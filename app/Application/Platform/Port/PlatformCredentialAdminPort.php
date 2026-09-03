<?php

declare(strict_types=1);

namespace App\Application\Platform\Port;

use App\Domain\Platform\Credential\CredentialProvider;
use App\Domain\Platform\Credential\CredentialStatus;

/**
 * Superadmin panelinin kasayla konuştuğu YÜZEYİN TAMAMI.
 *
 * Bu portta bilerek `reveal`/`read` YOKTUR. Panel bir sırrı yazabilir,
 * durumunu maskeli görebilir, döndürebilir ve kapatabilir — ama tam değeri
 * GERİ OKUYAMAZ. Sırrı çözmek ayrı bir porttadır (`CredentialResolverPort`)
 * ve yalnız tüketici altyapı (posta, AI adaptörü) onu kullanır. Ayrım yapısal:
 * HTTP katmanı fiziksel olarak sırra erişemez.
 */
interface PlatformCredentialAdminPort
{
    /** @return list<CredentialStatus> Her sağlayıcı için maskeli durum. */
    public function all(): array;

    public function status(CredentialProvider $provider): CredentialStatus;

    /**
     * Bir sağlayıcının alanlarını yazar/döndürür (rotate).
     *
     * Yalnız verilen sır alanlar güncellenir; boş bırakılan bir sır alan
     * öncekini KORUR — panel var olan sırrı geri okuyamadığı için, onu her
     * kayıtta yeniden girmek zorunda kalmamalı.
     *
     * @param  array<string, string>  $values  Alan adı → değer (yalnız şemadaki adlar)
     */
    public function put(CredentialProvider $provider, array $values, ?int $byUserId): void;

    public function disable(CredentialProvider $provider): void;
}
