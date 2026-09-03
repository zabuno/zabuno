<?php

declare(strict_types=1);

namespace App\Infrastructure\Platform\Credential;

use App\Application\Platform\Port\AccountRoutingPort;
use App\Domain\Platform\Credential\ConnectionHealth;
use App\Domain\Platform\Credential\CredentialProvider;
use App\Domain\Platform\Credential\CredentialScope;
use Illuminate\Support\Facades\DB;

/**
 * Yapışkan hesap yönlendirmesi — `skills/ai-account-routing.md`.
 *
 * Kasa deposuna BAĞIMLI DEĞİL, kasıtlı olarak: depo bu yönlendiriciyi
 * kullanıyor, bu yönlendirici de depoyu kullansaydı döngü olurdu. Burası
 * yalnız "hangi bağlantı sırayla denensin" sorusunu yanıtlar; bir
 * bağlantının zorunlu alanlarının tam olup olmadığı deponun bileceği iştir.
 *
 * Sıralama üç kuraldan doğar ve sırası önemlidir:
 *   1. YAPIŞKAN olan başa alınır (varsa ve hâlâ aday listesindeyse).
 *   2. BYOK, platform hesabından ÖNCE gelir — müşteri kendi anahtarını
 *      getirdiyse faturası ona gitmeli, yoksa girmenin anlamı olmazdı.
 *   3. Geri kalanlar en eskiden yeniye — belirlenebilir, rastgele değil.
 */
final readonly class StickyAccountRouter implements AccountRoutingPort
{
    private const CONNECTIONS = 'platform_credential_connections';

    private const ASSIGNMENTS = 'ai_connection_assignments';

    private const AUDITS = 'platform_credential_audits';

    public function candidates(int $workspaceId, CredentialProvider $provider): array
    {
        /*
            Aday havuzu SORGUDA daralır, bir `if` ile değil: başka bir
            tenant'ın BYOK satırı buraya hiç gelmez. Filtre unutulabilir,
            `where` unutulursa test kırılır.
        */
        $query = DB::table(self::CONNECTIONS)
            ->where('provider', $provider->value)
            ->where('state', 'active')
            ->where('health_status', '!=', ConnectionHealth::Unhealthy->value);

        /*
            ÖZEL UÇ NOKTA, DENENMEDEN ADAY OLMAZ — `docs/51` §4.5:
            "hangi portları desteklediği test edilmeden aday olmaz".

            Bilinen sağlayıcılar için "henüz sınanmadı" aday olmaya yeter:
            OpenAI'ın adresini ve sözleşmesini biliyoruz. Ama özel uç nokta
            superadmin'in yazdığı KEYFİ bir adrestir — ne konuştuğunu
            bilmiyoruz. Sınanmamış böyle bir adrese üretim trafiği
            göndermek, müşterinin menüsünü tanımadığımız bir sunucuya
            yollamak olurdu.
        */
        if ($provider === CredentialProvider::CustomEndpoint) {
            $query->where('health_status', ConnectionHealth::Healthy->value);
        }

        $rows = $query
            ->where(function ($query) use ($workspaceId): void {
                $query->where('scope', CredentialScope::PlatformOwned->value)
                    ->orWhere(function ($inner) use ($workspaceId): void {
                        $inner->where('scope', CredentialScope::TenantByok->value)
                            ->where('workspace_id', $workspaceId);
                    });
            })
            // BYOK önce: 'tenant_byok' < 'platform_owned' alfabetik olarak
            // doğru sırayı vermez, bu yüzden açık bir sıralama ifadesi.
            ->orderByRaw("case when scope = '".CredentialScope::TenantByok->value."' then 0 else 1 end")
            ->orderBy('id')
            ->pluck('id')
            ->map(static fn ($id): int => (int) $id)
            ->all();

        $sticky = DB::table(self::ASSIGNMENTS)
            ->where('workspace_id', $workspaceId)
            ->where('provider', $provider->value)
            ->value('connection_id');

        if ($sticky === null) {
            return $rows;
        }

        $sticky = (int) $sticky;
        $index = array_search($sticky, $rows, true);

        if ($index === false) {
            // Yapıştığı bağlantı artık aday değil (kapandı ya da sağlıksız).
            // Yeni bir seçim yapılacak; eşleme `remember()` ile güncellenir.
            return $rows;
        }

        unset($rows[$index]);

        return array_values(array_merge([$sticky], $rows));
    }

    public function remember(int $workspaceId, CredentialProvider $provider, int $connectionId): void
    {
        DB::table(self::ASSIGNMENTS)->updateOrInsert(
            ['workspace_id' => $workspaceId, 'provider' => $provider->value],
            ['connection_id' => $connectionId, 'updated_at' => now(), 'created_at' => now()],
        );
    }

    public function markHealthy(int $connectionId): void
    {
        $this->setHealth($connectionId, ConnectionHealth::Healthy);
    }

    public function markUnhealthy(int $connectionId): void
    {
        $this->setHealth($connectionId, ConnectionHealth::Unhealthy);
    }

    private function setHealth(int $connectionId, ConnectionHealth $health): void
    {
        $row = DB::table(self::CONNECTIONS)->where('id', $connectionId)->first();

        if ($row === null) {
            return;
        }

        $previous = (string) ($row->health_status ?? ConnectionHealth::Unknown->value);

        DB::table(self::CONNECTIONS)->where('id', $connectionId)->update([
            'health_status' => $health->value,
            'last_health_check_at' => now(),
            'updated_at' => now(),
        ]);

        // Yalnız DEĞİŞİM denetime yazılır. "Hâlâ sağlıklı" bir olay değildir;
        // her yoklamayı yazsaydık gerçek olay — bir hesabın düşmesi —
        // gürültüde kaybolurdu (`docs/14` §2a).
        if ($previous === $health->value) {
            return;
        }

        DB::table(self::AUDITS)->insert([
            'provider' => (string) $row->provider,
            'connection_id' => $connectionId,
            'action' => 'health:'.$health->value,
            'actor_user_id' => null,
            'created_at' => now(),
        ]);
    }
}
