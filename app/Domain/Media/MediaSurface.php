<?php

declare(strict_types=1);

namespace App\Domain\Media;

/**
 * Bir slotun hangi ÜRÜN YÜZEYİNE ait olduğu.
 *
 * Neden gerekli: restoran sahibi görsel yüklerken açılır menüde "Pricing",
 * "Features" ve "Testimonial" görüyordu. Bunlar Zabuno'nun KENDİ tanıtım
 * sitesinin slotları; restoranın menüsüyle hiçbir ilgisi yok.
 *
 * `docs/50`'nin "3 Neden" kapısı bunu sorar: bu kullanıcı neden görüyor?
 * Cevabı olmayan öğe başka yüzeye taşınır.
 */
enum MediaSurface: string
{
    /** Restoran paneli — menü, marka, QR. */
    case Menu = 'menu';

    /** Zabuno'nun kendi tanıtım sitesi. */
    case Marketing = 'marketing';
}
