<?php

declare(strict_types=1);

namespace App\Application\Platform\Port;

use App\Domain\Platform\Credential\CredentialProvider;

/**
 * HANGİ HESAP? — `skills/ai-account-routing.md`, `docs/14` §2a.
 *
 * Bu port bir isteğin hangi bağlantıya gideceğine karar verir. Üç kural
 * taşır ve üçü de "iyi olur" değil, maliyet/izolasyon gerçeğidir:
 *
 *   1. **Yapışkanlık** — bir tenant'ın ilk isteği hangi bağlantıya giderse
 *      sonrakiler de oraya gider. Rastgele dağıtım yasak, çünkü prompt
 *      önbelleği ve oturum bağlamı hesaba bağlıdır; dağıtmak her seferinde
 *      soğuk önbellekle çalışmak demektir.
 *   2. **BYOK önce** — tenant kendi anahtarını getirdiyse o kullanılır;
 *      aksi hâlde anahtarı girmenin hiçbir etkisi olmazdı.
 *   3. **Sağlıksız düşer** — ama SİLİNMEZ. Havuzdan çıkışı geçicidir ve
 *      denetime yazılır; kalıcı iptal insan kararıdır.
 *
 * Bu port hesap OLUŞTURMAZ/SİLMEZ/ROTASYONA SOKMAZ ve kota aşmak için
 * otomatik hesap değiştirmez — ikisi de skill dosyasında kesin yasak.
 */
interface AccountRoutingPort
{
    /**
     * Sıralı aday bağlantı kimlikleri — yapışkan olan başta.
     *
     * @return list<int>
     */
    /*
        AMAÇ boyutu (`docs/97` R30, FF-75): toplu trafik (`batch`) etkileşimli
        trafikten ayrı yapışır; `plain_fields.purpose = batch` etiketli bir
        bağlantı varsa toplu iş önce oraya gider. Yeni bir hesap YARATMAZ,
        kota aşmak için hesap DEĞİŞTİRMEZ — skill yasağı aynen durur.
    */
    public function candidates(int $workspaceId, CredentialProvider $provider, string $purpose = 'interactive'): array;

    /** Seçimi kalıcılaştırır — bir sonraki istek aynı yere gitsin diye. */
    public function remember(int $workspaceId, CredentialProvider $provider, int $connectionId, string $purpose = 'interactive'): void;

    public function markHealthy(int $connectionId): void;

    public function markUnhealthy(int $connectionId): void;
}
