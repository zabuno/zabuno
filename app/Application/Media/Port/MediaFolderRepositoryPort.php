<?php

declare(strict_types=1);

namespace App\Application\Media\Port;

use App\Application\Media\Dto\MediaFolderSummary;

/**
 * Klasör deposu — `docs/108` §3 madde 1.
 *
 * Her imzada `workspaceId` ilk parametredir ve isteğe bağlı değildir.
 * Kiracı yalıtımı burada YAPISALDIR: "kimliği bilirsen erişirsin" olan bir
 * imza, çağıran her yerde ayrı ayrı hatırlanması gereken bir kural
 * yaratırdı; unutulan tek çağrı da bir restoranın klasörünü başka bir
 * restorana açardı.
 */
interface MediaFolderRepositoryPort
{
    /**
     * Kenar çubuğu sırası: her kök klasör, hemen ardından kendi çocukları
     * (`position`, sonra kimlik). Girinti bu sıraya bakarak çizilir.
     *
     * @return list<MediaFolderSummary>
     */
    public function listForWorkspace(int $workspaceId): array;

    public function find(int $workspaceId, int $folderId): ?MediaFolderSummary;

    /** Aynı üst klasör altında aynı ad var mı? Yeniden adlandırmada kendisi hariç tutulur. */
    public function nameTaken(int $workspaceId, string $name, ?int $parentId, ?int $exceptFolderId = null): bool;

    public function create(int $workspaceId, string $name, ?int $parentId): MediaFolderSummary;

    /** Yalnız insanın okuduğu ad değişir; dosyalar ve bağlar yerinde kalır. */
    public function rename(int $workspaceId, int $folderId, string $name): bool;

    public function hasChildren(int $workspaceId, int $folderId): bool;

    /**
     * Klasörü kaldırır ve içindeki dosyaları SERBEST bırakır (klasörsüz
     * kalırlar, "Tümü"de görünmeye devam ederler). Döner: serbest bırakılan
     * dosya sayısı. Hiçbir dosya silinmez.
     */
    public function deleteAndReleaseFiles(int $workspaceId, int $folderId): int;

    /**
     * Varlığı klasöre taşır; `null` klasörden çıkarır. Varlık bu kiracıya
     * ait değilse `false` döner — çapraz kiracı taşıma sessizce başarılı
     * görünmemelidir.
     */
    public function moveAsset(int $workspaceId, int $assetId, ?int $folderId): bool;
}
