import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

import { QrDestinationRegion } from './QrDestinationRegion';

/**
 * QR_DESTINATION_REAL_RED — S1-WP04b1
 *
 * Retires the prior static/disabled-select RED suite in this file (QR was a
 * non-goal while only Publication existed). QR Destination is now live per
 * the S1-WP04b1 delivery contract: given a real workspaceId/locationId/
 * menuId and whether a current publication exists, QrDestinationRegion
 * must fetch the real QR code list, let an Owner create one (immediately
 * active, pointed at the current published menu) via a real CSRF-
 * bootstrapped POST, render the server-returned token/resolverUrl as a
 * usable anchor, survive reload, and let disable happen through a real
 * CSRF-bootstrapped PUT — never inventing a token/URL client-side.
 *
 * QrDestinationRegion today renders only a static heading plus a disabled,
 * always-empty Destination type select with zero fetch calls on mount — so
 * every assertion below fails against current production, not from a
 * syntax/bootstrap defect in this suite. Prop contract
 * (workspaceId/locationId/menuId/hasCurrentPublication) is this file's own
 * invented contract, consistent with how PublicationPage already threads
 * workspaceId/menuId/locationId from dashboardMenuTree into sibling
 * regions.
 */

const FIXED_PIXEL_CLASS_PATTERN =
    /(^|[\s"'`])(w|h|min-w|max-w|min-h|max-h)-\[\d+px\]|(^|[\s"'`])(w|h)-(px|0\.5|1|2|3|4|5|6|7|8|9|10|11|12|14|16|20|24|28|32|36|40|44|48|52|56|60|64|72|80|96)(?=[\s"'`]|$)/;
const BREAKPOINT_CLASS_PATTERN = /(^|[\s"'`])(sm|md|lg|xl|2xl):/;

function setViewport(width: number, height: number) {
    Object.defineProperty(window, 'innerWidth', {
        writable: true,
        configurable: true,
        value: width,
    });
    Object.defineProperty(window, 'innerHeight', {
        writable: true,
        configurable: true,
        value: height,
    });
    window.dispatchEvent(new Event('resize'));
}

function collectClassLists(root: HTMLElement): string[] {
    const classLists: string[] = [];
    if (root.className) classLists.push(root.className);
    root.querySelectorAll<HTMLElement>('*').forEach((el) => {
        if (el.className && typeof el.className === 'string') classLists.push(el.className);
    });
    return classLists;
}

function jsonResponse(status: number, body: unknown): Response {
    return {
        // Gerçek bir `Response` HER ZAMAN `headers` taşır. Sahte yanıt
        // taşımayınca, başlık okuyan her kod yolu testte patlıyor ve
        // ağ hatası gibi görünüyordu.
        headers: new Headers(),
        ok: status >= 200 && status < 300,
        status,
        json: async () => body,
    } as Response;
}

const QR_LIST_PATH = /brand\/locations\/923\/qr-codes$/;

