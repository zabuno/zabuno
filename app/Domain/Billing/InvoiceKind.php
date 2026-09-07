<?php

declare(strict_types=1);

namespace App\Domain\Billing;

/**
 * Belge türü (docs/130 §K3).
 *
 * `CreditNote` bir "silme" değildir: kesilmiş fatura yerinde durur, iade
 * onun karşısına yeni bir belge koyar. Defterin karşı kaydıyla aynı
 * mantık — hem hata hem düzeltmesi görünür kalır.
 */
enum InvoiceKind: string
{
    case Invoice = 'invoice';

    case CreditNote = 'credit_note';
}
