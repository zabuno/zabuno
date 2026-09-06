<?php

declare(strict_types=1);

namespace App\Application\Support\Dto;

/**
 * Panel listesindeki satır — sahibin kendi talebi.
 *
 * Mesaj gövdesi listede YOK: sahip ne yazdığını biliyor, listede aradığı
 * şey "hangi durumda". Gönderim damgaları ve sebepleri de yok — onlar
 * taşıyıcının cümlelerini taşır ve ekrana düşen bir sağlayıcı cevabı,
 * ürünün altyapısını tanıtan ücretsiz bir haritadır.
 */
final class SupportRequestSummary
{
    public function __construct(
        public readonly int $id,
        public readonly string $reference,
        public readonly string $subject,
        public readonly string $status,
        public readonly string $receivedAt,
        public readonly ?string $firstResponseAt,
    ) {}

    /**
     * @return array{id:int,reference:string,subject:string,status:string,received_at:string,first_response_at:?string}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'reference' => $this->reference,
            'subject' => $this->subject,
            'status' => $this->status,
            'received_at' => $this->receivedAt,
            'first_response_at' => $this->firstResponseAt,
        ];
    }
}
