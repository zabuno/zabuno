<?php

declare(strict_types=1);

namespace App\Application\QrDestination\Dto;

/**
 * Basılacak TEK KART — `docs/104` Döngü 8.
 *
 * PDF çıktısı bugüne kadar A4'ün ortasına tek bir çıplak kare koyuyordu: masa
 * adı yok, restoran adı yok, "menü için okutun" yok, kesme çizgisi yok ve
 * sayfada tek kod. 40 masa = 40 ayrı A4, her biri %97 beyaz ve baskıdan sonra
 * birbirinden ayırt edilemez. Sahip onları masalara dağıtırken hangisinin
 * hangi masa olduğunu bilemez — yani ürünün asıl çıktısı kullanılamaz.
 */
final class QrPrintCard
{
    public function __construct(
        /** Kodun PNG baytları — istemcide üretilmez, hiçbir zaman. */
        public readonly string $pngBytes,
        /** "T12" ya da masaya bağlı değilse "Giriş kodu". */
        public readonly string $title,
        /** "Bahçe" gibi alan etiketi; yoksa boş. */
        public readonly string $subtitle,
    ) {}
}
