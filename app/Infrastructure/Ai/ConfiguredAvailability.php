<?php

declare(strict_types=1);

namespace App\Infrastructure\Ai;

use App\Application\Ai\Port\AiAvailability;
use App\Application\Ai\Port\AiAvailabilityPort;
use App\Application\Platform\Port\CredentialResolverPort;
use App\Domain\Ai\Capability;
use App\Domain\Platform\Credential\CredentialProvider;

/**
 * Kullanılabilirlik kararı — sırası ÖNEMLİ.
 *
 * Kapatma anahtarı önce bakılır: bütçe hesabı yapmak, kapalı bir sistemde
 * gereksiz sorgu demektir. Sonra rota, sonra bütçe — çünkü aday modeli
 * olmayan bir yetenek için bütçe harcamak da anlamsızdır.
 */
final readonly class ConfiguredAvailability implements AiAvailabilityPort
{
    public function __construct(
        private AiBudgetLedger $budget,
        private CredentialResolverPort $credentials,
    ) {}

    public function isAvailable(int $workspaceId, Capability $capability): AiAvailability
    {
        if (config('ai.enabled') !== true) {
            return AiAvailability::KillSwitch;
        }

        /*
            YETENEK ADLARI NOKTA İÇERİR — `config()` onları iç içe anahtar sanar.

            `config("ai.capabilities.menu.extract.candidates")` çağrısı
            `capabilities → menu → extract → candidates` yolunu arar; oysa
            gerçek anahtar `'menu.extract'` düz metnidir. Dört yeteneğin
            DÖRDÜ de nokta taşıdığı için bu çağrı HER ZAMAN varsayılana
            düşüyordu: sağlayıcı tam yapılandırılmış olsa bile cevap
            "rota yok" olurdu.

            Kusur bugün görünmüyordu çünkü AI kapalı ve gerçek sağlayıcı yok;
            ilk kez anahtar girildiği gün, "para ödedik ama çalışmıyor"
            olarak ortaya çıkardı (`docs/92`).

            Dizi bir kez okunur, sonra DÜZ anahtarla indekslenir.
        */
        $capabilities = (array) config('ai.capabilities', []);
        $candidates = (array) ($capabilities[$capability->value]['candidates'] ?? []);

        // Rota iki kaynaktan gelebilir: config aday listesi VEYA kasada
        // yapılandırılmış, bu yeteneğe bakan bir sağlayıcı. Superadmin UI'dan
        // OpenAI anahtarı girdiğinde config'e dokunmadan rota açılmalı.
        if ($candidates === [] && ! $this->vaultServes($capability)) {
            return AiAvailability::NoRoute;
        }

        if (! $this->budget->hasRemaining($workspaceId)) {
            return AiAvailability::BudgetExhausted;
        }

        return AiAvailability::Available;
    }

    /**
     * Kasada bu yeteneğe bakan, açık bir sağlayıcı var mı?
     *
     * BURAYA EKLENMEYEN bir yetenek, kasada anahtar olsa bile `NoRoute`
     * döner — `docs/96` Faz 2'de bulunan gerçek arıza tam buydu: yeni bir
     * `StructuredGenerationPort`/`VisionExtractionPort` adaptörü yazılıp
     * kasaya bağlandığında, o yeteneğin adı burada da listelenmezse "para
     * ödedik ama çalışmıyor" sessizce geri döner (FF-34'ün düzelttiği
     * dotted-config-key sınıfıyla aynı arıza biçimi). Yeni bir gerçek
     * adaptör her eklendiğinde bu liste GÜNCELLENMELİDİR.
     *
     * Listede olmayan yetenekler (bugün: sınıflandırma, yeniden sıralama)
     * yalnız config aday listesine bağlı kalır.
     */
    private function vaultServes(Capability $capability): bool
    {
        /*
            EŞLEME YETENEĞE GÖRE, SAĞLAYICIYA GÖRE DEĞİL — ve bu ayrım
            Faz 3'te zorunlu hâle geldi.

            Anthropic/Kimi/özel uç noktanın YALNIZ metin adaptörü var
            (`StructuredGenerationPort`); görüntü okuyamazlar, gömme
            üretemezler. Hepsini tek bir listeye koymak, yalnız Kimi
            anahtarı girilmiş bir kurulumda "fotoğraftan menü oku"
            eylemini AÇIK gösterirdi — kullanıcı basar, arkada o yeteneği
            karşılayan hiçbir adaptör olmadığı için sahte üretici devreye
            girerdi. Bu, docblock'un uyardığı arızanın tersi: rota açık
            görünür, çağrı yapacak kod yoktur.
        */
        $servingProviders = match ($capability) {
            // Görme: yalnız gerçek görüntü adaptörü olanlar.
            Capability::MenuExtract, Capability::OcrDocument => [
                CredentialProvider::Gemini,
                CredentialProvider::OpenAi,
            ],
            // Şemaya bağlı metin: Faz 3'te dört adayı var.
            Capability::ProductDescription => [
                CredentialProvider::Gemini,
                CredentialProvider::Anthropic,
                CredentialProvider::Kimi,
                CredentialProvider::CustomEndpoint,
            ],
            // Gömme: bugün yalnız Gemini (`docs/51` §4.4 geçici bulut yedeği).
            Capability::TextEmbedding => [CredentialProvider::Gemini],
            default => [],
        };

        foreach ($servingProviders as $provider) {
            if ($this->credentials->isConfigured($provider)) {
                return true;
            }
        }

        return false;
    }
}
