import { describe, expect, it } from 'vitest';
import { render, screen } from '@testing-library/react';

import { AnalyticsMetricGrid } from './AnalyticsMetricGrid';

/**
 * KPI IZGARASI — kanonik teslim paketi (`DESIGN_SPEC.md` §5 "KPI kartları",
 * `Restoran Paneli v2.dc.html` Insights bölümü).
 *
 * Sahibin yolculuğu: Insights'ı açtığında ilk sorduğu şey "bugün kaç kişi
 * geldi, kaçı menüyü açtı?"dır — ve bu DÖRT sayı BİRLİKTE anlam taşır.
 * Tarama 200, menü açılışı 40 ise sorun QR'da değil menüdedir; ama bu ancak
 * iki sayı YAN YANA okunduğunda görülür.
 *
 * Bugünkü hâli dört kartı ALT ALTA diziyor. Geniş bir ekranda sahibin gözü
 * her sayı için satır başına dönüyor, dördüncü sayı katlamanın altında
 * kalıyor ve karşılaştırma —yani kartların tek sebebi— hiç yapılamıyor.
 *
 * Teslim paketi bu işi `repeat(auto-fit, minmax(140px, 1fr))` ile çözüyor:
 * 320 pikselde tek sütun, yer açıldıkça iki, üç, dört. Kırılma noktası
 * sınıfı yok — ızgara kendi kendine sarıyor.
 */
describe('AnalyticsMetricGrid — AEP KPI ızgarası', () => {
    it('sayaçları alt alta değil, kendiliğinden saran bir ızgarada dizer', () => {
        const { container } = render(
            <AnalyticsMetricGrid
                qrResolveCount={12}
                menuOpenCount={9}
                uniqueVisitorCount={7}
                openRate={0.75}
            />,
        );

        const grid = container.firstElementChild;

        expect(grid?.className).toMatch(/\bgrid\b/);
        expect(grid?.className).toMatch(/auto-fit/);
        expect(grid?.className).toMatch(/minmax/);
        // Yığın DEĞİL: `flex-col` dört sayıyı tek sütuna kilitliyordu.
        expect(grid?.className).not.toMatch(/flex-col/);
    });

    it('320 pikselde de çalışır: kırılma noktası öneki taşımaz', () => {
        const { container } = render(
            <AnalyticsMetricGrid
                qrResolveCount={12}
                menuOpenCount={9}
                uniqueVisitorCount={7}
                openRate={0.75}
            />,
        );

        const grid = container.firstElementChild;

        expect(grid?.className ?? '').not.toMatch(/(^|[\s"'`])(sm|md|lg|xl|2xl):/);
    });

    /**
     * RAKAM METRİK ÖLÇEKTE ve `tabular-nums`.
     *
     * Bu davranış `StatValue` mikro bileşeninden MİRAS gelir; burada
     * dondurulmasının sebebi, ızgara yeniden kurulurken sayaç kartının
     * başka bir gösterimle (ör. düz metin) değiştirilmesinin sessizce
     * geçmesini engellemektir. Ölçek adı okunur, ham piksel yazılmaz.
     */
    it('sayıyı AEP metrik ölçeğinde ve eşit genişlikli rakamlarla çizer', () => {
        render(
            <AnalyticsMetricGrid
                qrResolveCount={12}
                menuOpenCount={9}
                uniqueVisitorCount={7}
                openRate={0.75}
            />,
        );

        const value = screen.getByText('12');

        expect(value.style.fontSize).toBe('var(--aep-text-metric)');
        expect(value.className).toMatch(/tabular-nums/);
    });

    /**
     * DÖRT SAYAÇ, BEŞ DEĞİL — kanonik kaynak
     * (`docs/reference/panel-v3/panel-v3.1.dc.html`, Insights: `kpis`).
     *
     * Açılış oranı beşinci bir kart olarak duruyordu. Oysa oran iki sayının
     * BİLEŞİMİ: menü açılışı bölü tarama. Kendi kartında dururken sahibin
     * gözü, dördü gerçek ölçüm olan bir sırada beşinci bir "sayı" arıyor ve
     * "%70" rakamını bir adet sanabiliyordu. Kaynak onu tam olarak
     * açıkladığı sayının altına koyuyor.
     */
    it('açılış oranını ayrı bir kart olarak değil, menü açılışının alt satırı olarak yazar', () => {
        render(
            <AnalyticsMetricGrid
                qrResolveCount={12}
                menuOpenCount={9}
                uniqueVisitorCount={7}
                openRate={0.75}
            />,
        );

        expect(screen.getByText('75% open rate')).toBeInTheDocument();
        // Çıplak oran rakamı bir sayaç DEĞERİ olarak artık çizilmiyor.
        expect(screen.queryByText('75%')).toBeNull();
    });

    /**
     * Ölçülemeyen oran YAZILMAZ.
     *
     * Tarama yokken "%0 açılış oranı" demek "kimse açmadı" der; oysa doğrusu
     * "kimse taramadı"dır ve ikisi farklı sorunlardır — biri menünün, diğeri
     * karekodun derdi.
     */
    it('oran ölçülemediğinde alt satırı hiç yazmaz', () => {
        render(
            <AnalyticsMetricGrid
                qrResolveCount={0}
                menuOpenCount={0}
                uniqueVisitorCount={0}
                openRate={null}
            />,
        );

        expect(screen.queryByText(/open rate/i)).toBeNull();
    });

    /**
     * "Yaklaşık" kelimesinin SEBEBİ yazılır.
     *
     * Ölçülen şey kişi değil CİHAZDIR: proxy arkasındaki iki misafir tek
     * görünebilir, tarayıcısını değiştiren bir kişi iki. Etiketteki
     * "yaklaşık" bunu ima eder; alt satır söyler.
     */
    it('yaklaşık ziyaretçinin altına neyin sayıldığını yazar', () => {
        render(
            <AnalyticsMetricGrid
                qrResolveCount={12}
                menuOpenCount={9}
                uniqueVisitorCount={7}
                openRate={0.75}
            />,
        );

        expect(screen.getByText('unique devices')).toBeInTheDocument();
    });
});
