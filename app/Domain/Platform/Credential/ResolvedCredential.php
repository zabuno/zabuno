<?php

declare(strict_types=1);

namespace App\Domain\Platform\Credential;

/**
 * Çözülmüş kimlik bilgisi — VE hangi bağlantıdan geldiği.
 *
 * Kimliği taşımanın somut sebebi var: bir çağrı başarısız olduğunda,
 * adaptörün HANGİ hesabın düştüğünü söyleyebilmesi gerekir. Yalnız
 * değerleri döndürseydik, "bu sağlayıcı bozuk" diyebilirdik ama "bu
 * hesap bozuk" diyemezdik — oysa iki hesabın biri çalışıyor olabilir.
 *
 * `connectionId` yalnız kasadan gelen bir bağlantı için doludur; env
 * yedeğinden gelen değerlerin bir bağlantısı yoktur ve olmamalıdır.
 */
final readonly class ResolvedCredential
{
    /** @param array<string, string> $values Alan adı → SIR. Log'a yazılmaz. */
    public function __construct(
        public array $values,
        public ?int $connectionId = null,
    ) {}

    public function isEmpty(): bool
    {
        return $this->values === [];
    }
}
