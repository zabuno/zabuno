<?php

declare(strict_types=1);

namespace App\Domain\Platform\Credential;

/**
 * TEK bir sağlayıcı bağlantısı — sırrı OLMADAN.
 *
 * `CredentialStatus`'un çok-bağlantılı hâli: aynı disiplin (yalnız var/yok +
 * maskelenmiş son 4), üstüne bağlantıyı ayırt eden üç şey — superadmin'in
 * verdiği `label`, kapsamı (`scope`) ve sağlığı (`health`).
 *
 * Etiket olmadan panelde iki kart ayırt edilemez: sır görünmediği için
 * "hangisi toplu içe aktarma hesabıydı" sorusunun başka cevabı yoktur.
 */
final readonly class CredentialConnection
{
    /** @param list<CredentialFieldStatus> $fields */
    public function __construct(
        public int $id,
        public CredentialProvider $provider,
        public string $label,
        public CredentialScope $scope,
        public ?int $workspaceId,
        public bool $configured,
        public string $state,
        public ConnectionHealth $health,
        public array $fields,
        public ?string $lastRotatedAt = null,
        public ?string $lastHealthCheckAt = null,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'id' => $this->id,
            'provider' => $this->provider->value,
            'label' => $this->label,
            'scope' => $this->scope->value,
            'workspaceId' => $this->workspaceId,
            'configured' => $this->configured,
            'state' => $this->state,
            'health' => $this->health->value,
            'lastRotatedAt' => $this->lastRotatedAt,
            'lastHealthCheckAt' => $this->lastHealthCheckAt,
            'fields' => array_map(
                static fn (CredentialFieldStatus $f): array => $f->toArray(),
                $this->fields,
            ),
        ];
    }
}
