import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { render, screen, waitFor, within } from '@testing-library/react';

import { AnalyticsTimeSeriesRegion } from './AnalyticsTimeSeriesRegion';

/**
 * INSIGHTS'IN GRAFİK BÖLGESİ — `docs/109` §1 ve §6.5.
 *
 * Sahibin yolculuğu: cumartesi akşamı kasanın başında telefonu açıyor.
 * Bilmek istediği "214" değil — bu hafta geçen haftadan iyi mi, hangi gün
 * çöktü, öğle mi akşam mı yoğun, Kadıköy mü Beşiktaş mı çekiyor. Dördü de
 * ekranda yoktu ve arkasındaki veri de üretilemiyordu.
 *
 * Bu test, bölgenin GERÇEK uca bağlandığını ve ondan gelen dört şeyi de
 * çizdiğini dondurur. Uydurma yok: seri gelmeden hiçbir grafik çizilmez.
 */
const WORKSPACE_ID = 41;
const LOCATION_ID = 907;

function jsonResponse(status: number, body: unknown): Response {
    return {
        headers: new Headers(),
        ok: status >= 200 && status < 300,
        status,
        json: async () => body,
    } as Response;
}

function readyBody(overrides: Record<string, unknown> = {}) {
    return {
        range: '7d',
        state: 'ready',
        threshold: 5,
        observedVisitors: 9,
        timezone: 'Europe/Istanbul',
        buckets: [
            { date: '2026-08-30', qrResolveCount: 0, menuOpenCount: 0 },
            { date: '2026-08-31', qrResolveCount: 12, menuOpenCount: 9 },
            { date: '2026-09-01', qrResolveCount: 31, menuOpenCount: 25 },
        ],
        comparison: {
            basis: 'previous_period',
            currentQrResolveCount: 43,
            previousQrResolveCount: 34,
            deltaRatio: 0.2647,
            previousStart: '2026-08-22T09:00:00+00:00',
            previousEnd: '2026-08-29T09:00:00+00:00',
        },
        hourly: [
            { weekday: 2, hour: 13, qrResolveCount: 30 },
            { weekday: 6, hour: 20, qrResolveCount: 11 },
        ],
        suppressedHourCells: 2,
        locationShare: [
            { id: 1, label: 'Kadıköy', qrResolveCount: 43, sharePercent: 84.31 },
            { id: 2, label: 'Beşiktaş', qrResolveCount: 8, sharePercent: 15.69 },
        ],
        locationShareScope: 'workspace',
        generatedAt: '2026-09-05T09:00:00+00:00',
        ...overrides,
    };
}

function renderRegion() {
    return render(
        <AnalyticsTimeSeriesRegion
            workspaceId={WORKSPACE_ID}
            locationId={LOCATION_ID}
            range="7d"
        />,
    );
}

