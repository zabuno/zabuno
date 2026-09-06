<?php

declare(strict_types=1);

namespace App\Application\Billing\Port;

use App\Domain\Billing\BillingMode;

/**
 * Kipe göre geçit.
 *
 * Kullanım noktası kipi HER SEFERİNDE söyler: yeni bir ödeme etkin kipi
 * kullanır, geç gelen bir webhook ise işlemin KENDİ kipini. İkisi aynı
 * olmak zorunda değil.
 */
interface PaymentGatewaySelectorPort
{
    public function forMode(BillingMode $mode): PaymentGatewayPort;
}
