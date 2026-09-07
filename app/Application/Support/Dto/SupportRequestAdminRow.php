<?php

declare(strict_types=1);

namespace App\Application\Support\Dto;

use App\Domain\Support\DeliveryState;

/**
 * Süperadmin kuyruğundaki satır — cevap yazacak kişinin görmesi gereken
 * her şey: kim, nereden, ne yazdı, alındı e-postası çıktı mı.
 *
 * Gönderim SEBEBİ yine yok: süperadmin de sağlayıcının ham cümlesini
 * ekranda görmez, günlükte görür. Ekrana yalnız türetilmiş hâl çıkar.
 */
final class SupportRequestAdminRow
{
    public function __construct(
        public readonly int $id,
        public readonly string $reference,
        public readonly ?int $workspaceId,
        public readonly string $name,
        public readonly string $email,
        public readonly string $subject,
        public readonly string $message,
        public readonly string $channel,
        public readonly string $status,
        public readonly string $receivedAt,
        public readonly ?string $firstResponseAt,
        public readonly DeliveryState $acknowledgement,
    ) {}

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'workspace_id' => $this->workspaceId,
            'name' => $this->name,
            'email' => $this->email,
            'subject' => $this->subject,
            'message' => $this->message,
            'channel' => $this->channel,
            'status' => $this->status,
            'received_at' => $this->receivedAt,
            'first_response_at' => $this->firstResponseAt,
            'acknowledgement' => $this->acknowledgement->value,
        ];
    }
}
