import type React from 'react';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { desktopChrome } from '../../test/workspaceChrome';

/**
 * Omnibox sözleşmesi — `docs/65`.
 *
 * Bu dosya `WorkspaceApp.aiCommand.test.tsx` idi ve bağlı olmayan bir AI
 * merkezinin "dürüst yer tutucu" davranışını donduruyordu: devre dışı bir
 * komut kutusu, boş "etkilenen kayıtlar", devre dışı bir onay düğmesi.
 *
 * Dürüstlük iyiydi ama plan farklı bir şey söylüyor: sağlayıcı bağlı değilse
 * AI girişi HİÇ GÖSTERİLMEZ (`docs/50` §17). Yer tutucu, kullanıcıya değer
 * değil geliştirilmemiş bir özellik gösteriyordu.
 *
 * Yerine gerçekten iş gören deterministik bir omnibox geldi ve bu dosya onun
 * sözleşmesini donduruyor.
 */

vi.mock('./BrandOnboardingForm', () => ({
    BrandOnboardingForm: () => <div data-testid="brand-onboarding-form" />,
}));

vi.mock('./LocationOnboardingForm', () => ({
    LocationOnboardingForm: () => <div data-testid="location-onboarding-form" />,
}));

vi.mock('../catalog/menu/macro/MenuCatalogWorkspace', () => ({
    MenuCatalogWorkspace: (props: { workspaceId: number; locationId: number }) => (
        <div
            data-testid="menu-catalog-workspace"
            data-workspace-id={props.workspaceId}
            data-location-id={props.locationId}
        />
    ),
}));

const CSRF_COOKIE_URL = '/sanctum/csrf-cookie';
const WORKSPACE_ID = 71;
const AI_URL_PATTERN = /ai|assistant|command/i;

function importWorkspaceModule<
    T extends Record<string, unknown> = Record<string, unknown>,
