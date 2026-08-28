import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { render, screen, waitFor, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

import { AnalyticsPage } from './AnalyticsPage';

/**
 * ANALYTICS_FRONTEND_RED
 *
 * S1-WP05b1 RED — AnalyticsPage is frozen to the real
 * GET /api/workspaces/{workspaceId}/brand/locations/{locationId}/analytics/summary?range=...
 * contract instead of the honest-unavailable stub it renders today: it
 * must accept real workspaceId/locationId props, never fetch before both
 * are defined, offer Today/Last 7 days/Last 30 days, show real loading
 * and API-failure states without fabricating a zero, render a real zero
 * only when the API actually returns one, render real nonzero counts in
 * accessible metric cards, refetch on range change and drop a stale
 * in-flight response, and stay 320 CSS px fluid with no breakpoint
 * classes or "responsive" wording. None of this exists yet, so every
 * assertion below fails RED against current production. No production,
 * i18n, Storybook, backend or Git edits are made from this file.
 */

const WORKSPACE_ID = 41;
const LOCATION_ID = 907;
const SUMMARY_ENDPOINT = `/api/workspaces/${WORKSPACE_ID}/brand/locations/${LOCATION_ID}/analytics/summary`;

const BREAKPOINT_CLASS_PATTERN = /(^|[\s"'`])(sm|md|lg|xl|2xl):/;
const RESPONSIVE_WORDING = /\bresponsive\b/i;

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

function summaryBody(range: string, qrResolveCount: number, menuOpenCount: number) {
    return { range, qrResolveCount, menuOpenCount, generatedAt: '2026-08-22T09:00:00.000Z' };
}

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

describe('AnalyticsPage — S1-WP05b1 real ledger summary surface (ANALYTICS_FRONTEND_RED)', () => {
    let fetchSpy: ReturnType<typeof vi.fn>;

    beforeEach(() => {
        setViewport(320, 480);
        fetchSpy = vi.fn(async (url: string) => {
            if (String(url) === `${SUMMARY_ENDPOINT}?range=today`) {
                return jsonResponse(200, summaryBody('today', 3, 2));
            }
            throw new Error(`Unhandled fetch: ${String(url)}`);
        });
        vi.stubGlobal('fetch', fetchSpy);
    });

    afterEach(() => {
        vi.unstubAllGlobals();
    });

    it('never fetches the summary endpoint before both workspaceId and locationId are provided', async () => {
        render(<AnalyticsPage />);

        await new Promise((resolve) => setTimeout(resolve, 0));

        expect(fetchSpy).not.toHaveBeenCalled();
    });

    it('fetches the exact location-scoped summary endpoint for the default Today range once real IDs are provided', async () => {
        render(<AnalyticsPage workspaceId={WORKSPACE_ID} locationId={LOCATION_ID} />);

        await waitFor(() => expect(fetchSpy).toHaveBeenCalled());

        const [url] = fetchSpy.mock.calls[0];
        expect(String(url)).toBe(`${SUMMARY_ENDPOINT}?range=today`);
    });

    it('offers Today, Last 7 days, and Last 30 days range options', () => {
        render(<AnalyticsPage workspaceId={WORKSPACE_ID} locationId={LOCATION_ID} />);

        const rangeControl = screen.getByRole('combobox', { name: /range/i });
        const optionLabels = within(rangeControl)
            .getAllByRole('option')
            .map((option) => option.textContent);

        expect(optionLabels).toEqual(
            expect.arrayContaining([
                expect.stringMatching(/today/i),
                expect.stringMatching(/last 7 days/i),
                expect.stringMatching(/last 30 days/i),
            ]),
        );
    });

    it('shows a real loading state before the fetch resolves, never a fabricated zero', async () => {
        let resolveFetch: ((value: Response) => void) | undefined;
        fetchSpy.mockImplementation(
            () =>
                new Promise<Response>((resolve) => {
                    resolveFetch = resolve;
                }),
        );

        render(<AnalyticsPage workspaceId={WORKSPACE_ID} locationId={LOCATION_ID} />);

        await waitFor(() => expect(fetchSpy).toHaveBeenCalled());

        const region = screen.getByRole('region', { name: /metric|report/i });
        expect(within(region).getByText(/loading/i)).toBeInTheDocument();
        expect(within(region).queryByText(/^0$/)).not.toBeInTheDocument();

        resolveFetch?.(jsonResponse(200, summaryBody('today', 3, 2)));
        await waitFor(() => expect(within(region).getByText('3')).toBeInTheDocument());
    });

    it('shows an explicit API-failure state and never fabricates a zero when the request fails', async () => {
        fetchSpy.mockImplementation(async () => jsonResponse(500, { message: 'Server Error' }));

        render(<AnalyticsPage workspaceId={WORKSPACE_ID} locationId={LOCATION_ID} />);

        await waitFor(() => expect(fetchSpy).toHaveBeenCalled());

        const region = await screen.findByRole('region', { name: /metric|report/i });
        await waitFor(() =>
            expect(within(region).getByText(/fail|error|unavailable/i)).toBeInTheDocument(),
        );
        expect(within(region).queryByText(/^0$/)).not.toBeInTheDocument();
    });

    /**
     * SIFIR, "veri yok"un tek bir hâli değildir — `docs/66`.
     *
     * Bu test eskiden sıfırlardan oluşan bir ızgara bekliyordu. "0 tarama /
     * 0 menü açılışı" teknik olarak dürüsttü ama kullanıcıya hiçbir şey
     * söylemiyordu: neden sıfır olduğunu ve şimdi ne yapılacağını değil.
     *
     * Menüsüz bir çalışma alanında doğru cevap "önce menü"dür; sayaç değil.
     */
    it('sıfır dönen bir sonuçta sebebe göre ayrılmış boş durumu gösterir', async () => {
        fetchSpy.mockImplementation(async () => jsonResponse(200, summaryBody('today', 0, 0)));

        render(
            <AnalyticsPage
                workspaceId={WORKSPACE_ID}
                locationId={LOCATION_ID}
                onNavigateToSection={vi.fn()}
            />,
        );

        const region = await screen.findByRole('region', { name: /metric|report/i });

        await waitFor(() => {
            expect(
                within(region).getByText('Analytics starts with your first menu'),
            ).toBeInTheDocument();
        });
        expect(within(region).getByRole('button', { name: 'Build the menu' })).toBeInTheDocument();

        // Ve sıfırlardan oluşan bir ızgara ÇİZİLMEZ.
        expect(within(region).queryAllByText('0')).toHaveLength(0);
    });

    /**
     * Gezinti verilmediğinde durum EYLEMSİZ kalmaz: neden eylem sunulamadığını
     * söyler. Tip seviyesinde zorlanan sözleşme budur (`docs/59`).
     */
    it('gezinti yolu verilmediğinde neden eylem olmadığını söyler', async () => {
        fetchSpy.mockImplementation(async () => jsonResponse(200, summaryBody('today', 0, 0)));

        render(<AnalyticsPage workspaceId={WORKSPACE_ID} locationId={LOCATION_ID} />);

        const region = await screen.findByRole('region', { name: /metric|report/i });

        await waitFor(() => {
            expect(
                within(region).getByText('Analytics starts with your first menu'),
            ).toBeInTheDocument();
        });
        expect(within(region).queryByRole('button', { name: 'Build the menu' })).toBeNull();
        expect(within(region).getByText('Open that screen from the sidebar.')).toBeInTheDocument();
    });

    it('renders real nonzero QR Resolve and Confirmed Menu Open counts in accessible metric cards', async () => {
        render(<AnalyticsPage workspaceId={WORKSPACE_ID} locationId={LOCATION_ID} />);

        const region = await screen.findByRole('region', { name: /metric|report/i });

        await waitFor(() => expect(within(region).getByText('3')).toBeInTheDocument());
        expect(within(region).getByText('2')).toBeInTheDocument();
        expect(within(region).getByText(/qr resolve/i)).toBeInTheDocument();
        expect(within(region).getByText(/menu open/i)).toBeInTheDocument();
    });

    it('refetches on range change and, when the superseded initial request resolves late, keeps the newer range result instead of being overwritten by it', async () => {
        const user = (await import('@testing-library/user-event')).default.setup();

        let resolveToday: ((value: Response) => void) | undefined;
        fetchSpy.mockImplementation(async (url: string) => {
            if (String(url) === `${SUMMARY_ENDPOINT}?range=today`) {
                return new Promise<Response>((resolve) => {
                    resolveToday = resolve;
                });
            }
            if (String(url) === `${SUMMARY_ENDPOINT}?range=7d`) {
                return jsonResponse(200, summaryBody('7d', 19, 12));
            }
            throw new Error(`Unhandled fetch: ${String(url)}`);
        });

        render(<AnalyticsPage workspaceId={WORKSPACE_ID} locationId={LOCATION_ID} />);

        // The initial Today request is still in flight (deferred) when the
        // user switches to 7d before it ever resolves.
        await waitFor(() =>
            expect(fetchSpy).toHaveBeenCalledWith(`${SUMMARY_ENDPOINT}?range=today`),
        );

        await user.selectOptions(screen.getByRole('combobox', { name: /range/i }), '7d');

        const region = await screen.findByRole('region', { name: /metric|report/i });
        await waitFor(() => expect(within(region).getByText('19')).toBeInTheDocument());
        expect(within(region).getByText('12')).toBeInTheDocument();

        // The superseded Today request now resolves late; its stale result
        // must not clobber the already-displayed 7d result.
        resolveToday?.(jsonResponse(200, summaryBody('today', 3, 2)));

        await new Promise((resolve) => setTimeout(resolve, 0));

        expect(within(region).getByText('19')).toBeInTheDocument();
        expect(within(region).getByText('12')).toBeInTheDocument();
        expect(within(region).queryByText('3')).not.toBeInTheDocument();
    });

    it('stays 320 CSS px fluid with no breakpoint-prefixed classes or "responsive" wording', async () => {
        const { container } = render(
            <AnalyticsPage workspaceId={WORKSPACE_ID} locationId={LOCATION_ID} />,
        );

        await waitFor(() => expect(fetchSpy).toHaveBeenCalled());

        for (const classList of collectClassLists(container)) {
            expect(classList).not.toMatch(BREAKPOINT_CLASS_PATTERN);
        }
        expect(container.textContent ?? '').not.toMatch(RESPONSIVE_WORDING);
    });

    it('offers a visible accessible Refresh action in the successful report state that reissues the exact current workspace/location/range request', async () => {
        const user = (await import('@testing-library/user-event')).default.setup();

        render(<AnalyticsPage workspaceId={WORKSPACE_ID} locationId={LOCATION_ID} />);

        const region = await screen.findByRole('region', { name: /metric|report/i });
        await waitFor(() => expect(within(region).getByText('3')).toBeInTheDocument());

        const refreshButton = within(region).getByRole('button', { name: /refresh/i });
        expect(refreshButton).toBeEnabled();

        fetchSpy.mockClear();
        await user.click(refreshButton);

        await waitFor(() =>
            expect(fetchSpy).toHaveBeenCalledWith(`${SUMMARY_ENDPOINT}?range=today`),
        );
        expect(fetchSpy).toHaveBeenCalledTimes(1);
    });

    it('disables the Refresh action with truthful loading while the refresh request is pending', async () => {
        const user = (await import('@testing-library/user-event')).default.setup();

        render(<AnalyticsPage workspaceId={WORKSPACE_ID} locationId={LOCATION_ID} />);

        const region = await screen.findByRole('region', { name: /metric|report/i });
        await waitFor(() => expect(within(region).getByText('3')).toBeInTheDocument());

        let resolveRefresh: ((value: Response) => void) | undefined;
        fetchSpy.mockImplementation(
            () =>
                new Promise<Response>((resolve) => {
                    resolveRefresh = resolve;
                }),
        );

        const refreshButton = within(region).getByRole('button', { name: /refresh/i });
        await user.click(refreshButton);

        await waitFor(() => expect(refreshButton).toBeDisabled());
        expect(within(region).getByText(/loading/i)).toBeInTheDocument();
        expect(within(region).queryByText(/^0$/)).not.toBeInTheDocument();

        resolveRefresh?.(jsonResponse(200, summaryBody('today', 3, 2)));
        await waitFor(() => expect(refreshButton).toBeEnabled());
    });

    it('labels the action Retry (not Refresh) on HTTP and thrown-network failures without fabricating a zero counter', async () => {
        fetchSpy.mockImplementation(async () => jsonResponse(500, { message: 'Server Error' }));

        const { unmount } = render(
            <AnalyticsPage workspaceId={WORKSPACE_ID} locationId={LOCATION_ID} />,
        );

        let region = await screen.findByRole('region', { name: /metric|report/i });
        await waitFor(() =>
            expect(within(region).getByRole('button', { name: /retry/i })).toBeInTheDocument(),
        );
        expect(
            within(region).queryByRole('button', { name: /^refresh$/i }),
        ).not.toBeInTheDocument();
        expect(within(region).queryByText(/^0$/)).not.toBeInTheDocument();

        unmount();

        fetchSpy.mockImplementation(async () => {
            throw new Error('Network failure');
        });

        render(<AnalyticsPage workspaceId={WORKSPACE_ID} locationId={LOCATION_ID} />);

        region = await screen.findByRole('region', { name: /metric|report/i });
        await waitFor(() =>
            expect(within(region).getByRole('button', { name: /retry/i })).toBeInTheDocument(),
        );
        expect(within(region).queryByText(/^0$/)).not.toBeInTheDocument();
    });

    it('reissues the same current request on Retry and renders server-authoritative counters from the successful response', async () => {
        const user = (await import('@testing-library/user-event')).default.setup();

        fetchSpy.mockImplementation(async () => jsonResponse(500, { message: 'Server Error' }));

        render(<AnalyticsPage workspaceId={WORKSPACE_ID} locationId={LOCATION_ID} />);

        const region = await screen.findByRole('region', { name: /metric|report/i });
        const retryButton = await waitFor(() =>
            within(region).getByRole('button', { name: /retry/i }),
        );

        fetchSpy.mockImplementation(async (url: string) => {
            if (String(url) === `${SUMMARY_ENDPOINT}?range=today`) {
                return jsonResponse(200, summaryBody('today', 7, 5));
            }
            throw new Error(`Unhandled fetch: ${String(url)}`);
        });
        fetchSpy.mockClear();

        await user.click(retryButton);

        await waitFor(() =>
            expect(fetchSpy).toHaveBeenCalledWith(`${SUMMARY_ENDPOINT}?range=today`),
        );
        await waitFor(() => expect(within(region).getByText('7')).toBeInTheDocument());
        expect(within(region).getByText('5')).toBeInTheDocument();
    });

    // PLAN-RESTRICTED-402 — sahibinin ekranında görülen kusur.
    //
    // Sunucu 402 döndürüyordu (plan bu yeteneği içermiyor) ve arayüz onu
    // "Analytics failed to load. Please try again." diye gösterip bir
    // Retry düğmesi koyuyordu. Yeniden denemek hiçbir zaman işe yaramaz;
    // ekranda duran o düğme, olmayan bir yolu gösteriyordu.
    it('tells the owner the plan does not include reporting, instead of pretending it broke', async () => {
        const fetchSpy = vi.fn(async () =>
            jsonResponse(402, {
                message: 'Your plan does not include analytics reporting.',
                entitlement: 'analytics.reporting',
            }),
        );
        vi.stubGlobal('fetch', fetchSpy);

        const onNavigateToSection = vi.fn();

        render(
            <AnalyticsPage
                workspaceId={WORKSPACE_ID}
                locationId={LOCATION_ID}
                onNavigateToSection={onNavigateToSection}
            />,
        );

        const region = await screen.findByRole('region', { name: /analytics report/i });

        await waitFor(() => {
            expect(within(region).getByRole('status')).toHaveTextContent(
                /not included in your current plan/i,
            );
        });

        // Bir HATA değil: `role="alert"` yok, "try again" yok.
        expect(within(region).queryByRole('alert')).toBeNull();
        expect(within(region).queryByText(/try again/i)).toBeNull();

        // Ve hiçbir zaman işe yaramayacak bir yenileme düğmesi de yok.
        expect(within(region).queryByRole('button', { name: /retry|refresh/i })).toBeNull();

        // Bunun yerine gerçek bir çıkış yolu var.
        const wayForward = within(region).getByRole('button', { name: /view your plan/i });
        wayForward.click();
        expect(onNavigateToSection).toHaveBeenCalledWith('billing');

        // Veri kaybı korkusu açıkça yanıtlanır.
        expect(within(region).getByRole('status')).toHaveTextContent(/no data is lost/i);

        vi.unstubAllGlobals();
    });
    /**
     * Durum rozeti ANORMAL durumu bildirir, "yüklendi" demez.
     *
     * Önceden başarı hâlinde seçili zaman aralığı ("Today") rozet olarak
     * basılıyordu. O bilgi hemen altındaki `Range` seçicisinde zaten duruyor
     * ve kullanıcının kendi seçtiği şey. Her sayfada böyle bir "her şey yolunda"
     * rozeti bulunması rozetleri okunmayan süse çevirir — ve o noktadan sonra
     * gerçek uyarı da fark edilmez. Bu çiftin ikisi de sınanmalı: sustuğu yer
     * kadar konuştuğu yer de.
     */
    it('başarıda rozet göstermez ama plan kısıtında gösterir', async () => {
        vi.stubGlobal(
            'fetch',
            vi.fn(async () => jsonResponse(200, summaryBody('today', 4, 2))),
        );

        const { unmount } = render(
            <AnalyticsPage workspaceId={WORKSPACE_ID} locationId={LOCATION_ID} />,
        );

        await screen.findByText('4');
        expect(screen.queryByTestId('flowbite-badge')).toBeNull();

        unmount();
        vi.unstubAllGlobals();

        vi.stubGlobal(
            'fetch',
            vi.fn(async () =>
                jsonResponse(402, {
                    message: 'Your plan does not include analytics reporting.',
                    entitlement: 'analytics.reporting',
                }),
            ),
        );

        render(<AnalyticsPage workspaceId={WORKSPACE_ID} locationId={LOCATION_ID} />);

        await waitFor(() => {
            expect(screen.getByTestId('flowbite-badge')).toBeInTheDocument();
        });

        vi.unstubAllGlobals();
    });

    /**
     * Boşluğun sebebi DEĞİŞİNCE çıkış yolu da değişir — `docs/66`.
     *
     * Bu üç test ayrımın kendisini donduruyor. Tek bir "veri yok" cümlesi
     * dördüne birden söylense, üçüne yanlış yol gösterilirdi: yayınlanmamış
     * bir menüsü olan kişiye "QR kodunu yazdır" demek, atlayamayacağı bir
     * adımı atlamasını istemektir.
     */
    describe('boş sonucun sebebi', () => {
        const MENU_TREE = {
            id: 4501,
            workspaceId: WORKSPACE_ID,
            locationId: LOCATION_ID,
            name: 'Ana menü',
            state: 'draft',
            categories: [],
        } as never;

        function mockWith(published: boolean) {
            fetchSpy.mockImplementation(async (url: string) => {
                if (String(url).includes('/publications/current')) {
                    return published
                        ? jsonResponse(200, { id: 12, version: 3, state: 'published' })
                        : jsonResponse(404, {});
                }

                return jsonResponse(200, summaryBody('today', 0, 0));
            });
        }

        it('menü var ama yayınlanmamışsa yayın ekranına götürür', async () => {
            mockWith(false);
            const onNavigateToSection = vi.fn();

            render(
                <AnalyticsPage
                    workspaceId={WORKSPACE_ID}
                    locationId={LOCATION_ID}
                    menuTree={MENU_TREE}
                    onNavigateToSection={onNavigateToSection}
                />,
            );

            const region = await screen.findByRole('region', { name: /metric|report/i });
            const action = await within(region).findByRole('button', {
                name: 'Preview and publish',
            });
            await userEvent.click(action);

            expect(onNavigateToSection).toHaveBeenCalledWith('publication');
        });

        it('yayınlanmış ama hiç taranmamışsa QR ekranına götürür', async () => {
            mockWith(true);
            const onNavigateToSection = vi.fn();

            render(
                <AnalyticsPage
                    workspaceId={WORKSPACE_ID}
                    locationId={LOCATION_ID}
                    menuTree={MENU_TREE}
                    onNavigateToSection={onNavigateToSection}
                />,
            );

            const region = await screen.findByRole('region', { name: /metric|report/i });

            // 30 günlük aralığa geç: "bu aralıkta yok" ile "hiç yok" ayrımı budur.
            await userEvent.selectOptions(screen.getByLabelText(/range/i), '30d');

            const action = await within(region).findByRole('button', { name: 'View QR codes' });
            await userEvent.click(action);

            expect(onNavigateToSection).toHaveBeenCalledWith('qr-codes');
        });

        /**
         * Seçili aralıkta etkinlik yoksa çıkış yolu BU SAYFANIN İÇİNDEDİR.
         * Kullanıcıyı başka bir ekrana göndermek, cevabın burada olduğu bir
         * soruda gereksiz bir yolculuk olurdu.
         */
        it('dar aralıkta boşsa aralığı genişletmeyi önerir', async () => {
            mockWith(true);

            render(
                <AnalyticsPage
                    workspaceId={WORKSPACE_ID}
                    locationId={LOCATION_ID}
                    menuTree={MENU_TREE}
                    onNavigateToSection={vi.fn()}
                />,
            );

            const region = await screen.findByRole('region', { name: /metric|report/i });
            const widen = await within(region).findByRole('button', {
                name: 'Show the last 30 days',
            });
            await userEvent.click(widen);

            expect(screen.getByLabelText(/range/i)).toHaveValue('30d');
        });
    });
});
