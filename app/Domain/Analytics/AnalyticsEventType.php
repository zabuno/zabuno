<?php

declare(strict_types=1);

namespace App\Domain\Analytics;

/**
 * Taksonomi ÖNCE İŞ SORUSUNDAN türetildi (`docs/84`); tersini yapmak,
 * "ölçebildiğimizi ölçüp sonra ona bir anlam aramak" olurdu.
 */
enum AnalyticsEventType: string
{
    /** "Karekodu kaç kişi okuttu?" */
    case QrResolve = 'qr_resolve';

    /** "Menümü kaç kişi açtı?" */
    case MenuOpen = 'menu_open';

    /**
     * "Hangi ürüne bakılıyor?" — menü mühendisliğinin tek girdisi.
     *
     * Bu olayın YOKLUĞU da bir cevaptır: yayındaki ürün listesiyle fark
     * alınınca "hangi ürüne hiç bakılmıyor" çıkar. O yüzden ayrı bir olay
     * yok.
     */
    case ItemView = 'item_view';

    /**
     * "Misafir ne arıyor ama bulamıyor?"
     *
     * Sahibin göremediği tek talep budur: menüde OLMAYAN şeyin talebi.
     */
    case SearchNoResults = 'search_no_results';

    /**
     * "Masadan kaç sipariş geldi?" — `docs/115` §7 S2 (FF-176).
     *
     * MİSAFİR TARAFI ölçümüdür, yani sunucuda yaşar ve GTM'e gitmez
     * (`docs/112` §1, §5): masadaki misafir bir pazarlama konteyneri
     * indirmek zorunda değildir, ve bu veri sahibin ürününün parçasıdır.
     * Taksonomi bir ENUM'dur, serbest dize değil: yazım hatası taşıyan bir
     * olay adı sessizce ikinci bir olay yaratır ve rapor ikiye bölünür.
     *
     * ÜRÜN ADI VE FİYAT BASILMAZ. Olay yalnız "bu menüden, bu şubede, bu
     * anda bir sipariş gönderildi" der. Ne sipariş edildiği zaten
     * `order_items` içindedir ve orası sahibin verisidir; ölçüm tablosuna
     * ikinci bir kopya koymak, aynı gerçeği iki yerde tutup bir gün
     * ayrıştırmak olurdu.
     *
     * Bu olay bir HUNİNİN son basamağıdır: `qr_resolve` → `menu_open` →
     * `item_view` → `order_submitted`. Sipariş sayısını yalnız `orders`
     * tablosundan saymak da mümkündü ve yetmezdi — huninin diğer üç
     * basamağı bu tablodadır ve dönüşüm oranı ancak aynı yerde durduklarında
     * hesaplanabilir.
     */
    case OrderSubmitted = 'order_submitted';
}
