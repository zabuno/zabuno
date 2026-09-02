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
}
