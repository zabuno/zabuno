<?php

declare(strict_types=1);

namespace App\Domain\Media;

/**
 * Bir SVG gövdesi hakkında verilen karar.
 *
 * ÜÇ AYRI SORU tek kelimeye doldurulmaz — `docs/49`'un tarama iş kaydında
 * `held` ile `failed`i ayırmasıyla aynı sebep:
 *
 *   - `threats`  : gövdede ÇALIŞABİLİR ya da DIŞARI ÇIKAN bir şey vardı.
 *                  Bu bir kaza değil, bir saldırı imzasıdır.
 *   - `stripped` : gövde okunabilirdi, yalnız gereksiz kalıntı taşıyordu
 *                  (editör metadata'sı, yorum, tanımadığımız ögeler).
 *                  Zararsız; temizlenir ve dosya kabul edilir.
 *   - `failureReason` : sahibin ekranda okuyacağı cümle. Boş bırakmak,
 *                  sahibi "yükledim ama bir şey olmadı" ile bırakmaktır.
 *
 * Ayrım ürünü belirler: `stripped` dolu bir dosya KABUL EDİLİR ve
 * temizlenmiş gövdesi saklanır; `threats` dolu bir dosya REDDEDİLİR.
 * İkincisini sessizce temizleyip kabul etmek, saldırıyı arşivlemek ve
 * sahibin dosyasını haber vermeden değiştirmek olurdu.
 */
final readonly class SvgSanitizationResult
{
    /**
     * @param  list<string>  $threats
     * @param  list<string>  $stripped
     */
    private function __construct(
        public ?string $sanitized,
        public array $threats,
        public array $stripped,
        public ?string $failureReason,
    ) {}

    /** @param  list<string>  $stripped */
    public static function safe(string $sanitized, array $stripped = []): self
    {
        return new self($sanitized, [], $stripped, null);
    }

    /**
     * Gövde okundu, saldırı bulundu. Temizlenmiş dize yine de taşınır:
     * çağıran onu KULLANMAZ, ama bir denetim izine yazmak isterse elinde
     * saldırının kendisi değil, zararsızlaştırılmış hâli olur.
     *
     * @param  list<string>  $threats
     * @param  list<string>  $stripped
     */
    public static function hostile(?string $sanitized, array $threats, string $reason, array $stripped = []): self
    {
        return new self($sanitized, $threats, $stripped, $reason);
    }

    /** Gövde hiç okunamadı: ayrıştırılamadı, SVG değil ya da fazla büyük. */
    public static function unreadable(string $reason): self
    {
        return new self(null, [], [], $reason);
    }

    public function isSafe(): bool
    {
        return $this->sanitized !== null && $this->threats === [];
    }
}
