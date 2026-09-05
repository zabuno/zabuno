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

    /**
     * Restoranın kendi marka tonunu ve biçimini misafir menüsüne taşıması
     * (`docs/113` §10.1, `modules/opt-08-custom-branding.md`).
     *
     * Kapsam kuralına uyar: skin SEÇMEMEK bir kusur değildir. Seçmeyen
     * restoran bugünkü nötr görünümü alır ve menüsünü yayınlamaya, karekod
     * basmaya devam eder — bu gerçekten EK bir yetkidir.
     */
    case BrandingCustom = 'branding.custom';

    public function label(): string
    {
        return match ($this) {
            self::QrBulkGeneration => 'Toplu QR üretimi',
            self::TeamInvitations => 'Ekip daveti',
            self::AnalyticsReporting => 'Analitik raporlama',
            self::BrandingCustom => 'Marka görünümü',
        };
    }

    /**
     * Doğrulama kurallarının okuduğu anahtar listesi.
     *
     * Serbest metin kabul eden bir doğrulama, enum'un "bilinmeyen asla
     * yetki vermez" kuralıyla birleşince sinsi bir sessizlik üretir: plan
     * kaydedilir, ekranda yetenek yazar, restoran onu hiç almaz
     * (`docs/113` §10.2).
     *
     * @return list<string>
     */
    public static function keys(): array
    {
        return array_map(static fn (self $case): string => $case->value, self::cases());
    }

    /** Tanınmayan bir anahtar `null` döner; bilinmeyen asla yetki vermez. */
    public static function tryFromKey(string $key): ?self
    {
        return self::tryFrom($key);
    }
}
