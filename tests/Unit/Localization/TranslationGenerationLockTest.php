<?php

declare(strict_types=1);

namespace Tests\Unit\Localization;

use App\Domain\Localization\TranslationGenerationLock;
use App\Domain\Localization\TranslationGenerationLocked;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * TRANSLATION-LOCK-01 — FF-117, yönerge §1 ve §10.2.
 *
 * Sahibin değiştirilemez kararı: bütün Türkçe sayfalar tamamlanmadan gerçek
 * çeviri üretimine başlanmaz. Bu test o kararın kod tarafındaki karşılığıdır.
 */
final class TranslationGenerationLockTest extends TestCase
{
    public function test_it_is_locked_by_default(): void
    {
        // Varsayılanı açık bırakıp "nasılsa kimse çağırmaz" demek, bir gün bir
        // zamanlanmış görevin kendiliğinden çeviri başlatması demekti.
        self::assertFalse(TranslationGenerationLock::fromConfig(null)->isUnlocked());
        self::assertFalse(TranslationGenerationLock::fromConfig(false)->isUnlocked());
    }

    /** @return list<array{0: mixed}> */
    public static function almostTrueValues(): array
    {
        return [['1'], ['true'], ['yes'], [1], ['on'], [[]]];
    }

    #[DataProvider('almostTrueValues')]
    public function test_only_an_exact_true_unlocks_it(mixed $value): void
    {
        // "1" ya da "true" gibi bir dize, bir `.env` yazım hatasıyla kolayca
        // oluşur. Şüphe hâlinde güvenli taraf kilitli olmaktır.
        self::assertFalse(TranslationGenerationLock::fromConfig($value)->isUnlocked());
    }

    public function test_a_locked_lock_refuses_and_says_who_can_open_it(): void
    {
        $this->expectException(TranslationGenerationLocked::class);
        $this->expectExceptionMessageMatches('/ÇEVİRİLERE BAŞLA/u');

        TranslationGenerationLock::fromConfig(false)->assertUnlocked();
    }

    public function test_the_shipped_configuration_is_locked(): void
    {
        /*
            Deponun GERÇEK yapılandırması ölçülür, sahte bir değer değil.
            Bu satır yeşil kaldığı sürece, kimse farkında olmadan çeviri
            üretimini açmış olamaz.
        */
        $config = require __DIR__.'/../../../config/localization.php';

        self::assertFalse(
            $config['translation_generation_enabled'],
            'TRANSLATION-LOCK-01: çeviri üretimi kilidi açılmış — bu yalnız sahibin açık talebiyle olabilir.',
        );
    }
}
