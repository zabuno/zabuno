<?php

declare(strict_types=1);

namespace App\Domain\Legal;

use InvalidArgumentException;

/**
 * Bir üçüncü taraf paketin lisans satırı — FF-228.
 *
 * LİSANS UYDURULMAZ. Kilit dosyasında lisans alanı yoksa `license` boş
 * kalır ve satır bunu SÖYLER; "MIT" varsaymak, olmayan bir izni varmış gibi
 * göstermek olurdu.
 */
final readonly class DependencyLicense
{
    public function __construct(
        public string $name,
        public string $version,
        public ?string $license,
    ) {
        if (trim($name) === '') {
            throw new InvalidArgumentException('A dependency needs a name.');
        }
    }

    public function sentence(): string
    {
        $version = trim($this->version) === '' ? 'version not recorded' : $this->version;

        return $this->name.' '.$version.' — '.($this->license ?? 'licence not declared in the lock file');
    }
}
