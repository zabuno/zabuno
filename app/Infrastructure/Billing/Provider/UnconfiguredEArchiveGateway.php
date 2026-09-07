<?php

declare(strict_types=1);

namespace App\Infrastructure\Billing\Provider;

use App\Application\Billing\Exception\EArchiveGatewayNotConfiguredException;
use App\Application\Billing\Port\EArchiveGatewayPort;
use App\Domain\Billing\EArchiveDispatchState;

/**
 * e-arşiv/e-fatura kapısının BUGÜNKÜ tek uygulaması: yol yok, açıkça yok.
 *
 * Bu bir "boş nesne" (null object) değildir — boş nesne sessizce başarılı
 * döner. Burası DURUR. Aradaki fark, bir gün vergi denetiminde
 * kesilmemiş bir faturayı kesilmiş sanmakla sanmamak arasındaki farktır.
 *
 * Gerçek bir entegratör adaptörü, sahip sözleşmeyi yaptıktan sonra bu
 * portun ikinci uygulaması olarak yazılır (docs/130 §K4); o güne kadar
 * ürün "yakında e-fatura" diye bir söz de vermez.
 */
final class UnconfiguredEArchiveGateway implements EArchiveGatewayPort
{
    public function isConfigured(): bool
    {
        return false;
    }

    public function state(): EArchiveDispatchState
    {
        return EArchiveDispatchState::NotConfigured;
    }

    public function submit(int $invoiceId): string
    {
        throw new EArchiveGatewayNotConfiguredException(
            'No e-Arşiv / e-Fatura provider is configured; the document was not sent anywhere.'
        );
    }
}
