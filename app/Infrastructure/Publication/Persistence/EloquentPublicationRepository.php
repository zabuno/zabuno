<?php

declare(strict_types=1);

namespace App\Infrastructure\Publication\Persistence;

use App\Application\Entitlement\Port\EntitlementRepositoryPort;
use App\Application\Publication\Dto\PublicationRecord;
use App\Application\Publication\Exception\PublicationPersistenceFailedException;
use App\Application\Publication\Port\PublicationRepositoryPort;
use App\Domain\Publication\MenuIndexability;
use App\Domain\Publication\PublicationStatus;
use Illuminate\Database\QueryException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use JsonException;
use Throwable;

final class EloquentPublicationRepository implements PublicationRepositoryPort
{
    /**
     * Plan HAKLARI, yayın anında dondurulmak üzere burada okunur
     * (`docs/114` §3 Dalga 6).
     *
     * Dondurmanın yeri BURASI'dır, çağıran denetleyiciler değil, ve bu
     * bilinçli: bugün üç ayrı yol yayın yapıyor (elle yayınlama, eski
     * sürüme dönüş, zamanlanmış yayın). Her birine "planı da yaz" demek,
     * dördüncü yol eklendiği gün sessizce unutulacak bir hatırlatmaydı —
     * ve unutulduğunda hiçbir test kırılmaz, yalnız bir restoranın
     * planını düşürmesi masadaki menüyü anında değiştirirdi.
     *
     * Eski sürüme DÖNÜŞ de bugünün planını dondurur ve bu doğrudur: geri
     * yükleme bir yayındır, ve bugün yapılır.
     */
    public function __construct(private readonly EntitlementRepositoryPort $entitlements) {}

    public function publish(int $workspaceId, int $menuId, int $locationId, array $snapshot, int $publishedByUserId): PublicationRecord
    {
        try {
            $frozenEntitlements = $this->entitlements->forWorkspace($workspaceId)->keys();

            return DB::transaction(function () use ($workspaceId, $menuId, $locationId, $snapshot, $publishedByUserId, $frozenEntitlements): PublicationRecord {
                DB::table('menus')->where('id', $menuId)->lockForUpdate()->first();

                $nextVersion = ((int) DB::table('menu_publications')->where('menu_id', $menuId)->max('version')) + 1;

                DB::table('menu_publications')
                    ->where('menu_id', $menuId)
                    ->where('state', PublicationStatus::Published->value)
                    ->update(['state' => PublicationStatus::Superseded->value, 'updated_at' => now()]);

                $publishedAt = now();

                $id = (int) DB::table('menu_publications')->insertGetId([
                    'workspace_id' => $workspaceId,
                    'menu_id' => $menuId,
                    'location_id' => $locationId,
                    'version' => $nextVersion,
                    'state' => PublicationStatus::Published->value,
                    'snapshot' => json_encode($snapshot, JSON_THROW_ON_ERROR),
                    // `snapshot`'ın İÇİNE yazılmaz: snapshot misafire çizilen
                    // MENÜ İÇERİĞİDİR ve yapılandırılmış veriye kadar oradan
                    // türer. Plan menü içeriği değil, yayının yapıldığı
                    // koşuldur; aynı kaba koymak bir plan değişikliğini bir
                    // gün "menü değişti" gibi gösterirdi.
                    'entitlements' => json_encode($frozenEntitlements, JSON_THROW_ON_ERROR),
                    'published_by' => $publishedByUserId,
                    'published_at' => $publishedAt,
                    'created_at' => $publishedAt,
                    'updated_at' => $publishedAt,
                ]);

                // İndekslenebilirlik HER yayında yeniden hesaplanır.
                // Bir kez açılıp öylece kalsaydı, ürünlerini kaldırıp
                // yeniden yayınlayan bir restoranın boş menüsü arama
                // sonuçlarında kalmaya devam ederdi.
                DB::table('menus')->where('id', $menuId)->update([
                    'is_indexable' => MenuIndexability::isIndexable($snapshot),
                    'updated_at' => $publishedAt,
                ]);

                $pointerExists = DB::table('menu_publication_current_pointers')->where('menu_id', $menuId)->exists();

                if ($pointerExists) {
                    DB::table('menu_publication_current_pointers')
                        ->where('menu_id', $menuId)
                        ->update(['current_publication_id' => $id, 'updated_at' => $publishedAt]);
                } else {
                    DB::table('menu_publication_current_pointers')->insert([
                        'menu_id' => $menuId,
                        'current_publication_id' => $id,
                        'created_at' => $publishedAt,
                        'updated_at' => $publishedAt,
                    ]);
                }

                return new PublicationRecord(
                    $id,
                    $workspaceId,
                    $menuId,
                    $locationId,
                    $nextVersion,
                    PublicationStatus::Published->value,
                    $publishedAt->toISOString(),
                    $snapshot,
                    $frozenEntitlements,
                );
            });
        } catch (QueryException|JsonException $e) {
            throw PublicationPersistenceFailedException::fromPrevious($e);
        } catch (Throwable $e) {
            if ($e instanceof PublicationPersistenceFailedException) {
                throw $e;
            }

            throw PublicationPersistenceFailedException::fromPrevious($e);
        }
    }

