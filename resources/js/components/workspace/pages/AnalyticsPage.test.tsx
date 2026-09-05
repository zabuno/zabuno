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

function summaryBody(
    range: string,
    qrResolveCount: number,
    menuOpenCount: number,
    extra: Record<string, unknown> = {},
) {
    return {
        range,
        qrResolveCount,
        menuOpenCount,
        // MVP metrikleri (docs/68). Varsayılanlar sıfır/boş: ölçüm var ama
        // bu senaryoda veri yok.
        uniqueVisitorCount: 0,
        openRate: qrResolveCount === 0 ? null : menuOpenCount / qrResolveCount,
        locations: [],
        qrCodes: [],
        generatedAt: '2026-08-22T09:00:00.000Z',
        ...extra,
    };
}

/**
 * ZAMAN SERİSİ GÖVDESİ — `docs/109` §1, §6.5.
 *
 * Insights ekranının grafikleri artık ayrı bir uçtan besleniyor: aralık
 * TOPLAMI bir haftanın şeklini gizliyordu ve "hangi gün çöktü", "öğle mi
 * akşam mı", "geçen haftaya göre nasıl", "hangi şube çekiyor" soruları
 * üründe hiç cevaplanamıyordu.
 */
function timeSeriesBody(extra: Record<string, unknown> = {}) {
    return {
        range: 'today',
        state: 'ready',
        threshold: 5,
        observedVisitors: 9,
        timezone: 'Europe/Istanbul',
        buckets: [
            { date: '2026-08-31', qrResolveCount: 12, menuOpenCount: 9 },
            { date: '2026-09-01', qrResolveCount: 3, menuOpenCount: 3 },
        ],
        comparison: {
            basis: 'previous_period',
            currentQrResolveCount: 15,
            previousQrResolveCount: 12,
            deltaRatio: 0.25,
            previousStart: '2026-08-22T09:00:00+00:00',
            previousEnd: '2026-08-29T09:00:00+00:00',
        },
        hourly: [{ weekday: 2, hour: 13, qrResolveCount: 12 }],
        suppressedHourCells: 0,
        locationShare: [
            { id: 1, label: 'Kadıköy', qrResolveCount: 12, sharePercent: 80 },
            { id: 2, label: 'Beşiktaş', qrResolveCount: 3, sharePercent: 20 },
        ],
        locationShareScope: 'workspace',
        generatedAt: '2026-09-05T09:00:00.000Z',
        ...extra,
    };
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

        /*
            İddia SIRAYA değil ADRESE bakar.

            Sayfa `docs/84`'ten beri menü mühendisliği raporunu da çekiyor;
            "ilk çağrı" varsayımı testin niyetiyle ilgisiz bir kırılganlıktı.
            Ölçülmek istenen şey, ÖZET ucunun doğru aralıkla çağrılması.
        */
        const urls = fetchSpy.mock.calls.map((call) => String(call[0]));
        expect(urls).toContain(`${SUMMARY_ENDPOINT}?range=today`);
    });

    /**
     * ARALIK SEÇİCİSİ AÇILIR LİSTE DEĞİL, SEGMENT — `docs/109` §1.
     *
     * Kaynağın Insights başlığında üç seçenek de aynı anda görünür ve seçili
     * olan ekranda durur. Açılır liste üçünü de GİZLİYORDU: sahip "30 gün"e
     * bakmak için önce listeyi açmak, sonra seçmek zorundaydı — iki dokunuş
     * ve arada kapanan bir katman. Bu ekranın en sık yapılan işi tam olarak
     * aralıklar arasında gidip gelmek.
     *
     * Rol `radiogroup`tur, buton dizisi değil: görünüşü ne olursa olsun anlamı
     * tek seçimdir ve ekran okuyucu kullanıcısı "3 seçenekten 2." bilgisini
     * ancak bu rolle alır.
     */
    it('offers Today, Last 7 days, and Last 30 days range options', () => {
        render(<AnalyticsPage workspaceId={WORKSPACE_ID} locationId={LOCATION_ID} />);

        const rangeControl = screen.getByRole('radiogroup', { name: /range/i });
        const optionLabels = within(rangeControl)
            .getAllByRole('radio')
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

    /*
        SAYACIN ADI SAHİBİN KELİMESİ (kanonik kaynak, Insights ekranı).

        Kartlar "QR Resolve" ve "Confirmed Menu Open" yazıyordu; bunlar
        `docs/12`'nin ölçüm terimleri. Ayrım korunuyor — iki sayı hâlâ ayrı
        ölçülüyor — ama sahibi ekranda kaynağın kelimelerini okuyor.
    */
    it('renders real nonzero scan and menu-open counts in accessible metric cards', async () => {
        render(<AnalyticsPage workspaceId={WORKSPACE_ID} locationId={LOCATION_ID} />);

        const region = await screen.findByRole('region', { name: /metric|report/i });

        await waitFor(() => expect(within(region).getByText('3')).toBeInTheDocument());
        expect(within(region).getByText('2')).toBeInTheDocument();
        expect(within(region).getByText('Scans')).toBeInTheDocument();
        expect(within(region).getByText('Menu opens')).toBeInTheDocument();
        // Ölçümün ledger terimleri artık kullanıcıya çıkmıyor.
        expect(within(region).queryByText(/qr resolve/i)).toBeNull();
        expect(within(region).queryByText(/confirmed menu open/i)).toBeNull();
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

        // Segment düğmesine BASILIR; açılır listedeki gibi seçilmez.
        await user.click(screen.getByRole('radio', { name: /last 7 days/i }));

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

        /*
            İddia ÖZET ucunu sayar, sayfanın toplam isteğini değil.

            "Tam bir istek" yazıldığı gün sayfanın tek veri kaynağı vardı;
            ölçülmek istenen şey, tazelemenin özeti İKİ KEZ istememesiydi.
            `docs/84` ile sayfa menü mühendisliği raporunu da tazeliyor —
            sahip "Tazele"ye bastığında sayfanın tamamı tazelenmeli.
        */
        expect(
            fetchSpy.mock.calls.filter((call) => String(call[0]).includes('/analytics/summary')),
        ).toHaveLength(1);
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
            await userEvent.click(screen.getByRole('radio', { name: /last 30 days/i }));

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

            // Segmentte seçili olan, `aria-checked` ile bildirilir: seçim
            // yalnız RENKLE anlatılsaydı yüksek kontrast modunda kaybolurdu.
            expect(screen.getByRole('radio', { name: /last 30 days/i })).toHaveAttribute(
                'aria-checked',
                'true',
            );
        });
    });

    /**
     * MVP metrikleri — `docs/68`.
     *
     * Toplam sayı, iki şubesi olan bir işletmede birinin hiç taranmadığını
     * gizler; kırılım o gizlenen şeyi görünür kılar.
     */
    describe('MVP metrikleri', () => {
        it('yaklaşık benzersiz ziyaretçiyi ve açılma oranını gösterir', async () => {
            fetchSpy.mockImplementation(async () =>
                jsonResponse(
                    200,
                    summaryBody('today', 10, 7, { uniqueVisitorCount: 6, openRate: 0.7 }),
                ),
            );

            render(
                <AnalyticsPage
                    workspaceId={WORKSPACE_ID}
                    locationId={LOCATION_ID}
                    onNavigateToSection={vi.fn()}
                />,
            );

            const region = await screen.findByRole('region', { name: /metric|report/i });

            await waitFor(() => {
                expect(within(region).getByText('6')).toBeInTheDocument();
            });
            expect(within(region).getByText(/approx\. unique visitors/i)).toBeInTheDocument();
            /*
                AÇILIŞ ORANI ARTIK KENDİ KARTI DEĞİL, MENÜ AÇILIŞI KARTININ
                ALT SATIRI (kaynağın DÖRT sayaçlı ızgarası).

                Oran iki sayının bileşimi; üçüncü bir ölçüm değil. Beşinci
                bir kart olarak dururken sahibin gözü, dördü gerçek ölçüm
                olan bir sırada beşinci bir "sayı" arıyordu. Sayı aynı sayı,
                yeri değişti: tam olarak açıkladığı sayının altında.
            */
            expect(within(region).getByText('70% open rate')).toBeInTheDocument();
            expect(within(region).queryByText('70%')).toBeNull();
        });

        /**
         * ŞUBE KIRILIMI ARTIK BİR TABLO DEĞİL, BİR HALKA — `docs/109` §1.
         *
         * Kaynağın Insights ekranında şube kırılımı "By location" başlıklı bir
         * tablo değil, bir PAY HALKASIDIR ve verisi zaman serisi ucundan
         * gelir. Sebebi şu: sahibin sorduğu şey "Beşiktaş'ta kaç tarama var?"
         * değil, "Beşiktaş markanın ne kadarı?"dır — ve bu, iki satırlık bir
         * tablodan okunmaz. Halka her zaman markanın TAMAMINI gösterir, tek
         * şubeye süzülmüş bir ekranda bile.
         */
        it('birden fazla şube varsa pay halkasını çizer', async () => {
            fetchSpy.mockImplementation(async (url: string) => {
                if (String(url).includes('/analytics/time-series')) {
                    return jsonResponse(200, timeSeriesBody());
                }

                return jsonResponse(200, summaryBody('today', 15, 12));
            });

            render(
                <AnalyticsPage
                    workspaceId={WORKSPACE_ID}
                    locationId={LOCATION_ID}
                    onNavigateToSection={vi.fn()}
                />,
            );

            const region = await screen.findByRole('region', { name: /metric|report/i });

            expect(
                await within(region).findByRole('heading', { name: 'Share by location' }),
            ).toBeInTheDocument();
            expect(within(region).getByText('Beşiktaş')).toBeInTheDocument();
        });

        /**
         * Tek şubeli bir işletmede pay %100'dür: halka, üstündeki toplamın
         * kelimesi kelimesine tekrarıdır ve ekranda yalnız yer kaplar.
         */
        it('tek şube varsa pay halkasını çizmez', async () => {
            fetchSpy.mockImplementation(async (url: string) => {
                if (String(url).includes('/analytics/time-series')) {
                    return jsonResponse(
                        200,
                        timeSeriesBody({
                            locationShare: [
                                { id: 1, label: 'Kadıköy', qrResolveCount: 12, sharePercent: 100 },
                            ],
                        }),
                    );
                }

                return jsonResponse(200, summaryBody('today', 12, 9));
            });

            render(
                <AnalyticsPage
                    workspaceId={WORKSPACE_ID}
                    locationId={LOCATION_ID}
                    onNavigateToSection={vi.fn()}
                />,
            );

            const region = await screen.findByRole('region', { name: /metric|report/i });
            await within(region).findByText('12');

            expect(within(region).queryByRole('heading', { name: 'Share by location' })).toBeNull();
        });
    });

    /**
     * INSIGHTS'IN AEP DÜZENİ — kanonik teslim paketi
     * (`Restoran Paneli v2.dc.html` Insights bölümü, `DESIGN_SPEC.md` §5).
     *
     * Sahibin yolculuğu: Insights'ı açar, üstte "Bugün / 7 gün / 30 gün"
     * seçer ve altındaki her şeyin o aralığa göre değiştiğini görür. Aralık
     * seçicisi sayfa başlığının YANINDA durur; çünkü o, bir bölgenin değil
     * SAYFANIN tamamının kapsamıdır.
     *
     * Ve teslim paketinde kartın içinde kart yoktur: bölgeler doğrudan
     * zeminin üstünde durur.
     */
    describe('AEP düzeni', () => {
        function cardAncestorsOf(element: HTMLElement): HTMLElement[] {
            const cards: HTMLElement[] = [];
            let current = element.parentElement;

            while (current) {
                if (
                    current.classList.contains('rounded-[var(--radius-lg)]') &&
                    current.classList.contains('bg-[var(--color-surface)]')
                ) {
                    cards.push(current);
                }
                current = current.parentElement;
            }

            return cards;
        }

        it('aralık seçicisini sayfa başlığının yanına koyar', async () => {
            render(<AnalyticsPage workspaceId={WORKSPACE_ID} locationId={LOCATION_ID} />);

            const heading = screen.getByRole('heading', { level: 1, name: /analytics/i });
            const rangeControl = screen.getByRole('radiogroup', { name: /range/i });

            /*
                Seçici, başlık satırının içinde olmalı. Gövdenin ilk satırında
                duran bir "Range" alanı, altındaki tek bir bölgeye aitmiş gibi
                okunuyordu — oysa aralık, sayfadaki HER sayının kapsamıdır.
            */
            const headerRow = heading.closest('.justify-between');

            expect(headerRow).not.toBeNull();
            expect(headerRow?.contains(rangeControl)).toBe(true);
        });

        it('kartın içine kart çizmez', async () => {
            fetchSpy.mockImplementation(async (url: string) => {
                if (String(url).includes('/analytics/time-series')) {
                    return jsonResponse(200, timeSeriesBody());
                }

                if (String(url).includes('/analytics/summary')) {
                    return jsonResponse(200, summaryBody('today', 15, 12));
                }

                return jsonResponse(200, {});
            });

            render(
                <AnalyticsPage
                    workspaceId={WORKSPACE_ID}
                    locationId={LOCATION_ID}
                    onNavigateToSection={vi.fn()}
                />,
            );

            // Grafik kartı ARTIK bölgenin ilk kartıdır; kırılım tablosunun
            // yerini o aldı (`docs/109` §1).
            const breakdownHeading = await screen.findByRole('heading', {
                name: 'Scans and menu opens',
            });
            const breakdownCard = breakdownHeading.closest('section');

            expect(breakdownCard).not.toBeNull();
            /*
                Kart sınırı ANLAM taşır: "burada bir kaynak, bir liste, bir
                bölge var". İç içe iki kart aynı zemin ve aynı kenarlıkla
                çizilir; sahip iki çizgi görür ama hiçbiri ona yeni bir şey
                söylemez — yalnız içerik daralır ve sayfa dar ekranda iki kat
                daha çok yatay dolgu harcar.
            */
            expect(cardAncestorsOf(breakdownCard as HTMLElement)).toHaveLength(0);
        });
    });
});
