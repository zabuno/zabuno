<?php

declare(strict_types=1);

namespace App\Application\Support\Dto;

/**
 * Bir kiracı olarak bakma oturumunun tek satırlık kaydı — `docs/133`.
 *
 * AYNI SATIR HEM SÜPERADMİNE HEM KİRACIYA GİDER ve bu kasıtlıdır: iki ayrı
 * gösterim, bir gün iki ayrı gerçek üretirdi. Kiracının gördüğü cümlenin
 * ("şu tarihte, şu sebeple, şu kadar süre, platform ekibinden biri
 * hesabınıza baktı") her parçası burada durur.
 *
 * SATIR SIR TAŞIMAZ: oturum kimliği dışında hiçbir jeton, çerez ya da
 * oturum yükü yoktur; taşınmayan alan sızmaz.
 */
final class SupportAccessSessionRow
{
    public function __construct(
        public readonly int $id,
        public readonly int $workspaceId,
        public readonly ?string $actorEmail,
        public readonly string $reason,
        public readonly string $startedAt,
        public readonly string $expiresAt,
        public readonly ?string $endedAt,
        public readonly bool $active,
    ) {}

    /**
     * @return array{id:int,workspaceId:int,actor:?string,reason:string,startedAt:string,expiresAt:string,endedAt:?string,active:bool}
     */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'workspaceId' => $this->workspaceId,
            // Fail E-POSTAYLA yazılır: bir ekipte iki "Mehmet" olabilir.
            // Kullanıcı silinmişse alan boş kalır — kaydı gizlemek yerine
            // failin bilinmediğini söylemek dürüst olandır.
            'actor' => $this->actorEmail,
            'reason' => $this->reason,
            'startedAt' => $this->startedAt,
            'expiresAt' => $this->expiresAt,
            'endedAt' => $this->endedAt,
            'active' => $this->active,
        ];
    }
}
