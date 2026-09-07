<?php

declare(strict_types=1);

namespace App\Application\Billing\Port;

use App\Application\Billing\Dto\Invoice;

/**
 * Belgenin basılabilir hâli (docs/130 §K6).
 *
 * Ekrandaki liste ile kâğıttaki belge AYRI kiplerdir; bu port kâğıt
 * tarafıdır. Ayrı olmaları bilinçli: dar ekranda yatayda kaydırılabilen
 * bir tablo A4'te kaydırılamaz, taşar.
 */
interface InvoiceDocumentRendererPort
{
    /** @return string PDF baytları. */
    public function render(Invoice $invoice): string;
}
