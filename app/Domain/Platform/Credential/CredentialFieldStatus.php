<?php

declare(strict_types=1);

namespace App\Domain\Platform\Credential;

/**
 * Panelin bir alan hakkında görmesine İZİN VERİLEN her şey.
 *
 * Sır alanların `preview`'i yalnız son 4 karakterin maskesidir
 * (`••••b1c0`); tam değer BURADA YOKTUR ve buraya asla konmaz. Düz alanlar
 * (domain, endpoint) tam değerini gösterebilir — onlar sır değildir.
 */
final readonly class CredentialFieldStatus
{
    public function __construct(
        public string $name,
        public bool $secret,
        public bool $isSet,
        public ?string $preview,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'secret' => $this->secret,
            'isSet' => $this->isSet,
            'preview' => $this->preview,
        ];
    }
}
