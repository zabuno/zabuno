<?php

declare(strict_types=1);

namespace App\Application\Billing\Port;

/** Canlı kipin geçidi — gerçek para. Yalnız etkin kip `live` iken çağrılır. */
interface LivePaymentGatewayPort extends PaymentGatewayPort {}
