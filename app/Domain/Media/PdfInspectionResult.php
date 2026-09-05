<?php

declare(strict_types=1);

namespace App\Domain\Media;

/**
 * Bir PDF gövdesi hakkında verilen karar.
 *
 * `SvgSanitizationResult` ile aynı ayrımı taşır, bir farkla: burada
 * `sanitized` diye bir alan YOKTUR ve olmaması bilinçlidir.
 *
 * SVG'de temizlenmiş bir gövde üretmek anlamlıydı — çizim, betikten
 * ayrılabilir. PDF'te ayrılamaz: eylemler nesne sözlüklerinin içinde,
 * çapraz referans tablosunun işaret ettiği bayt konumlarında yaşar. Bir
 * nesneyi çıkarmak, tablodaki her konumu kaydırır; "temizlenmiş" bir PDF
 * üretmek pratikte dosyayı YENİDEN YAZMAK demektir ve o iş bu depoda
 * bağımlılıksız yapılamaz. Yarım yapılırsa sonuç, sahibin açamadığı bozuk
 * bir belgedir.
 *
 * Bu yüzden karar iki uçludur: gövde ya olduğu gibi kabul edilir ya da
 * REDDEDİLİR.
 *
 *   - `threats`  : gövdede ÇALIŞABİLİR ya da DIŞARI ÇIKAN bir şey vardı.
 *   - `failureReason` : sahibin ekranda okuyacağı cümle. Boş bırakmak,
 *                  sahibi "yükledim ama bir şey olmadı" ile bırakmaktır.
 */
final readonly class PdfInspectionResult
{
    /** @param  list<string>  $threats */
    private function __construct(
        public array $threats,
        public ?string $failureReason,
        public bool $readable,
    ) {}

    public static function safe(): self
    {
        return new self([], null, true);
    }

    /**
     * Gövde okundu, saldırı bulundu.
     *
     * @param  list<string>  $threats
     */
    public static function hostile(array $threats, string $reason): self
    {
        return new self($threats, $reason, true);
    }

    /**
     * Gövde hiç okunamadı: PDF değil, yarım inmiş, şifreli ya da fazla
     * büyük. Okunamayan bir dosya "temiz" DEĞİLDİR — hakkında hiçbir şey
     * bilmediğimiz dosyadır ve fail-closed reddedilir.
     */
    public static function unreadable(string $reason): self
    {
        return new self([], $reason, false);
    }

    public function isSafe(): bool
    {
        return $this->readable && $this->threats === [];
    }
}
