<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use App\Application\Platform\Port\CredentialResolverPort;
use App\Application\Platform\Port\PlatformCredentialAdminPort;
use App\Domain\Platform\Credential\CredentialProvider;
use App\Domain\Platform\Credential\CredentialStatus;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * FAZ 3 SAĞLAYICI KAYDI — `docs/95` "Sorulan listede olup henüz eksik".
 *
 * Sahibin sorduğu liste (Claude/Anthropic, Kimi K3, Qwen) doktrine üç FARKLI
 * biçimde oturur ve bu ayrım kozmetik değildir:
 *
 *   • Anthropic ve Kimi kendi başlarına sağlayıcıdır — `docs/51` §3.2 ikisini
 *     de adıyla anıyor.
 *   • **Qwen bir sağlayıcı DEĞİLDİR.** Doktrin onu `local`/self-host/
 *     OpenAI-uyumlu-uç-nokta sınıfına koyar (`docs/51` §3.2, §4.5). Onu
 *     "Gemini gibi" modellemek, o hesabın sağlık/kota davranışının
 *     OpenAI-uyumlu olduğunu VARSAYARDI — ama uyumluluk garanti değildir
 *     (`docs/51` §4.5 "tam uyumluluk varsayılmaz"). Doğru model: genel bir
 *     "özel uç nokta" sınıfı; superadmin Qwen'in kendi adresini `base_url`
 *     alanına yazar.
 */
final class Faz3ProviderRegistryTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): PlatformCredentialAdminPort
    {
        return $this->app->make(PlatformCredentialAdminPort::class);
    }

    private function resolver(): CredentialResolverPort
    {
        return $this->app->make(CredentialResolverPort::class);
    }

    #[Test]
    public function the_vault_now_knows_anthropic_kimi_and_a_custom_endpoint(): void
    {
        $providers = array_map(
            static fn (CredentialStatus $s): string => $s->provider->value,
            $this->admin()->all(),
        );
        sort($providers);

        self::assertSame(
            ['anthropic', 'custom_endpoint', 'gemini', 'iyzico', 'kimi', 'mailgun', 'openai'],
            $providers,
        );
    }

    #[Test]
    public function anthropic_and_kimi_carry_a_key_and_an_overridable_base_url(): void
    {
        foreach ([CredentialProvider::Anthropic, CredentialProvider::Kimi] as $provider) {
            self::assertSame(['api_key', 'base_url'], $provider->fieldNames());
            self::assertSame(['api_key'], $provider->secretFieldNames());
            self::assertTrue($provider->field('api_key')?->required);
            // `base_url` opsiyoneldir ama VARSAYILANI vardır: bir bölge/proxy
            // adresi girmek isteyen superadmin'in önü açık kalsın, girmeyen
            // için de kasa yarım kalmasın.
            self::assertNotNull($provider->field('base_url')?->default);
        }
    }

    /**
     * ÖZEL UÇ NOKTADA ZORUNLU OLAN ADRESTİR, ANAHTAR DEĞİL.
     *
     * Kendi barındırdığı bir Qwen/vLLM sunucusu çoğu kurulumda anahtarsız
     * çalışır (ağ sınırında korunur). Anahtarı zorunlu kılmak, o kurulumu
     * kasaya hiç giremez hâle getirirdi; adresi zorunlu kılmamak ise
     * "yapılandırıldı" görünen ama nereye gideceği belli olmayan bir kayıt
     * üretirdi.
     */
    #[Test]
    public function the_custom_endpoint_requires_an_address_and_treats_the_key_as_optional(): void
    {
        $provider = CredentialProvider::CustomEndpoint;

        self::assertSame(['base_url', 'api_key'], $provider->fieldNames());
        self::assertTrue($provider->field('base_url')?->required);
        self::assertFalse($provider->field('api_key')?->required);
        self::assertSame(['api_key'], $provider->secretFieldNames());
        // Adresin VARSAYILANI olamaz: her kurulumun adresi kendine özgüdür,
        // uydurulmuş bir varsayılan sessizce yanlış bir yere çağrı yapardı.
        self::assertNull($provider->field('base_url')?->default);
    }

    #[Test]
    public function a_custom_endpoint_configured_with_only_an_address_resolves(): void
    {
        $this->admin()->put(
            CredentialProvider::CustomEndpoint,
            ['base_url' => 'https://qwen.internal.example/v1'],
            null,
        );

        self::assertTrue($this->resolver()->isConfigured(CredentialProvider::CustomEndpoint));
        self::assertSame(
            ['base_url' => 'https://qwen.internal.example/v1'],
            $this->resolver()->resolve(CredentialProvider::CustomEndpoint),
        );
    }

    #[Test]
    public function an_anthropic_key_is_encrypted_and_only_its_mask_comes_back(): void
    {
        $this->admin()->put(
            CredentialProvider::Anthropic,
            ['api_key' => 'sk-ant-not-a-real-key-9f42'],
            null,
        );

        $status = $this->admin()->status(CredentialProvider::Anthropic);
        $keyField = array_values(array_filter(
            $status->fields,
            static fn ($f): bool => $f->name === 'api_key',
        ))[0];

        self::assertTrue($status->configured);
        self::assertTrue($keyField->isSet);
        self::assertSame('••••9f42', $keyField->preview);
    }

    /**
     * TÜKETİCİ ABONELİĞİ ÜRETİM KİMLİK BİLGİSİ DEĞİLDİR.
     *
     * `modules/ai-provider-account-vault.md` §Tüketici abonelik yasağı:
     * Claude.ai Pro/Max ya da ChatGPT Plus girişi hiçbir koşulda kabul
     * edilmez — yalnız resmi API anahtarı. Şema bunu YAPISAL olarak zorlar:
     * kasada e-posta/parola/oturum çerezi alacak bir alan YOKTUR.
     */
    #[Test]
    public function no_provider_schema_offers_a_consumer_login_field(): void
    {
        $forbidden = ['email', 'password', 'username', 'session', 'cookie', 'session_key'];

        foreach (CredentialProvider::cases() as $provider) {
            foreach ($provider->fieldNames() as $name) {
                self::assertNotContains(
                    $name,
                    $forbidden,
                    "{$provider->value} şemasında tüketici-giriş alanı var: {$name}",
                );
            }
        }
    }
}
