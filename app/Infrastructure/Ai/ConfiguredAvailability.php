<?php

declare(strict_types=1);

namespace App\Infrastructure\Ai;

use App\Application\Ai\Port\AiAvailability;
use App\Application\Ai\Port\AiAvailabilityPort;
use App\Domain\Ai\Capability;

/**
 * Kullanılabilirlik kararı — sırası ÖNEMLİ.
 *
 * Kapatma anahtarı önce bakılır: bütçe hesabı yapmak, kapalı bir sistemde
 * gereksiz sorgu demektir. Sonra rota, sonra bütçe — çünkü aday modeli
 * olmayan bir yetenek için bütçe harcamak da anlamsızdır.
 */
final readonly class ConfiguredAvailability implements AiAvailabilityPort
{
    public function __construct(private AiBudgetLedger $budget) {}

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

        if ($candidates === []) {
            return AiAvailability::NoRoute;
        }

        if (! $this->budget->hasRemaining($workspaceId)) {
            return AiAvailability::BudgetExhausted;
        }

        return AiAvailability::Available;
    }
}
