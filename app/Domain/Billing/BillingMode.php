<?php

declare(strict_types=1);

namespace App\Domain\Billing;

/**
 * Ödeme kipi — hangi Iyzico'ya konuşulduğu.
 *
 * İki değer, üçüncüsü yok: "test" ile "gerçek para" arasında ara durum
 * olmaz. Bir işlem yaratıldığı andaki kipi TAŞIR (`payment_transactions.mode`),
 * çünkü geç gelen bir webhook anahtar değişmiş olsa bile kendi geçidiyle
 * doğrulanmalıdır.
 */
enum BillingMode: string
{
    case Sandbox = 'sandbox';
    case Live = 'live';
}
