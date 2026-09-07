<?php

declare(strict_types=1);

namespace Tests\Unit\Billing;

use App\Application\Billing\Exception\EArchiveGatewayNotConfiguredException;
use App\Application\Billing\Port\EArchiveGatewayPort;
use App\Domain\Billing\EArchiveDispatchState;
use App\Infrastructure\Billing\Provider\UnconfiguredEArchiveGateway;
use PHPUnit\Framework\Attributes\Test;
use ReflectionEnum;
use Tests\TestCase;

/**
 * E-ARŞİV KAPISI — bağlı olmayan bir sağlayıcı SESSİZCE BAŞARILI DÖNMEZ
 * (docs/130 §K4).
 *
 * Türkiye'de e-arşiv/e-fatura kesmek kayıtlı bir entegratör ya da GİB
 * portalı ister; sözleşme sahibin işidir ve bugün yoktur. Bu paket o
 * sözleşmenin YERİNİ (portu) açar, kendisini değil.
 *
 * Bir gün vergi denetiminde en pahalı hata, kesilmemiş bir faturayı
 * kesilmiş sanmaktır. Bu yüzden bugünkü tek uygulama açıkça DURUR ve
 * "gönderildi" diye bir durum ürünün sözlüğünde HİÇ yoktur.
 */
final class EArchiveGatewayPortTest extends TestCase
{
    #[Test]
    public function the_bound_gateway_reports_that_it_is_not_configured(): void
    {
        $gateway = $this->app->make(EArchiveGatewayPort::class);

        self::assertInstanceOf(UnconfiguredEArchiveGateway::class, $gateway);
        self::assertFalse($gateway->isConfigured());
        self::assertSame(EArchiveDispatchState::NotConfigured, $gateway->state());
    }

    #[Test]
    public function submitting_a_document_stops_loudly_instead_of_pretending_to_succeed(): void
    {
        $gateway = $this->app->make(EArchiveGatewayPort::class);

        $this->expectException(EArchiveGatewayNotConfiguredException::class);

        $gateway->submit(1);
    }

    #[Test]
    public function the_vocabulary_has_no_sent_state_to_claim(): void
    {
        $values = array_map(
            static fn (EArchiveDispatchState $case): string => $case->value,
            EArchiveDispatchState::cases(),
        );

        self::assertSame(['not_configured', 'not_dispatched'], $values);
        self::assertNotContains('sent', $values, 'Gönderilmemiş bir belgeyi "gönderildi" gösteren bir durum eklenemez.');
        self::assertNotContains('submitted', $values);

        // Sözlük kapalı: yeni bir durum, bu testi kırmadan eklenemez.
        self::assertCount(2, (new ReflectionEnum(EArchiveDispatchState::class))->getCases());
    }
}
