import { describe, expect, it } from 'vitest';
import { render, screen, within } from '@testing-library/react';

import { TrendChart } from './TrendChart';

/**
 * ÇUBUK + ÇİZGİ — `docs/109` §1 (Insights) ve §6.5.
 *
 * Sahibin yolculuğu: "son 7 günde 214 tarama" cümlesi bir haftanın şeklini
 * gizler. Salı gününün sıfır olduğunu, cumartesinin haftanın yarısını
 * taşıdığını o sayı söylemez — ve sahip menüyü, personeli, saatleri o şekle
 * göre değiştirir.
 *
 * Kaynak bu grafiği ECharts ile çiziyor. Depo ECharts'ı EKLEMİYOR ve sebebi
 * ölçülü (`docs/109` §6.5): paket ~300 KB gzip, bütçe ise giriş başına
 * 200 KB (`DS-BUNDLE-BUDGET-07`). Tek bir ekran için bütçeyi ikiye
 * katlamak, telefonla bakan bir restoran sahibinin HER sayfa açılışını
 * yavaşlatırdı. Aynı düzen elle yazılmış SVG ile çizilir.
 *
 * ERİŞİLEBİLİRLİK BU BİLEŞENİN SÖZLEŞMESİDİR.
 *
 * Bir SVG, ekran okuyucu için bir resimdir: içindeki dikdörtgenlerin
 * yükseklikleri okunmaz. Grafiği "gören" biri sayıya ulaşırken görmeyen biri
 * hiçbir şeye ulaşamazsa, ürünün bir kısmı o kullanıcı için YOKTUR. Bu
 * yüzden her grafik, aynı sayıları taşıyan bir TABLO ile birlikte doğar —
 * sonradan eklenen bir "erişilebilirlik iyileştirmesi" olarak değil.
 */
const POINTS = [
    { label: '29 Ağu', primary: 0, secondary: 0 },
    { label: '30 Ağu', primary: 12, secondary: 9 },
    { label: '31 Ağu', primary: 31, secondary: 25 },
];

function renderChart(points = POINTS) {
    return render(
        <TrendChart
            points={points}
            primaryLabel="Taramalar"
            secondaryLabel="Menü açılışları"
            columnLabel="Gün"
            description="Son 7 günde tarama ve menü açılışı"
        />,
    );
}

describe('TrendChart — elle yazılmış SVG çubuk+çizgi', () => {
    it('grafiği bir figure olarak adlandırır', () => {
        renderChart();

        expect(
            screen.getByRole('figure', { name: 'Son 7 günde tarama ve menü açılışı' }),
        ).toBeInTheDocument();
    });

    it('aynı sayıları taşıyan bir tablo da çizer', () => {
        renderChart();

        const table = screen.getByRole('table');

        // Sütunlar: gün, birincil seri, ikincil seri.
        expect(within(table).getByRole('columnheader', { name: 'Gün' })).toBeInTheDocument();
        expect(within(table).getByRole('columnheader', { name: 'Taramalar' })).toBeInTheDocument();
        expect(
            within(table).getByRole('columnheader', { name: 'Menü açılışları' }),
        ).toBeInTheDocument();

        // Başlık satırı dahil dört satır: üç veri noktası eksiksiz.
        expect(within(table).getAllByRole('row')).toHaveLength(POINTS.length + 1);

        const zeroDay = within(table).getByRole('row', { name: /29 Ağu/ });

        /*
            SIFIR GÜN DE TABLODA DURUR.

            Grafikte sıfır bir boşluktur; tabloda ise açıkça "0"dır. Boş
            günü listeden düşürmek, sahibe "o gün ölçülmedi" dedirtirdi —
            oysa ölçüldü ve sonuç sıfırdı. İkisi farklı şeylerdir.
        */
        expect(within(zeroDay).getAllByRole('cell')[0]).toHaveTextContent('0');
    });

    it('çizimi ekran okuyucudan gizler: sayı tablodan okunur, resimden değil', () => {
        const { container } = renderChart();

        const svg = container.querySelector('svg');

        expect(svg).not.toBeNull();
        expect(svg?.getAttribute('aria-hidden')).toBe('true');
        // Ölçek dışarıdan gelir: viewBox olmadan SVG 320 pikselde taşar.
        expect(svg?.getAttribute('viewBox')).toBeTruthy();
    });

    it('en yüksek çubuk çizim alanını doldurur, sıfır çubuk hiç yükselmez', () => {
        const { container } = renderChart();

        const bars = [...container.querySelectorAll('[data-role="bar"]')];

        expect(bars).toHaveLength(POINTS.length);

        const heights = bars.map((bar) => Number(bar.getAttribute('height')));

        expect(heights[0]).toBe(0);
        expect(heights[2]).toBeGreaterThan(heights[1]);
    });

    it('tek bir veri noktası varken de çizer', () => {
        // Yeni açılmış bir restoranın ilk günü tek noktadır. Bir bölme
        // hatası yüzünden çizilmemesi, ürünün ilk gününde boş görünmesi
        // demekti.
        renderChart([{ label: 'Bugün', primary: 4, secondary: 3 }]);

        expect(screen.getByRole('figure')).toBeInTheDocument();
        expect(screen.getAllByRole('row')).toHaveLength(2);
    });

    it('veri yoksa hiç çizilmez', () => {
        const { container } = renderChart([]);

        // Boşluğun ne anlama geldiğini SAYFA anlatır (`docs/66`): dört ayrı
        // boşluk durumu var ve bir grafik hangisinde olduğunu bilemez.
        expect(container).toBeEmptyDOMElement();
    });

    it('320 pikselde tek sütun kalır: kırılma noktası öneki taşımaz', () => {
        const { container } = renderChart();

        expect(container.innerHTML).not.toMatch(/(^|[\s"'`])(sm|md|lg|xl|2xl):/);
    });
});
