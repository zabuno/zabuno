<?php

declare(strict_types=1);

namespace App\Domain\Entitlement;

/**
 * Plan tarafından verilebilen yetenekler — CORE-04.
 *
 * Bu bir ENUM'dur, serbest metin değildir. `plans.entitlements` sütunu bugün
 * serbest string listesi tutuyor (`['feature.a']`) ve hiçbir yerde
 * uygulanmıyordu: plan satın alınabiliyor, hiçbir yetenek kapanmıyordu.
 * Serbest metin bırakılsaydı, yazım hatası taşıyan bir entitlement sessizce
 * hiçbir şeyi açmaz ve kimse fark etmezdi.
 *
 * **Kapsam kuralı:** entitlement EK YETKİ verir; temel yolculuğu kapatmaz.
 * Kayıt→menü→yayın→QR zinciri plansız bir hesapta çalışmaya devam eder
 * (`RestaurantCriticalJourneyTest` bunu donduruyor). Buradaki her değer,
 * o zincirin ÜSTÜNDE duran bir yetenektir.
 */
enum Entitlement: string
{
    /** Tek tek değil, masa aralığı vererek toplu QR üretimi. */
    case QrBulkGeneration = 'qr.bulk-generation';

    /** Sahibin yanına ekip üyesi daveti. */
    case TeamInvitations = 'team.invitations';

    /** Yayınlanmış menünün ziyaret/analitik raporu. */
    case AnalyticsReporting = 'analytics.reporting';

    public function label(): string
    {
        return match ($this) {
            self::QrBulkGeneration => 'Toplu QR üretimi',
            self::TeamInvitations => 'Ekip daveti',
            self::AnalyticsReporting => 'Analitik raporlama',
        };
    }

    /** Tanınmayan bir anahtar `null` döner; bilinmeyen asla yetki vermez. */
    public static function tryFromKey(string $key): ?self
    {
        return self::tryFrom($key);
    }
}
