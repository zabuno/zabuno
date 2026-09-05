import { describe, expect, it } from 'vitest';
import { render, screen } from '@testing-library/react';
import { QrPrintActionBar } from './QrPrintActionBar';
import type { QrScreenCode } from '../publication/QrTableCardGrid';
import type { QrPrintPlan } from './qrPrintPlan';

/**
 * EYLEM ÇUBUĞUNUN İKİ DÜĞMESİ — sahibin 2026-09-05 ekran görüntüsü.
 *
 * İki bildirim, ikisi de aynı sebebe bakıyor: düğmenin işi kısa ve net
 * olmalı.
 *
 * 1. Sarı düğme "Download Entrance code" yazıyordu. Kodun adı zaten iki
 *    satır YUKARIDA, özet cümlesinin içinde ("Entrance code · PDF"). Düğmeye
 *    tekrar yazmak bilgi eklemiyor, yalnız düğmeyi uzatıyor — ve uzayan
 *    düğme, yanındakini alt satıra itiyor.
 *
 * 2. "Yazdır" ile "İndir" YAN YANA durmalı. İkisi aynı kâğıdın iki farklı
 *    çıkışı; alt alta dizildiklerinde birbirinin devamı gibi okunuyorlar,
 *    oysa bir seçim sunuyorlar.
 *
 * İkincisi birincisiz olmuyordu: uzun etiketle sarılma kaçınılmazdı. Bu
 * yüzden tek pakette.
 *
 * Requirement IDs: QR-PRINT-ACTIONS-SIDE-BY-SIDE-01.
 */
describe('karekod eylem çubuğu', () => {
    const code: QrScreenCode = {
        id: 4021,
        workspaceId: 7,
        locationId: 3,
        menuId: 11,
        token: 'yDeMVVWFnsMcK1wdiru3rP4sqbrhEcf',
        resolverUrl: 'https://zabuno.com/q/yDeMVVWFnsMcK1wdiru3rP4sqbrhEcf',
        tableName: null,
        areaLabel: null,
        destinationType: 'published_menu',
        state: 'active',
    };

    const plan: QrPrintPlan = {
        preset: 'table',
        custom: false,
        size: 'A5',
        landscape: false,
        format: 'pdf',
        scope: 'one',
        areaId: null,
        codeId: 4021,
        theme: 'minimal',
        headline: '',
    };

    function renderBar(selected: readonly QrScreenCode[]) {
        return render(
            <QrPrintActionBar
                workspaceId={7}
                locationId={3}
                plan={plan}
                selected={selected}
                scopeName="Entrance code"
            />,
        );
    }

    // --- QR-PRINT-ACTIONS-SIDE-BY-SIDE-01 ---------------------------------

    it('indirme düğmesi yalnız "Download" der, kodun adını tekrar etmez', () => {
        renderBar([code]);

        const download = screen.getByRole('link', { name: /download/i });

        expect(download.textContent?.trim()).toBe('Download');
        expect(
            download.textContent,
            'Kodun adı özet cümlesinde zaten var; düğmede tekrar etmek bilgi eklemiyor, yalnız düğmeyi uzatıyor.',
        ).not.toMatch(/entrance code/i);
    });

    it('yazdır ve indir aynı satırda durur, alt alta düşmez', () => {
        const { container } = renderBar([code]);

        const print = screen.getByRole('link', { name: /print/i });
        const download = screen.getByRole('link', { name: /download/i });

        const group = print.parentElement;

        expect(group).not.toBeNull();
        expect(download.parentElement, 'İki düğme AYNI kapta olmalı.').toBe(group);

        /*
            `flex-wrap` kabın sarmasına izin verir; sarma, iki düğmeyi alt alta
            düşüren şeyin ta kendisiydi. Etiket kısaldığı için artık sarmaya
            ihtiyaç da yok: "Print" ve "Download" 320 pikselde yan yana sığar.

            Sınıf ölçülüyor çünkü jsdom düzen hesaplamıyor — gerçek sarma
            davranışını burada göremeyiz. Ölçülen şey KARARIN kendisi.
        */
        expect(group!.className).toContain('flex');
        expect(
            group!.className,
            'QR-PRINT-ACTIONS-SIDE-BY-SIDE-01: kap sarabiliyorsa düğmeler yine alt alta düşer.',
        ).not.toContain('flex-wrap');

        expect(container.querySelectorAll('a').length).toBeGreaterThanOrEqual(2);
    });

    /**
     * ÇOK KARTTA "YAZDIR" ÇİZİLMEZ ve bu bozulmamalı.
     *
     * Bileşenin kendi gerekçesi: çok kartlı bir seçimin PDF'i yoktur, çıktı
     * bir ZIP arşividir. Sarmayı kaldırmak tek düğmeli bu durumu
     * etkilememeli — ZIP etiketi uzundur ve yanında kimse yoktur.
     */
    it('çok kartta yalnız arşiv düğmesi vardır', () => {
        renderBar([code, { ...code, id: 4022, token: 'b'.repeat(31) }]);

        expect(screen.queryByRole('link', { name: /print/i })).toBeNull();
        expect(screen.getByRole('link', { name: /zip/i })).toBeInTheDocument();
    });
});
