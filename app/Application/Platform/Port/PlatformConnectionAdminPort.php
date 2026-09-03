<?php

declare(strict_types=1);

namespace App\Application\Platform\Port;

use App\Domain\Platform\Credential\ConnectionHealth;
use App\Domain\Platform\Credential\CredentialConnection;
use App\Domain\Platform\Credential\CredentialProvider;
use App\Domain\Platform\Credential\CredentialScope;

/**
 * Superadmin'in BAĞLANTILARLA konuştuğu yüzeyin tamamı — `docs/95` Faz 3.
 *
 * `PlatformCredentialAdminPort` bir sağlayıcının TEK kaydını yönetiyordu;
 * bu port aynı disiplini `Provider → Connection (N adet)` hiyerarşisine
 * taşır (`modules/ai-provider-account-vault.md`). İki port bilerek AYRI:
 * eski yüzey çalışan bir paneli besliyor ve onu kırmanın bir kazancı yok.
 *
 * Burada da `reveal`/`read` YOKTUR — sır yazılır, maskesi görünür, geri
 * okunamaz. Sırrı çözmek `CredentialResolverPort`'un işidir.
 */
interface PlatformConnectionAdminPort
{
    /**
     * @return list<CredentialConnection> Sağlayıcı verilirse yalnız onunkiler.
     */
    public function connections(?CredentialProvider $provider = null): array;

    public function connection(int $id): ?CredentialConnection;

    /**
     * Yeni bir bağlantı açar ve kimliğini döner.
     *
     * Etiket zorunludur: sır görünmediği için panelde iki kartı ayırt eden
     * tek şey odur. Kapsam ile workspace TUTARLI olmak zorundadır — BYOK
     * bir tenant adı taşımalı, platform hesabı taşımamalı; ikisi de
     * `InvalidArgumentException`.
     *
     * @param  array<string, string>  $values  Yalnız sağlayıcının şemasındaki adlar
     */
    public function createConnection(
        CredentialProvider $provider,
        string $label,
        CredentialScope $scope,
        ?int $workspaceId,
        array $values,
        ?int $byUserId,
    ): int;

    /**
     * Alanları yazar/döndürür. Verilmeyen bir sır alan öncekini KORUR —
     * panel onu geri okuyamadığı için her kayıtta yeniden girmek zorunda
     * kalmamalı.
     *
     * @param  array<string, string>  $values
     */
    public function updateConnection(int $id, array $values, ?int $byUserId): void;

    public function renameConnection(int $id, string $label, ?int $byUserId): void;

    /** Kapatır — SİLMEZ. Kayıt ve denetim izi yerinde kalır. */
    public function disableConnection(int $id, ?int $byUserId): void;

    public function enableConnection(int $id, ?int $byUserId): void;

    /**
     * Sağlık durumunu işaretler. Sağlıksız bağlantı aday havuzundan geçici
     * olarak düşer; otomatik silinmez — o insan kararıdır (`docs/95` Faz 3).
     */
    public function markHealth(int $id, ConnectionHealth $health, ?int $byUserId): void;
}
