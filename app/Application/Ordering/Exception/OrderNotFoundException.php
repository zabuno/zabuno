<?php

declare(strict_types=1);

namespace App\Application\Ordering\Exception;

use RuntimeException;

/**
 * Bu kiracının, bu şubesinde böyle bir sipariş YOK.
 *
 * "Var ama senin değil" ile "hiç yok" arasındaki farkı DIŞARI VERMEZ:
 * ayrıştırılsaydı, komşu şubenin garsonu deneyerek başka bir şubede kaç
 * sipariş olduğunu ölçebilirdi. Sınır sorgunun İÇİNDEDİR; bu istisna o
 * sorgunun boş dönmesinin adıdır.
 */
final class OrderNotFoundException extends RuntimeException
{
    public static function inScope(int $workspaceId, int $locationId, int $orderId): self
    {
        return new self(sprintf(
            'Sipariş bulunamadı (workspace=%d, location=%d, order=%d).',
            $workspaceId,
            $locationId,
            $orderId,
        ));
    }
}
