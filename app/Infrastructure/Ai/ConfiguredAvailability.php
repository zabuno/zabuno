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
     * Listede olmayan yetenekler (bugün: gömme, sınıflandırma) yalnız
     * config aday listesine bağlı kalır.
     */
    private function vaultServes(Capability $capability): bool
    {
        if (! in_array($capability, [Capability::MenuExtract, Capability::OcrDocument, Capability::ProductDescription, Capability::TextEmbedding], true)) {
            return false;
        }

        return $this->credentials->isConfigured(CredentialProvider::OpenAi)
            || $this->credentials->isConfigured(CredentialProvider::Gemini);
    }
}