describe('AnalyticsTimeSeriesRegion', () => {
    beforeEach(() => {
        vi.restoreAllMocks();
    });

    afterEach(() => {
        vi.restoreAllMocks();
    });

    it('gerçek zaman serisi ucunu seçili şube ve aralıkla çağırır', async () => {
        const fetchMock = vi
            .spyOn(globalThis, 'fetch')
            .mockResolvedValue(jsonResponse(200, readyBody()));

        renderRegion();

        await waitFor(() => {
            expect(fetchMock).toHaveBeenCalled();
        });

        expect(String(fetchMock.mock.calls[0][0])).toBe(
            `/api/workspaces/${WORKSPACE_ID}/brand/locations/${LOCATION_ID}/analytics/time-series?range=7d`,
        );
    });

    it('çubuk+çizgi, ısı haritası ve şube halkasını çizer', async () => {
        vi.spyOn(globalThis, 'fetch').mockResolvedValue(jsonResponse(200, readyBody()));

        renderRegion();

        // Üç grafik de FIGURE'dür ve adı vardır: ekran okuyucu kullanıcısı
        // landmark listesinden hangisine baktığını bilmeli.
        const figures = await screen.findAllByRole('figure');

        expect(figures.length).toBe(3);

        /*
            Her grafiğin METİN KARŞILIĞI bulunur. Bir SVG ekran okuyucu için
            resimdir; grafiği gören biri sayıya ulaşırken görmeyen biri
            hiçbir şeye ulaşamazsa, ürünün bir kısmı o kullanıcı için yoktur.
        */
        const tables = screen.getAllByRole('table');

        expect(tables.length).toBeGreaterThanOrEqual(2);

        const trend = tables[0];

        expect(within(trend).getByRole('row', { name: /12\s*9/ })).toBeInTheDocument();
    });

    it('gizlenen saat hücrelerini SESSİZCE düşürmez, sayısını söyler', async () => {
        vi.spyOn(globalThis, 'fetch').mockResolvedValue(jsonResponse(200, readyBody()));

        renderRegion();

        /*
            Ekran "o saatte kimse yoktu" derse bu yanlıştır: geldi, yalnız
            tek kişiydi ve sayısı yayımlanamaz. Gizlemenin kendisi kadar,
            gizlendiğini söylemek de gerekir.
        */
        expect(await screen.findByText(/2 hour slots are not shown/i)).toBeInTheDocument();
    });

    it('şube payının markanın tamamından okunduğunu yazar', async () => {
        vi.spyOn(globalThis, 'fetch').mockResolvedValue(jsonResponse(200, readyBody()));

        renderRegion();

        // Süzülmüş bir ekranda pay halkası hâlâ markanın tamamını gösterir;
        // bunu söylemezsek sahip halkayı seçili şubenin kırılımı sanar.
        expect(
            await screen.findByText(/across your whole brand, not only the selected location/i),
        ).toBeInTheDocument();
    });

    it('tek şubeli markada halkayı hiç çizmez', async () => {
        vi.spyOn(globalThis, 'fetch').mockResolvedValue(
            jsonResponse(
                200,
                readyBody({
                    locationShare: [
                        { id: 1, label: 'Kadıköy', qrResolveCount: 43, sharePercent: 100 },
                    ],
                }),
            ),
        );

        renderRegion();

        // Tek dilimli bir halka her zaman %100'dür: üstündeki toplamın
        // kelimesi kelimesine tekrarı ve ekranda yalnız yer kaplar.
        await waitFor(() => {
            expect(screen.getAllByRole('figure')).toHaveLength(2);
        });
    });

    it('eşiğin altında hiçbir grafik çizmez, sebebini ve eşiği yazar', async () => {
        vi.spyOn(globalThis, 'fetch').mockResolvedValue(
            jsonResponse(
                200,
                readyBody({
                    state: 'not_enough_data',
                    observedVisitors: 2,
                    buckets: [],
                    hourly: [],
                    locationShare: [],
                    comparison: null,
                    suppressedHourCells: 0,
                }),
            ),
        );

        renderRegion();

        expect(await screen.findByText(/2 of 5/)).toBeInTheDocument();
        expect(screen.queryByRole('figure')).not.toBeInTheDocument();
    });

    it('sunucu hata verdiğinde uydurma grafik çizmez', async () => {
        vi.spyOn(globalThis, 'fetch').mockResolvedValue(jsonResponse(500, {}));

        renderRegion();

        expect(await screen.findByRole('alert')).toHaveTextContent(/charts could not be loaded/i);
        expect(screen.queryByRole('figure')).not.toBeInTheDocument();
    });

    it('320 pikselde tek sütun kalır: kırılma noktası öneki taşımaz', async () => {
        vi.spyOn(globalThis, 'fetch').mockResolvedValue(jsonResponse(200, readyBody()));

        const { container } = renderRegion();

        await screen.findAllByRole('figure');

        expect(container.innerHTML).not.toMatch(/(^|[\s"'`])(sm|md|lg|xl|2xl):/);
    });
});
