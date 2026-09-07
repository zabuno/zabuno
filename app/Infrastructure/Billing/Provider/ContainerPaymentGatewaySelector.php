<?php

declare(strict_types=1);

namespace App\Infrastructure\Billing\Provider;

use App\Application\Billing\Port\LivePaymentGatewayPort;
use App\Application\Billing\Port\PaymentGatewayPort;
use App\Application\Billing\Port\PaymentGatewaySelectorPort;
use App\Application\Billing\Port\SandboxPaymentGatewayPort;
use App\Domain\Billing\BillingMode;
use Illuminate\Contracts\Container\Container;

/**
 * Geçit, KİP SÖYLENDİĞİNDE çözülür — önceden değil.
 *
 * Canlı geçit sandbox kipinde hiç kurulmaz: kasa okunmaz, `Options`
 * yaratılmaz. "Anahtar kapalıyken canlı geçit çağrılmaz" cümlesi burada
 * yapısal olarak doğrudur, bir `if` ile değil.
 */
final class ContainerPaymentGatewaySelector implements PaymentGatewaySelectorPort
{
    public function __construct(private readonly Container $container) {}

    public function forMode(BillingMode $mode): PaymentGatewayPort
    {
        return match ($mode) {
            BillingMode::Live => $this->container->make(LivePaymentGatewayPort::class),
            BillingMode::Sandbox => $this->container->make(SandboxPaymentGatewayPort::class),
        };
    }
}
