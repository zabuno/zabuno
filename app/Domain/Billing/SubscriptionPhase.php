<?php

declare(strict_types=1);

namespace App\Domain\Billing;

/**
 * Aboneliğin BUGÜN hangi evrede olduğu (`docs/107` Faz 1.3, `docs/134`).
 *
 * Bu bir SÜTUN DEĞİLDİR. `subscriptions.state` yalnız "yaşayan bir abonelik
 * var mı" sorusunun eski cevabıdır; evre ise üç tarihten türetilir (bitiş,
 * iptal, ödemesiz sürenin sonu). Sütuna yazılsaydı, saat ilerledikçe
 * kendiliğinden yanlışlaşan ve yalnız bir zamanlayıcı koştuğunda düzelen bir
 * alan olurdu — bu depo aynı hatayı `menu_publications` yayın haklarında bir
 * kez yaşadı ve oradan çıkardığı ders şudur: **zamanla değişen bir gerçek
 * saklanmaz, hesaplanır.**
 *
 * Yetenek veren evreler `grantsEntitlements()` ile tek yerde yazılıdır;
 * `DatabaseEntitlementRepository` ve panel aynı cevabı okur.
 */
enum SubscriptionPhase: string
{
    /** Hiç abonelik yok. Ücretsiz yolculuk (kayıt→menü→yayın→QR) çalışmaya devam eder. */
    case None = 'none';

    /** Ödenmiş dönem sürüyor, iptal edilmemiş. */
    case Active = 'active';

    /**
     * Sahip iptal etti; ödenmiş dönem SÜRÜYOR.
     *
     * Hizmet kesilmez, yalnız yenileme durur. Sahip bu evrede iptalinden
     * cayabilir ve yeniden ödeme yapmak zorunda kalmaz.
     */
    case Cancelling = 'cancelling';

    /**
     * Dönem bitti, ödeme gelmedi, ödemesiz süre içindeyiz.
     *
     * Yetenekler AÇIK kalır (`billing.subscription.grace_days`). Sahip
     * panelde ne olduğunu ve ne zaman kapanacağını okur.
     */
    case Grace = 'grace';

    /**
     * Ödemesiz süre de doldu: plan yetenekleri kapalı.
     *
     * "Hesap kapandı" DEĞİLDİR: veri durur, menü yayında kalır, sahip
     * panelini kullanmaya devam eder; kapanan yalnız planın verdiği EK
     * yeteneklerdir. Başarılı bir ödeme hesabı elle müdahale olmadan geri
     * getirir.
     */
    case Suspended = 'suspended';

    /** Sahip iptal etmişti ve ödenmiş dönem de bitti: abonelik sona erdi. */
    case Ended = 'ended';

    /**
     * Bu evre planın yeteneklerini veriyor mu?
     *
     * `Grace` DE VERİR ve ödemesiz sürenin bütün anlamı budur: gecikmiş bir
     * ödeme, hizmeti aynı saniyede kesmez.
     */
    public function grantsEntitlements(): bool
    {
        return match ($this) {
            self::Active, self::Cancelling, self::Grace => true,
            self::None, self::Suspended, self::Ended => false,
        };
    }

    /** Sahip bu evrede iptalinden cayabilir mi? */
    public function canResume(): bool
    {
        return $this === self::Cancelling;
    }

    /** Sahip bu evrede iptal edebilir mi? (Zaten iptalliyse hayır.) */
    public function canCancel(): bool
    {
        return match ($this) {
            self::Active, self::Grace => true,
            self::None, self::Cancelling, self::Suspended, self::Ended => false,
        };
    }
}
