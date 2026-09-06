<?php

declare(strict_types=1);

namespace App\Application\Support\Dto;

use App\Domain\Support\SupportChannel;

/**
 * Kaydedilmiş talep — referansı artık var.
 *
 * Gönderim adımları (alındı bildirimi, sahibe bildirim) bunu okur: kime,
 * hangi dilde, hangi referansla. Durum ve damgalar burada YOK; onlar
 * satırın sonraki hayatıdır ve özet DTO'larda yaşar.
 */
final class ReceivedSupportRequest
{
    public function __construct(
        public readonly int $id,
        public readonly string $reference,
        public readonly string $name,
        public readonly string $email,
        public readonly string $subject,
        public readonly string $message,
        public readonly SupportChannel $channel,
        public readonly ?string $locale,
        public readonly ?int $workspaceId,
        public readonly \DateTimeImmutable $receivedAt,
    ) {}
}
