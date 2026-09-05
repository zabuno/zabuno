<?php

declare(strict_types=1);

namespace App\Support\Localization;

/**
 * Dil değiştiricideki TEK BİR SATIR — `docs/120` §5.
 *
 * Bileşen bir liste çizer; hangi listeyi çizeceğine sunucu karar verir.
 * "Bu dilin karşılığı var mı" bir veri sorusudur ve tarayıcıda cevaplanamaz.
 *
 * `unavailableReason` iki farklı durumu AYRI tutar, çünkü kullanıcı için
 * iki farklı cümledir:
 *
 * - `not-offered` — bu dil henüz sunulmuyor (katalog tam değil).
 * - `no-counterpart` — dil sunuluyor ama bu sayfanın o dilde karşılığı yok.
 *
 * İkisini "kullanılamaz" diye birleştirmek, kullanıcıya yanlış bir şey
 * söylemek olurdu: birinde ürün eksik, ötekinde yalnız bu sayfa eksik.
 */
final readonly class LanguageChoice
{
    public function __construct(
        public Language $language,
        public ?string $href,
        public bool $isCurrent,
        public bool $isAvailable,
        public ?string $unavailableReason = null,
    ) {}
}