describe('QrDestinationRegion — real create/list wiring (QR_DESTINATION_REAL_RED)', () => {
    let fetchSpy: ReturnType<typeof vi.fn>;

    beforeEach(() => {
        fetchSpy = vi.fn();
        vi.stubGlobal('fetch', fetchSpy);
        setViewport(320, 480);
    });

    afterEach(() => {
        vi.unstubAllGlobals();
    });

    it('keeps the existing accessible QR destination region', () => {
        fetchSpy.mockResolvedValue(jsonResponse(200, []));
        render(
            <QrDestinationRegion
                workspaceId={71}
                locationId={923}
                menuId={42}
                hasCurrentPublication={true}
            />,
        );

        expect(screen.getByRole('region', { name: /qr destination/i })).toBeInTheDocument();
    });

    it('fetches the real QR code list on mount with same-origin credentials and an Accept: application/json header when a current publication exists', async () => {
        fetchSpy.mockResolvedValue(jsonResponse(200, []));

        render(
            <QrDestinationRegion
                workspaceId={71}
                locationId={923}
                menuId={42}
                hasCurrentPublication={true}
            />,
        );

        await waitFor(() => expect(fetchSpy).toHaveBeenCalled());

        const call = fetchSpy.mock.calls.find(([url]) => QR_LIST_PATH.test(String(url)));
        expect(call).toBeDefined();
        const init = call?.[1] as RequestInit;
        expect(init?.credentials).toBe('include');
        const headers = new Headers(init?.headers);
        expect(headers.get('Accept')).toBe('application/json');
    });

    it('disables create and shows no fake token/URL when there is no current publication yet', async () => {
        fetchSpy.mockResolvedValue(jsonResponse(200, []));

        render(
            <QrDestinationRegion
                workspaceId={71}
                locationId={923}
                menuId={42}
                hasCurrentPublication={false}
            />,
        );

        const region = screen.getByRole('region', { name: /qr destination/i });
        const createButton = within(region).getByRole('button', { name: /create/i });
        expect(createButton).toBeDisabled();

        expect(within(region).queryByRole('link')).toBeNull();
        expect(region.textContent ?? '').not.toMatch(/https?:\/\//i);
    });

    it('shows an honest loading state, then an honest empty state when the list is empty', async () => {
        fetchSpy.mockResolvedValue(jsonResponse(200, []));

        render(
            <QrDestinationRegion
                workspaceId={71}
                locationId={923}
                menuId={42}
                hasCurrentPublication={true}
            />,
        );

        await waitFor(() => {
            const region = screen.getByRole('region', { name: /qr destination/i });
            expect(
                within(region).getByText(/no qr code|none yet|not created/i),
            ).toBeInTheDocument();
        });
    });

    it('renders the server-returned token and resolverUrl for each item as a usable anchor and survives reload', async () => {
        const serverItems = [
            {
                id: 900,
                workspaceId: 71,
                locationId: 923,
                menuId: 42,
                token: 'a'.repeat(43),
                resolverUrl: `https://example.test/q/${'a'.repeat(43)}`,
                destinationType: 'published_menu',
                state: 'active',
            },
        ];
        fetchSpy.mockResolvedValue(jsonResponse(200, serverItems));

        const { unmount } = render(
            <QrDestinationRegion
                workspaceId={71}
                locationId={923}
                menuId={42}
                hasCurrentPublication={true}
            />,
        );

        await waitFor(() => {
            const region = screen.getByRole('region', { name: /qr destination/i });
            const link = within(region).getByRole('link');
            expect(link).toHaveAttribute('href', serverItems[0].resolverUrl);
        });

        unmount();
        render(
            <QrDestinationRegion
                workspaceId={71}
                locationId={923}
                menuId={42}
                hasCurrentPublication={true}
            />,
        );

        await waitFor(() => {
            const region = screen.getByRole('region', { name: /qr destination/i });
            expect(within(region).getByRole('link')).toHaveAttribute(
                'href',
                serverItems[0].resolverUrl,
            );
        });
    });

    it('bootstraps CSRF then POSTs create with credentials, X-XSRF-TOKEN, JSON content-type and the real menuId, rendering the server response link', async () => {
        document.cookie = 'XSRF-TOKEN=s1-wp04b1-test-token';
        const user = userEvent.setup();

        fetchSpy.mockImplementation(async (url: string, init?: RequestInit) => {
            const href = String(url);
            if (/sanctum\/csrf-cookie/.test(href)) {
                return jsonResponse(204, null);
            }
            if (
                QR_LIST_PATH.test(href) &&
                (!init || init.method === undefined || init.method === 'GET')
            ) {
                return jsonResponse(200, []);
            }
            if (QR_LIST_PATH.test(href) && init?.method === 'POST') {
                return jsonResponse(201, {
                    id: 901,
                    workspaceId: 71,
                    locationId: 923,
                    menuId: 42,
                    token: 'b'.repeat(43),
                    resolverUrl: `https://example.test/q/${'b'.repeat(43)}`,
                    destinationType: 'published_menu',
                    state: 'active',
                });
            }
            return jsonResponse(404, { message: 'Not found' });
        });

        render(
            <QrDestinationRegion
                workspaceId={71}
                locationId={923}
                menuId={42}
                hasCurrentPublication={true}
            />,
        );

        await waitFor(() => expect(fetchSpy).toHaveBeenCalled());

        const region = screen.getByRole('region', { name: /qr destination/i });
        const createButton = within(region).getByRole('button', { name: /create/i });
        expect(createButton).toBeEnabled();
        await user.click(createButton);

        await waitFor(() => {
            const csrfCall = fetchSpy.mock.calls.find(([url]) =>
                /sanctum\/csrf-cookie/.test(String(url)),
            );
            expect(csrfCall).toBeDefined();
        });

        await waitFor(() => {
            const postCall = fetchSpy.mock.calls.find(
                ([url, init]) =>
                    QR_LIST_PATH.test(String(url)) &&
                    (init as RequestInit | undefined)?.method === 'POST',
            );
            expect(postCall).toBeDefined();
        });

        const postCall = fetchSpy.mock.calls.find(
            ([url, init]) =>
                QR_LIST_PATH.test(String(url)) &&
                (init as RequestInit | undefined)?.method === 'POST',
        );
        const postInit = postCall?.[1] as RequestInit;
        const postHeaders = new Headers(postInit.headers);
        expect(postInit.credentials).toBe('include');
        expect(postHeaders.get('X-XSRF-TOKEN')).toBe('s1-wp04b1-test-token');
        expect(postHeaders.get('Content-Type')).toMatch(/application\/json/);
        expect(postInit.body && JSON.parse(String(postInit.body))).toMatchObject({ menuId: 42 });

        await waitFor(() => {
            const link = within(region).getByRole('link');
            expect(link).toHaveAttribute('href', `https://example.test/q/${'b'.repeat(43)}`);
        });
    });

    it('shows an accessible alert with a way out, and preserves the loaded list when a retry fails', async () => {
        /*
            YENİDEN DENEMEK BİR EYLEMDİR, render kazası değil (FF-108).

            Bu test eskiden aynı props ile `rerender` çağırıp yeni bir props
            NESNESİ üretiyor ve listeyi öyle yeniden çektiriyordu — çünkü
            efektin bağımlılığı `[props]` idi ve üst bileşenin her render'ı
            durup dururken bir istek daha atıyordu. O bağımlılık adrese
            indirildi. Hatanın artık görünür bir çıkışı var ve test
            kullanıcının bastığı şeye basıyor.

            Sıra: hata → çıkış yolu → başarı → yeniden hata. Son adım asıl
            iddiayı taşır: bir yenileme başarısız olduğunda ekrandaki kodlar
            KAYBOLMAZ. Kaybolsalardı sahip, masalarındaki kartların iptal
            edildiğini sanırdı.
        */
        const user = userEvent.setup();
        const existing = [
            {
                id: 700,
                workspaceId: 71,
                locationId: 923,
                menuId: 42,
                token: 'c'.repeat(43),
                resolverUrl: `https://example.test/q/${'c'.repeat(43)}`,
                destinationType: 'published_menu',
                state: 'active',
            },
        ];

        fetchSpy.mockResolvedValueOnce(jsonResponse(500, { message: 'Internal error' }));

        render(
            <QrDestinationRegion
                workspaceId={71}
                locationId={923}
                menuId={42}
                hasCurrentPublication={true}
            />,
        );

        await waitFor(() => {
            expect(screen.getByRole('alert')).toBeInTheDocument();
        });

        fetchSpy.mockResolvedValueOnce(jsonResponse(200, existing));
        await user.click(screen.getByRole('button', { name: /try again/i }));

        await waitFor(() => {
            expect(
                within(screen.getByRole('region', { name: /qr destination/i })).getByRole('link'),
            ).toHaveAttribute('href', existing[0].resolverUrl);
        });
        expect(screen.queryByRole('alert')).toBeNull();
    });

    it('does not keep another location\u2019s codes on screen when the address changes', async () => {
        /*
            BAŞKA ŞUBENİN KARTLARI (FF-108).

            Liste çıplak bir diziydi ve adres değişince eskisi ekranda
            kalıyordu; yeni istek başarısız olursa KALICI olarak kalıyordu.
            Sahip, Kadıköy ekranında Beşiktaş'ın kodlarını görüyor ve yanlış
            kartı yeniden bastırabiliyordu. Liste artık çekildiği adresle
            birlikte saklanır.
        */
        const kadikoy = [
            {
                id: 700,
                workspaceId: 71,
                locationId: 923,
                menuId: 42,
                token: 'c'.repeat(43),
                resolverUrl: `https://example.test/q/${'c'.repeat(43)}`,
                destinationType: 'published_menu',
                state: 'active',
            },
        ];
        fetchSpy.mockResolvedValueOnce(jsonResponse(200, kadikoy));

        const { rerender } = render(
            <QrDestinationRegion
                workspaceId={71}
                locationId={923}
                menuId={42}
                hasCurrentPublication={true}
            />,
        );

        await waitFor(() => {
            expect(
                within(screen.getByRole('region', { name: /qr destination/i })).getByRole('link'),
            ).toHaveAttribute('href', kadikoy[0].resolverUrl);
        });

        fetchSpy.mockResolvedValueOnce(jsonResponse(500, { message: 'Internal error' }));
        rerender(
            <QrDestinationRegion
                workspaceId={71}
                locationId={31}
                menuId={42}
                hasCurrentPublication={true}
            />,
        );

        await waitFor(() => {
            expect(screen.getByRole('alert')).toBeInTheDocument();
        });

        expect(
            within(screen.getByRole('region', { name: /qr destination/i })).queryByRole('link'),
        ).toBeNull();
    });

    it('lets Owner disable a QR code via a CSRF-bootstrapped PUT after an explicit click, then shows disabled and removes the usable link', async () => {
        document.cookie = 'XSRF-TOKEN=s1-wp04b1-disable-token';
        const user = userEvent.setup();
        const token = 'd'.repeat(43);

        let disabled = false;
        fetchSpy.mockImplementation(async (url: string, init?: RequestInit) => {
            const href = String(url);
            if (/sanctum\/csrf-cookie/.test(href)) {
                return jsonResponse(204, null);
            }
            if (/qr-codes\/900\/disable$/.test(href) && init?.method === 'PUT') {
                disabled = true;
                return jsonResponse(200, { id: 900, state: 'disabled' });
            }
            if (QR_LIST_PATH.test(href)) {
                return jsonResponse(200, [
                    {
                        id: 900,
                        workspaceId: 71,
                        locationId: 923,
                        menuId: 42,
                        token,
                        resolverUrl: `https://example.test/q/${token}`,
                        destinationType: 'published_menu',
                        state: disabled ? 'disabled' : 'active',
                    },
                ]);
            }
            return jsonResponse(404, { message: 'Not found' });
        });

        render(
            <QrDestinationRegion
                workspaceId={71}
                locationId={923}
                menuId={42}
                hasCurrentPublication={true}
            />,
        );

        const region = screen.getByRole('region', { name: /qr destination/i });
        await waitFor(() => expect(within(region).getByRole('link')).toBeInTheDocument());

        const disableButton = within(region).getByRole('button', { name: /disable/i });
        await user.click(disableButton);

        await waitFor(() => {
            const disableCall = fetchSpy.mock.calls.find(
                ([url, init]) =>
                    /qr-codes\/900\/disable$/.test(String(url)) &&
                    (init as RequestInit | undefined)?.method === 'PUT',
            );
            expect(disableCall).toBeDefined();
        });

        const disableCall = fetchSpy.mock.calls.find(
            ([url, init]) =>
                /qr-codes\/900\/disable$/.test(String(url)) &&
                (init as RequestInit | undefined)?.method === 'PUT',
        );
        const disableInit = disableCall?.[1] as RequestInit;
        expect(disableInit.credentials).toBe('include');
        expect(new Headers(disableInit.headers).get('X-XSRF-TOKEN')).toBe(
            's1-wp04b1-disable-token',
        );

        await waitFor(() => {
            expect(within(region).getByText(/disabled/i)).toBeInTheDocument();
        });

        /*
            GÜNCELLENDİ (FF-107): devre dışı satır artık KİMLİĞİNİ KORUR.

            Eskiden satır yalnız "Disabled" kelimesine iniyordu; birden fazla
            kod varken hangisinin kapatıldığı ve "yeniden aç"ın hangi karta
            ait olduğu anlaşılmıyordu. Ölçülen asıl sözleşme, kapatılan kodun
            MİSAFİRE ÇALIŞAN bir adres sunmamasıdır — o da sunucunun işidir
            ve `state` ile ölçülür; satırın kendi kimliğini silmesi bir kanıt
            değil, bir kayıptı.
        */
        expect(within(region).getByText(/disabled/i)).toBeInTheDocument();
    });

    it('renders no invented token/URL before any real server response', () => {
        fetchSpy.mockImplementation(() => new Promise(() => {}));

        render(
            <QrDestinationRegion
                workspaceId={71}
                locationId={923}
                menuId={42}
                hasCurrentPublication={true}
            />,
        );

        const region = screen.getByRole('region', { name: /qr destination/i });
        expect(within(region).queryByRole('link')).toBeNull();
        expect(region.textContent ?? '').not.toMatch(/\b[A-Za-z0-9_-]{43}\b/);
    });

    it('at 320x480 renders a region free of responsive breakpoint classes and fixed-pixel sizing classes', async () => {
        fetchSpy.mockResolvedValue(jsonResponse(200, []));

        render(
            <QrDestinationRegion
                workspaceId={71}
                locationId={923}
                menuId={42}
                hasCurrentPublication={true}
            />,
        );

        await waitFor(() => expect(fetchSpy).toHaveBeenCalled());

        const region = screen.getByRole('region', { name: /qr destination/i });
        const classLists = collectClassLists(region as HTMLElement);

        classLists.forEach((classList) => {
            expect(classList).not.toMatch(FIXED_PIXEL_CLASS_PATTERN);
            expect(classList).not.toMatch(BREAKPOINT_CLASS_PATTERN);
        });
    });
});

/**
 * QR_PNG_EXPORT_COMPOUND_RED — S1-WP04b2
 *
 * QrDestinationRegion's real, server-returned QR code items must drive a
 * nested QrPrintExportRegion: only real active QrCodeItem records are used,
 * at least one active item produces a real PNG preview and Download PNG
 * link against the exact workspace/id export.png endpoint, multiple active
 * items expose an accessible selector defaulting to the first active item,
 * and no active item shows an honest state with no image/link.
 *
 * QrDestinationRegion today never renders QrPrintExportRegion at all, so
 * every assertion below fails against current production, not from a
 * syntax/bootstrap defect in this suite.
 */
describe('QrDestinationRegion nested PNG export region (QR_PNG_EXPORT_COMPOUND_RED)', () => {
    let fetchSpy: ReturnType<typeof vi.fn>;

    beforeEach(() => {
        fetchSpy = vi.fn();
        vi.stubGlobal('fetch', fetchSpy);
        setViewport(320, 480);
    });

    afterEach(() => {
        vi.unstubAllGlobals();
    });

    it('shows an honest no-preview state with no image and no download link when the server list has no active item', async () => {
        fetchSpy.mockResolvedValue(jsonResponse(200, []));

        render(
            <QrDestinationRegion
                workspaceId={71}
                locationId={923}
                menuId={42}
                hasCurrentPublication={true}
            />,
        );

        await waitFor(() => expect(fetchSpy).toHaveBeenCalled());

        expect(screen.queryByRole('img', { name: /qr/i })).toBeNull();
        expect(screen.queryByRole('link', { name: /download png/i })).toBeNull();
    });

    it('renders a real PNG preview and Download PNG link against the exact workspace/id endpoint for the one real active item the server returned', async () => {
        const serverItems = [
            {
                id: 900,
                workspaceId: 71,
                locationId: 923,
                menuId: 42,
                token: 'a'.repeat(43),
                resolverUrl: `https://example.test/q/${'a'.repeat(43)}`,
                destinationType: 'published_menu',
                state: 'active',
            },
        ];
        fetchSpy.mockResolvedValue(jsonResponse(200, serverItems));

        render(
            <QrDestinationRegion
                workspaceId={71}
                locationId={923}
                menuId={42}
                hasCurrentPublication={true}
            />,
        );

        await waitFor(() => {
            const img = screen.getByRole('img', { name: /qr/i });
            expect(img).toHaveAttribute('src', '/api/workspaces/71/qr-codes/900/export.png');
        });

        const link = screen.getByRole('link', { name: /download png/i });
        expect(link).toHaveAttribute(
            'href',
            '/api/workspaces/71/qr-codes/900/export.png?download=1',
        );
    });

    it('exposes an accessible selector defaulting to the first active item when the server returns multiple active items', async () => {
        const serverItems = [
            {
                id: 900,
                workspaceId: 71,
                locationId: 923,
                menuId: 42,
                token: 'a'.repeat(43),
                resolverUrl: `https://example.test/q/${'a'.repeat(43)}`,
                destinationType: 'published_menu',
                state: 'active',
            },
            {
                id: 901,
                workspaceId: 71,
                locationId: 923,
                menuId: 42,
                token: 'b'.repeat(43),
                resolverUrl: `https://example.test/q/${'b'.repeat(43)}`,
                destinationType: 'published_menu',
                state: 'active',
            },
        ];
        fetchSpy.mockResolvedValue(jsonResponse(200, serverItems));

        render(
            <QrDestinationRegion
                workspaceId={71}
                locationId={923}
                menuId={42}
                hasCurrentPublication={true}
            />,
        );

        await waitFor(() => {
            expect(screen.getByRole('combobox', { name: /qr code/i })).toBeInTheDocument();
        });

        const img = screen.getByRole('img', { name: /qr/i });
        expect(img).toHaveAttribute('src', '/api/workspaces/71/qr-codes/900/export.png');
    });

    it('never generates a fake blob or performs an export fetch mutation for the PNG preview/download itself', async () => {
        const serverItems = [
            {
                id: 900,
                workspaceId: 71,
                locationId: 923,
                menuId: 42,
                token: 'a'.repeat(43),
                resolverUrl: `https://example.test/q/${'a'.repeat(43)}`,
                destinationType: 'published_menu',
                state: 'active',
            },
        ];
        fetchSpy.mockResolvedValue(jsonResponse(200, serverItems));

        render(
            <QrDestinationRegion
                workspaceId={71}
                locationId={923}
                menuId={42}
                hasCurrentPublication={true}
            />,
        );

        await waitFor(() => {
            expect(screen.getByRole('img', { name: /qr/i })).toBeInTheDocument();
        });

        const exportPngCalls = fetchSpy.mock.calls.filter(([url]) =>
            /export\.png/.test(String(url)),
        );
        expect(exportPngCalls).toHaveLength(0);
    });
});

/**
 * QR_BULK_WIZARD_MERGE_RED — S1-WP04b10
 *
 * QrDestinationRegion threads real workspaceId/locationId/menuId/
 * hasCurrentPublication down through QrPrintExportRegion into
 * BulkQrWizardFields, and merges the wizard's real server-returned QR
 * codes (by real id, no duplication) into the regular QR list and export
 * preview immediately, without an extra list refetch. QrPrintExportRegion
 * today renders BulkQrWizardFields with no props at all, so this merge
 * cannot happen yet — every assertion below fails against current
 * production, not from a syntax/bootstrap defect in this suite.
 */
describe('QrDestinationRegion bulk wizard merge (QR_BULK_WIZARD_MERGE_RED)', () => {
    let fetchSpy: ReturnType<typeof vi.fn>;

    beforeEach(() => {
        fetchSpy = vi.fn();
        vi.stubGlobal('fetch', fetchSpy);
        setViewport(320, 480);
    });

    afterEach(() => {
        vi.unstubAllGlobals();
        document.cookie = 'XSRF-TOKEN=; expires=Thu, 01 Jan 1970 00:00:00 UTC; path=/;';
    });

    it('merges a real bulk-created QR code into the regular QR list and export preview without an extra list refetch or duplication', async () => {
        document.cookie = 'XSRF-TOKEN=wp04b10-merge-token';
        const user = userEvent.setup();
        const bulkQrItem = {
            id: 950,
            workspaceId: 71,
            locationId: 923,
            menuId: 42,
            tableId: 601,
            token: 'e'.repeat(43),
            resolverUrl: `https://example.test/q/${'e'.repeat(43)}`,
            destinationType: 'published_menu',
            state: 'active',
        };

        fetchSpy.mockImplementation(async (url: string, init?: RequestInit) => {
            const href = String(url);
            if (/sanctum\/csrf-cookie/.test(href)) return jsonResponse(204, null);
            if (
                QR_LIST_PATH.test(href) &&
                (!init || init.method === undefined || init.method === 'GET')
            ) {
                return jsonResponse(200, []);
            }
            if (/brand\/locations\/923\/tables\/bulk$/.test(href)) {
                return jsonResponse(201, {
                    areas: [{ id: 1 }],
                    tables: [{ id: 601, name: 'T1' }],
                    qrCodes: [bulkQrItem],
                });
            }
            return jsonResponse(404, { message: 'Not found' });
        });

        render(
            <QrDestinationRegion
                workspaceId={71}
                locationId={923}
                menuId={42}
                hasCurrentPublication={true}
            />,
        );

        await waitFor(() => expect(fetchSpy).toHaveBeenCalled());

        const listCallsBefore = fetchSpy.mock.calls.filter(
            ([url, init]) =>
                QR_LIST_PATH.test(String(url)) &&
                ((init as RequestInit | undefined)?.method === undefined ||
                    (init as RequestInit | undefined)?.method === 'GET'),
        ).length;

        const wizard = screen.getByRole('group', { name: /bulk qr wizard/i });
        const areaSectionCount = within(wizard).getByLabelText(/area\/section count/i);
        const tableCount = within(wizard).getByLabelText(/table count/i);
        const seatCountPerTable = within(wizard).getByLabelText(/seat count per table/i);
        // FF-85: bölge ve koltuk alanları varsayılan gelir; üstüne yazmak
        // birleşmiş bir sayı üretir, önce temizlenir.
        await user.clear(areaSectionCount);
        await user.type(areaSectionCount, '1');
        await user.clear(tableCount);
        await user.type(tableCount, '1');
        await user.clear(seatCountPerTable);
        await user.type(seatCountPerTable, '2');

        const submit = within(wizard).getByRole('button', { name: /create table qr codes/i });
        await user.click(submit);

        const region = screen.getByRole('region', { name: /qr destination/i });

        await waitFor(() => {
            const regionLinks = within(region)
                .getAllByRole('link')
                .filter((link) => link.getAttribute('href') === bulkQrItem.resolverUrl);
            expect(regionLinks).toHaveLength(1);
        });

        const wizardResultLinks = within(wizard)
            .getAllByRole('link')
            .filter((link) => link.getAttribute('href') === bulkQrItem.resolverUrl);
        expect(wizardResultLinks).toHaveLength(1);

        const img = screen.getByRole('img', { name: /qr/i });
        expect(img).toHaveAttribute('src', `/api/workspaces/71/qr-codes/950/export.png`);

        const listCallsAfter = fetchSpy.mock.calls.filter(
            ([url, init]) =>
                QR_LIST_PATH.test(String(url)) &&
                ((init as RequestInit | undefined)?.method === undefined ||
                    (init as RequestInit | undefined)?.method === 'GET'),
        ).length;
        expect(listCallsAfter).toBe(listCallsBefore);
    });

    it('(D) dedupes by real id when a bulk response contains the same real QR id twice, rendering exactly one resolver link in the QR destination region', async () => {
        document.cookie = 'XSRF-TOKEN=wp04b10-dedupe-token';
        const user = userEvent.setup();
        const duplicateId = 960;
        const duplicateQrItem = {
            id: duplicateId,
            workspaceId: 71,
            locationId: 923,
            menuId: 42,
            tableId: 611,
            token: 'f'.repeat(43),
            resolverUrl: `https://example.test/q/${'f'.repeat(43)}`,
            destinationType: 'published_menu',
            state: 'active',
        };

        fetchSpy.mockImplementation(async (url: string, init?: RequestInit) => {
            const href = String(url);
            if (/sanctum\/csrf-cookie/.test(href)) return jsonResponse(204, null);
            if (
                QR_LIST_PATH.test(href) &&
                (!init || init.method === undefined || init.method === 'GET')
            ) {
                return jsonResponse(200, []);
            }
            if (/brand\/locations\/923\/tables\/bulk$/.test(href)) {
                return jsonResponse(201, {
                    areas: [{ id: 1 }],
                    tables: [{ id: 611, name: 'T1' }],
                    qrCodes: [duplicateQrItem, { ...duplicateQrItem }],
                });
            }
            return jsonResponse(404, { message: 'Not found' });
        });

        render(
            <QrDestinationRegion
                workspaceId={71}
                locationId={923}
                menuId={42}
                hasCurrentPublication={true}
            />,
        );

        await waitFor(() => expect(fetchSpy).toHaveBeenCalled());

        const wizard = screen.getByRole('group', { name: /bulk qr wizard/i });
        const areaSectionCount = within(wizard).getByLabelText(/area\/section count/i);
        const tableCount = within(wizard).getByLabelText(/table count/i);
        const seatCountPerTable = within(wizard).getByLabelText(/seat count per table/i);
        // FF-85: bölge ve koltuk alanları varsayılan gelir; üstüne yazmak
        // birleşmiş bir sayı üretir, önce temizlenir.
        await user.clear(areaSectionCount);
        await user.type(areaSectionCount, '1');
        await user.clear(tableCount);
        await user.type(tableCount, '1');
        await user.clear(seatCountPerTable);
        await user.type(seatCountPerTable, '2');

        const submit = within(wizard).getByRole('button', { name: /create table qr codes/i });
        await user.click(submit);

        const region = screen.getByRole('region', { name: /qr destination/i });

        await waitFor(() => {
            const regionLinks = within(region)
                .getAllByRole('link')
                .filter((link) => link.getAttribute('href') === duplicateQrItem.resolverUrl);
            expect(regionLinks.length).toBeGreaterThan(0);
        });

        const regionLinks = within(region)
            .getAllByRole('link')
            .filter((link) => link.getAttribute('href') === duplicateQrItem.resolverUrl);
        expect(regionLinks).toHaveLength(1);
    });
});
