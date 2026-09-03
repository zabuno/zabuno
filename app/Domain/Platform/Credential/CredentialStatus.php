<?php

declare(strict_types=1);

namespace App\Domain\Platform\Credential;

/**
 * Bir sağlayıcının kasadaki durumu — SIRRI OLMADAN.
 *
 * Bu nesne HTTP katmanına dönen tek şeydir. Tam sır değeri taşımaz; taşıyamaz
 * — alanları yalnız `CredentialFieldStatus` maskeleridir. Vault modülünün
 * disiplini: "yalnız var/yok + maskelenmiş son 4 karakter"
 * (`modules/ai-provider-account-vault.md` §Data retention).
 */
final readonly class CredentialStatus
{
    /** @param list<CredentialFieldStatus> $fields */
    public function __construct(
        public CredentialProvider $provider,
        public bool $configured,
        public string $state,
        public array $fields,
        public ?string $lastRotatedAt = null,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'provider' => $this->provider->value,
            'configured' => $this->configured,
            'state' => $this->state,
            'lastRotatedAt' => $this->lastRotatedAt,
            'fields' => array_map(
                static fn (CredentialFieldStatus $f): array => $f->toArray(),
                $this->fields,
            ),
        ];
    }
}