>(): Promise<T> {
    return import('./WorkspaceApp') as unknown as Promise<T>;
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

function makeUser() {
    return { id: 1, name: 'Ada Lovelace', email: 'ada@example.com' };
}

function makeWorkspace() {
    return {
        id: WORKSPACE_ID,
        name: 'Zeytin Restoranları',
        slug: 'zeytin-restoranlari',
        state: 'active',
    };
}

function makeLocation(overrides: Partial<Record<string, unknown>> = {}) {
    return {
        id: 923,
        workspace_id: WORKSPACE_ID,
        brand_id: 811,
        display_name: 'Kadıköy',
        country_code: 'TR',
        timezone: 'Europe/Istanbul',
        city: 'İstanbul',
        address_line1: 'Bahariye Cd. 1',
        address_line2: null,
        postal_code: null,
        ...overrides,
    };
}

function buildFetchMock() {
    return vi.fn(async (url: string, init?: RequestInit) => {
        const method = (init?.method ?? 'GET').toUpperCase();

        if (String(url) === CSRF_COOKIE_URL) {
            return jsonResponse(204, {});
        }
        if (String(url) === '/api/user' && method === 'GET') {
            return jsonResponse(200, makeUser());
        }
        if (String(url) === '/api/workspaces' && method === 'GET') {
            return jsonResponse(200, [makeWorkspace()]);
        }
        if (String(url) === '/api/workspace-context' && method === 'GET') {
            return jsonResponse(200, makeWorkspace());
        }
        if (String(url) === `/api/workspaces/${WORKSPACE_ID}/brand` && method === 'GET') {
            return jsonResponse(200, { id: 811, workspace_id: WORKSPACE_ID, name: 'Zeytin' });
        }
        if (String(url) === `/api/workspaces/${WORKSPACE_ID}/brand/locations` && method === 'GET') {
            return jsonResponse(200, [makeLocation()]);
        }

        if (String(url) === `/api/workspaces/${WORKSPACE_ID}/brand/locations/923/menu`) {
            return jsonResponse(200, {
                id: 4501,
                workspaceId: WORKSPACE_ID,
                locationId: 923,
                name: 'Ana menü',
                state: 'draft',
                categories: [
                    {
                        id: 71,
                        menuId: 4501,
                        name: 'Kahvaltı',
                        position: 1,
                        menuItems: [
                            {
                                id: 900,
                                categoryId: 71,
                                productId: 5,
                                productName: 'Menemen',
                                priceMinorAmount: 18500,
                                currencyCode: 'TRY',
                                position: 1,
                                isVisible: true,
                                allergens: [],
                            },
                        ],
                    },
                ],
            });
        }

        // Omnibox HİÇBİR ağ isteği yapmaz: aradığı her şey zaten yüklü.
        if (AI_URL_PATTERN.test(String(url))) {
            throw new Error(`Unexpected AI/command fetch: ${method} ${String(url)}`);
        }

        throw new Error(`Unhandled fetch in WorkspaceApp omnibox test: ${method} ${String(url)}`);
    });
}

async function renderCurrentWorkspace() {
    const fetchMock = buildFetchMock();
    vi.stubGlobal('fetch', fetchMock);

    const { WorkspaceApp } = await importWorkspaceModule<{
        WorkspaceApp: React.ComponentType<typeof desktopChrome>;
    }>();
    render(<WorkspaceApp {...desktopChrome} />);

    await screen.findByRole('navigation', { name: 'Restaurant admin' });

    return fetchMock;
}

describe('WorkspaceApp — omnibox (docs/65)', () => {
    beforeEach(() => {
        history.replaceState(null, '', window.location.pathname);
    });

    it('üst çubukta tek bir omnibox tetikleyicisi vardır ve gezinti sonrası kalır', async () => {
        const user = userEvent.setup();
        await renderCurrentWorkspace();

        const banner = await screen.findByRole('banner');
        expect(
            within(banner).getAllByRole('button', { name: 'Search, go to, or create' }),
        ).toHaveLength(1);

        await user.click(await screen.findByRole('link', { name: 'Menus' }));

        expect(
            within(await screen.findByRole('banner')).getAllByRole('button', {
                name: 'Search, go to, or create',
            }),
        ).toHaveLength(1);

        vi.unstubAllGlobals();
    });

    /**
     * Kapsam GÖRÜNÜRDÜR: kullanıcı, seçtiği şeyin hangi çalışma alanı ve hangi
     * şube üzerinde iş göreceğini tahmin etmek zorunda kalmaz (`docs/50` §11).
     */
    it('açıldığında kapsamı ve deterministik grupları gösterir', async () => {
        const user = userEvent.setup();
        await renderCurrentWorkspace();

        await user.click(screen.getByRole('button', { name: 'Search, go to, or create' }));

        const dialog = await screen.findByRole('dialog');
        expect(within(dialog).getByText('Zeytin Restoranları')).toBeInTheDocument();
        expect(within(dialog).getByText('Kadıköy')).toBeInTheDocument();

        expect(within(dialog).getByRole('heading', { name: 'Go to' })).toBeInTheDocument();
        expect(within(dialog).getByRole('heading', { name: 'Create' })).toBeInTheDocument();

        vi.unstubAllGlobals();
    });

    /**
     * AI grubu YOKTUR ve bu bir eksiklik değil, bir karardır: bağlı bir
     * sağlayıcı olmadan AI girişi gösterilmez (`docs/50` §17).
     */
    it('bağlı AI sağlayıcısı yokken AI girişi sunmaz', async () => {
        const user = userEvent.setup();
        await renderCurrentWorkspace();

        await user.click(screen.getByRole('button', { name: 'Search, go to, or create' }));
        const dialog = await screen.findByRole('dialog');

        expect(within(dialog).queryByText(/ask zabuno/i)).toBeNull();
        expect(within(dialog).queryByRole('heading', { name: /ai/i })).toBeNull();
        // Ve devre dışı hiçbir kontrol bırakılmadı.
        expect(
            within(dialog)
                .queryAllByRole('button')
                .filter((button) => button.hasAttribute('disabled')),
        ).toHaveLength(0);

        vi.unstubAllGlobals();
    });

    /**
     * Sorgu boşken KAYIT gösterilmez: bir çalışma alanındaki bütün ürünleri
     * listelemek bir cevap değil, ikinci bir liste ekranıdır.
     */
    it('kayıtları yalnız arandığında gösterir ve seçilince oraya götürür', async () => {
        const user = userEvent.setup();
        await renderCurrentWorkspace();

        await user.click(await screen.findByRole('link', { name: 'Menus' }));
        await user.click(screen.getByRole('button', { name: 'Search, go to, or create' }));

        const dialog = await screen.findByRole('dialog');
        expect(within(dialog).queryByRole('heading', { name: 'In this workspace' })).toBeNull();

        await user.type(within(dialog).getByLabelText('Search'), 'menemen');

        expect(
            await within(dialog).findByRole('heading', { name: 'In this workspace' }),
        ).toBeInTheDocument();
        await user.click(within(dialog).getByRole('button', { name: /Menemen/ }));

        // Seçim diyaloğu kapatır ve hedefe götürür.
        await waitFor(() => {
            expect(screen.queryByRole('dialog')).toBeNull();
        });
        expect(window.location.pathname).toMatch(/\/menu$/);

        vi.unstubAllGlobals();
    });

    it('eşleşme yoksa bunu söyler', async () => {
        const user = userEvent.setup();
        await renderCurrentWorkspace();

        await user.click(screen.getByRole('button', { name: 'Search, go to, or create' }));
        const dialog = await screen.findByRole('dialog');

        await user.type(within(dialog).getByLabelText('Search'), 'zzzzz');

        expect(
            await within(dialog).findByText('Nothing matches that search in this workspace.'),
        ).toBeInTheDocument();

        vi.unstubAllGlobals();
    });

    it('hiçbir ağ isteği yapmadan çalışır', async () => {
        const user = userEvent.setup();
        const fetchMock = await renderCurrentWorkspace();
        const before = fetchMock.mock.calls.length;

        await user.click(screen.getByRole('button', { name: 'Search, go to, or create' }));
        await screen.findByRole('dialog');
        await user.type(screen.getByLabelText('Search'), 'kahv');

        expect(fetchMock.mock.calls).toHaveLength(before);

        vi.unstubAllGlobals();
    });

    /**
     * Kapanınca odak tetikleyiciye döner: klavye kullanıcısı, diyalogdan
     * çıktığında listenin başına atılmaz.
     */
    /**
     * Kısayolun anlamı "her yerden": bir alana odaklanmışken de çalışır.
     */
    it('Cmd/Ctrl+K ile açılır', async () => {
        const user = userEvent.setup();
        await renderCurrentWorkspace();

        expect(screen.queryByRole('dialog')).toBeNull();

        await user.keyboard('{Control>}k{/Control}');

        expect(await screen.findByRole('dialog', { name: 'Search and go' })).toBeInTheDocument();

        vi.unstubAllGlobals();
    });

    it('Escape ile kapanır ve odağı tetikleyiciye döndürür', async () => {
        const user = userEvent.setup();
        await renderCurrentWorkspace();

        const trigger = screen.getByRole('button', { name: 'Search, go to, or create' });
        await user.click(trigger);
        await screen.findByRole('dialog');

        await user.keyboard('{Escape}');

        await waitFor(() => {
            expect(screen.queryByRole('dialog')).toBeNull();
        });
        expect(trigger).toHaveFocus();

        vi.unstubAllGlobals();
    });
});
