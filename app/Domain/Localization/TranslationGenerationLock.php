<?php

declare(strict_types=1);

namespace App\Domain\Localization;

/**
 * Çeviri üretiminin kilidi — FF-117, yönerge §1 ve §10.2.
 *
 * SAHİBİN DEĞİŞTİRİLEMEZ KARARI: bütün Türkçe sayfalar tamamlanmadan gerçek
 * çeviri üretimine başlanmaz. Sahip açıkça `ÇEVİRİLERE BAŞLA` demeden hiçbir
 * çeviri çağrısı yapılmaz, hiçbir kuyruk başlatılmaz, hiçbir alan tahmini
 * İngilizce içerikle doldurulmaz.
 *
 * Kilit BURADA tek bir yerde yaşar ve varsayılanı KAPALIDIR. Varsayılanı açık
 * bırakıp "nasılsa kimse çağırmaz" demek, bir gün bir zamanlanmış görevin ya
 * da bir olay dinleyicisinin kendiliğinden çeviri başlatması demekti.
 *
 * Kilidin açılması bir yapılandırma değişikliği DEĞİL, bir sahip kararıdır;
 * yapılandırma yalnız o kararın kaydıdır.
 */
final class TranslationGenerationLock
{
    public function __construct(private readonly bool $enabled) {}

    public static function fromConfig(mixed $configuredValue): self
    {
        // Yalnız kesin `true` açar. "1", "yes", null ya da tanımsız bir değer
        // kapalı sayılır: şüphe hâlinde güvenli taraf kilitli olmaktır.
        return new self($configuredValue === true);
    }

    public function isUnlocked(): bool
    {
        return $this->enabled;
    }

    /**
     * Çeviri üretecek her kod yolunun İLK satırı.
     *
     * @throws TranslationGenerationLocked
     */
    public function assertUnlocked(): void
    {
        if (! $this->enabled) {
            throw TranslationGenerationLocked::make();
        }
    }
}
