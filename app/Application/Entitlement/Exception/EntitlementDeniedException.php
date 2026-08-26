<?php

declare(strict_types=1);

namespace App\Application\Entitlement\Exception;

use App\Domain\Entitlement\Entitlement;
use RuntimeException;

/**
 * Yeteneğin planda olmadığını bildirir.
 *
 * Mesaj kullanıcıya gösterilebilir olmalıdır: hangi yeteneğin eksik olduğunu
 * söylemeyen bir ret, kullanıcıyı çıkışsız bırakır. "Erişim reddedildi"
 * demek yerine ne olduğunu ve ne yapılabileceğini söyler.
 */
final class EntitlementDeniedException extends RuntimeException
{
    private function __construct(
        public readonly Entitlement $entitlement,
        string $message,
    ) {
        parent::__construct($message);
    }

    public static function missing(Entitlement $entitlement): self
    {
        return new self(
            $entitlement,
            sprintf('%s bu planda bulunmuyor.', $entitlement->label()),
        );
    }
}
