<?php

declare(strict_types=1);

namespace App\Application\DataRights\Dto;

use App\Domain\DataRights\DataRequestKind;
use App\Domain\DataRights\DataRequestState;

/**
 * Ekrana ve denetim izine giden tek satır — FF-226.
 *
 * Kayıt DEĞİL, kaydın OKUNAN hâlidir: ham satırda duran dosya yolu buraya
 * hiç gelmez. Yol bir konumdur ve konumun ekranda işi yoktur; indirme
 * imzalı uçtan geçer.
 */
final readonly class DataRequestRow
{
    /**
     * @param  list<string>  $scopeSections
     * @param  array<string, int>  $deletedCounts
     */
    public function __construct(
        public int $id,
        public int $workspaceId,
        public DataRequestKind $kind,
        public DataRequestState $state,
        public ?string $requestedByEmail,
        public string $requestedAt,
        public array $scopeSections,
        public ?int $artifactBytes,
        public ?string $availableUntil,
        public ?string $scheduledFor,
        public ?string $completedAt,
        public ?string $cancelledAt,
        public ?string $failureReason,
        public ?string $notifiedAt,
        public ?string $notificationFailure,
        public array $deletedCounts,
    ) {}

    public function deletedRowTotal(): int
    {
        return array_sum($this->deletedCounts);
    }
}
