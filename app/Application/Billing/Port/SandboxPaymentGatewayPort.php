<?php

declare(strict_types=1);

namespace App\Application\Billing\Port;

/** Sandbox kipinin geçidi — para hareket etmez. */
interface SandboxPaymentGatewayPort extends PaymentGatewayPort {}
