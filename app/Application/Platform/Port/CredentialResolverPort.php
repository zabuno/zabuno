<?php

declare(strict_types=1);

namespace App\Application\Platform\Port;

use App\Domain\Platform\Credential\CredentialProvider;
use App\Domain\Platform\Credential\ResolvedCredential;

/**
 * Sırrı ÇÖZEN taraf — yalnız tüketici altyapı içindir.
 *
 * Posta göndericisi ya da OpenAI adaptörü bir çağrı yaparken buradan gerçek
 * değeri okur. HTTP controller'ları bu portu ASLA enjekte etmez; onların
 * portu `PlatformCredentialAdminPort`'tur ve sır geri okumaz.
 *
 * Kasa boşsa (ya da sağlayıcı kapalıysa) `env` yedeğine düşer: mevcut
 * dağıtımlar sunucu `.env`'iyle çalışmaya devam eder, kasa doldurulduğu an
 * onun önüne geçer. Öncelik: KASA > env.
 */
interface CredentialResolverPort
{
    /**
     * @return array<string, string> Alan adı → çözülmüş değer. Hiç yapılandırma
     *                               yoksa boş dizi. Değerler SIRDIR — log'a,
     *                               cevaba, denetim metnine yazılmaz.
     */
    public function resolve(CredentialProvider $provider): array;

    /**
     * BİR TENANT İÇİN çözer — `docs/95` Faz 3, `docs/14` §2a.
     *
     * `resolve()`'dan üç farkı var ve üçü de çok-hesap dünyasında zorunlu:
     * tenant kendi anahtarını (BYOK) getirdiyse o kullanılır; seçim
     * YAPIŞKANDIR (aynı tenant hep aynı hesaba gider, çünkü prompt
     * önbelleği hesaba bağlı); ve dönen nesne hangi bağlantının seçildiğini
     * söyler, böylece çağrı başarısız olduğunda "hangi hesap düştü"
     * yanıtlanabilir.
     */
    public function resolveFor(int $workspaceId, CredentialProvider $provider, string $purpose = 'interactive'): ResolvedCredential;

    public function isConfigured(CredentialProvider $provider): bool;
}