    public function history(int $workspaceId, int $menuId): array
    {
        $liveId = (int) DB::table('menu_publication_current_pointers')
            ->where('menu_id', $menuId)
            ->value('current_publication_id');

        return DB::table('menu_publications')
            ->where('workspace_id', $workspaceId)
            ->where('menu_id', $menuId)
            // EN YENİ ÖNCE: geri almayı arayan sahip panik hâlindedir ve
            // listenin dibine inmez (`docs/81`).
            ->orderByDesc('version')
            ->get()
            ->map(fn (object $row): PublicationRecord => $this->hydrate($row))
            ->all();
    }

    public function find(int $workspaceId, int $menuId, int $publicationId): ?PublicationRecord
    {
        $row = DB::table('menu_publications')
            ->where('id', $publicationId)
            ->where('workspace_id', $workspaceId)
            // Menü de KONTROL EDİLİR: aynı çalışma alanındaki başka bir
            // menünün yayınını buraya geri yüklemek, iki menüyü birbirine
            // karıştırmak olurdu.
            ->where('menu_id', $menuId)
            ->first();

        return $row === null ? null : $this->hydrate($row);
    }

    private function hydrate(object $row): PublicationRecord
    {
        /** @var array<string,mixed> $snapshot */
        $snapshot = json_decode((string) $row->snapshot, true, 512, JSON_THROW_ON_ERROR);

        return new PublicationRecord(
            (int) $row->id,
            (int) $row->workspace_id,
            (int) $row->menu_id,
            (int) $row->location_id,
            (int) $row->version,
            (string) $row->state,
            Carbon::parse($row->published_at)->toISOString(),
            $snapshot,
            $this->frozenEntitlements($row),
        );
    }

    /**
     * Donmuş plan hakları; alan eklenmeden önceki yayınlar için `null`.
     *
     * Boş bir liste ile `null` AYNI ŞEY DEĞİLDİR ve karıştırılamaz: boş
     * liste "o gün hiçbir hak yoktu" der, `null` ise "o gün ne olduğu
     * bilinmiyor" der. İkincisini boş liste saymak, eski yayınları
     * geriye dönük olarak haksız ilan etmek olurdu.
     *
     * @return list<string>|null
     */
    private function frozenEntitlements(object $row): ?array
    {
        if (! property_exists($row, 'entitlements') || $row->entitlements === null) {
            return null;
        }

        $decoded = json_decode((string) $row->entitlements, true);

        if (! is_array($decoded)) {
            return null;
        }

        return array_values(array_filter($decoded, 'is_string'));
    }

    public function current(int $workspaceId, int $menuId): ?PublicationRecord
    {
        $pointer = DB::table('menu_publication_current_pointers')->where('menu_id', $menuId)->first();

        if ($pointer === null) {
            return null;
        }

        $row = DB::table('menu_publications')
            ->where('id', $pointer->current_publication_id)
            ->where('workspace_id', $workspaceId)
            ->first();

        if ($row === null) {
            return null;
        }

        /** @var array{categories: list<array{name:string,menuItems:list<array{productName:string,priceMinorAmount:int,currencyCode:string,allergens:list<string>}>}>} $snapshot */
        $snapshot = json_decode((string) $row->snapshot, true, 512, JSON_THROW_ON_ERROR);

        return new PublicationRecord(
            (int) $row->id,
            (int) $row->workspace_id,
            (int) $row->menu_id,
            (int) $row->location_id,
            (int) $row->version,
            (string) $row->state,
            Carbon::parse($row->published_at)->toISOString(),
            $snapshot,
            /*
                DONMUŞ HAK BURADA DA TAŞINIR.

                Bu metot kendi `PublicationRecord`'unu kuruyor ve alan
                eklendiğinde atlanmıştı: sonuç, misafirin gördüğü yayının
                planını HER ZAMAN `null` sanmak, yani her sipariş isteğinde
                sessizce CANLI plana düşmekti. Sahip planını düşürdüğü an
                masadaki basılı karekod çalışmayı bırakırdı — dondurmanın
                var oluş sebebinin tam tersi.

                Kusur görünmezdi çünkü canlı plan çoğu zaman aynı cevabı
                verir; yalnız plan DEĞİŞTİĞİNDE ayrışır.
            */
            $this->frozenEntitlements($row),
        );
    }
}
