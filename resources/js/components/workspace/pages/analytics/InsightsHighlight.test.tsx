import { describe, expect, it, vi } from 'vitest';
import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';

import { InsightsHighlight } from './InsightsHighlight';
import type { AnalyticsTimeSeries } from './useAnalyticsTimeSeries';
import type { MenuEngineeringReport } from './MenuEngineeringRegion';

/**
 * "BU 7 GÜNDE NE OLDU?" — `docs/109` §1 (Insights) ve §6.1.
 *
 * Kaynak bu kartı "AI yorumu" diye adlandırıyor ama içindeki üç cümlenin
 * üçü de GERÇEK ÖLÇÜMDEN doğuyor: arama kayıtları, saat kırılımı ve ürün
 * görüntülenmesi. Yani bunlar "AI" değil, ölçümden çıkan gözlemler —
 * sağlayıcı bağlı olmasa da üretilebilirler.
 *
 * Kartın değeri şu: sahip grafiklere bakmadan önce üç satırda ne olduğunu
 * okur. Grafik "ne oldu"yu gösterir, bu kart "ne yapmalı"ya bir adım atar.
 *
 * KAYNAĞIN DEĞİŞMEZ KURALI: "Öneri yapar, sen onaylarsın. Onaysız hiçbir
 * şey değişmez." Bu yüzden düğmeler bir şeyi UYGULAMAZ; sahibi kararı
 * vereceği ekrana götürür.
 */
const SERIES: AnalyticsTimeSeries = {
    state: 'ready',
    threshold: 5,
    observedVisitors: 12,
    timezone: 'Europe/Istanbul',
    buckets: [],
    comparison: {
        basis: 'previous_period',
        currentQrResolveCount: 43,
        previousQrResolveCount: 34,
        deltaRatio: 0.2647,
        previousStart: '2026-08-22T09:00:00+00:00',
        previousEnd: '2026-08-29T09:00:00+00:00',
    },
    hourly: [
        { weekday: 2, hour: 9, qrResolveCount: 4 },
        { weekday: 6, hour: 13, qrResolveCount: 30 },
    ],
    suppressedHourCells: 0,
    locationShare: [],
    locationShareScope: 'workspace',
};

const REPORT: MenuEngineeringReport = {
    state: 'ready',
    threshold: 5,
    observedViewers: 12,
    mostViewed: [],
    neverViewed: [
        { menuItemId: 3, productName: 'Tavuk Şiş', categoryName: 'Kebaplar', viewers: 0 },
    ],
    searchesWithNoResults: [
        { term: 'vejetaryen', searches: 14 },
        { term: 'glutensiz pide', searches: 9 },
    ],
};

describe('InsightsHighlight', () => {
    it('geçen döneme göre değişimi yüzdeyle söyler', () => {
        render(<InsightsHighlight series={SERIES} report={REPORT} />);

        expect(screen.getByText(/up 26% on the period before/i)).toBeInTheDocument();
    });

    it('önceki dönem boşken yüzde UYDURMAZ', () => {
        /*
            Sıfırdan yüzde artış yoktur: bölen sıfırdır. "%100 arttı" demek
            matematiksel olarak uydurmadır ve sahibin bir sonraki kararına
            temel olur.
        */
        render(
            <InsightsHighlight
                series={{
                    ...SERIES,
                    comparison: {
                        ...SERIES.comparison!,
                        previousQrResolveCount: 0,
                        deltaRatio: null,
                    },
                }}
                report={REPORT}
            />,
        );

        expect(screen.getByText(/nothing to compare/i)).toBeInTheDocument();
        expect(screen.queryByText(/%/)).toBeNull();
    });

    it('en yoğun günü ve saati söyler', () => {
        render(<InsightsHighlight series={SERIES} report={REPORT} />);

        expect(screen.getByText(/sat around 13:00 was the busiest slot/i)).toBeInTheDocument();
    });

    it('aranıp bulunamayan ilk terimi ve kaç kez arandığını söyler', () => {
        render(<InsightsHighlight series={SERIES} report={REPORT} />);

        expect(screen.getByText(/“vejetaryen” was searched 14 times/i)).toBeInTheDocument();
    });

    it('iki eylemi de gerçek bir ekrana bağlar', async () => {
        const user = userEvent.setup();
        const navigate = vi.fn();

        render(
            <InsightsHighlight series={SERIES} report={REPORT} onNavigateToSection={navigate} />,
        );

        /*
            Kaynağın kuralı: "Öneri yapar, sen onaylarsın." Düğme hiçbir şeyi
            kendiliğinden uygulamaz — sahibi kararı vereceği ekrana götürür.
            Hiçbir şey yapmayan bir düğme ise ekrandaki en pahalı yalandır.
        */
        await user.click(screen.getByRole('button', { name: /add “vejetaryen” to the menu/i }));
        expect(navigate).toHaveBeenCalledWith('menu');

        await user.click(screen.getByRole('button', { name: /review the menu/i }));
        expect(navigate).toHaveBeenCalledTimes(2);
    });

    it('gidilecek bir yer yoksa düğmeyi HİÇ çizmez', () => {
        // `onNavigateToSection` verilmediğinde basıldığında hiçbir şey
        // yapmayacak bir düğme çizmek, kullanıcıya olmayan bir yol
        // göstermektir.
        render(<InsightsHighlight series={SERIES} report={REPORT} />);

        expect(screen.queryByRole('button')).toBeNull();
    });

    it('söyleyecek bir şey yoksa kart hiç çizilmez', () => {
        const { container } = render(
            <InsightsHighlight
                series={{ ...SERIES, comparison: null, hourly: [] }}
                report={{
                    ...REPORT,
                    neverViewed: [],
                    searchesWithNoResults: [],
                }}
            />,
        );

        // Boş bir "ne oldu?" kartı, her açılışta okunup hiçbir şey
        // söylemeyen bir çerçeveye dönüşür ve altındaki gerçek bilgiyi
        // aşağı iter.
        expect(container).toBeEmptyDOMElement();
    });
});
