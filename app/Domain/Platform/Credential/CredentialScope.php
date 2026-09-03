<?php

declare(strict_types=1);

namespace App\Domain\Platform\Credential;

/**
 * Bir bağlantı KİMİN — `docs/95` Faz 3, `modules/ai-provider-account-vault.md`.
 *
 * İki kapsam ayrı bir alan olarak durur, bir bayrak ya da "workspace_id
 * doluysa BYOK'tur" çıkarımı olarak değil: çıkarım, sorgunun bir yerinde
 * unutulabilir. Ayrı bir kapsam alanı, "platform hesabı" ile "tenant'ın
 * kendi anahtarı" arasındaki sınırı sorguda GÖRÜNÜR kılar.
 */
enum CredentialScope: string
{
    /** Platformun kendi hesabı — her tenant'a hizmet edebilir. */
    case PlatformOwned = 'platform_owned';

    /**
     * Tenant'ın kendi anahtarı (BYOK). Yalnız o tenant'ın isteklerine
     * hizmet eder ve başka bir tenant'ın aday listesinde ASLA görünmez —
     * yapısal izolasyon, filtre değil (`docs/95` Faz 3 §BYOK).
     */
    case TenantByok = 'tenant_byok';
}
