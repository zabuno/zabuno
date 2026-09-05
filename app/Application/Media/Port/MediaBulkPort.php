<?php

declare(strict_types=1);

namespace App\Application\Media\Port;

use App\Application\Media\Dto\MediaBulkCandidate;

/**
 * TOPLU İŞLEMİN depo yüzü — `docs/109-PANEL-V3.md` §2 "Toplu işlem".
 *
 * `MediaRepositoryPort`e eklenmedi ve bu bilinçli: o arayüz tek bir
 * varlığın hayatını (alma, tarama, işleme, sürüm, çöp) tarif eder ve
 * zaten uzundur. Toplu işlemin sorduğu sorular başkadır — "bu kapsamda
 * hangi kimlikler var", "bu kimliklerin hepsi için karar verecek bilgiyi
 * TEK sorguda ver" — ve bunları oraya karıştırmak, tek varlık yolunu
 * kullanan on beş çağıranı ilgilendirmeyen bir yüzeyle büyütürdü.
 *
 * Bütün metotlar `workspaceId` ALIR ve onunla süzer: kiracı sınırı
 * çağıranın hatırlaması gereken bir şey değil, arayüzün kendi biçimidir.
 */
interface MediaBulkPort
{
    /**
     * Bir kapsamın DONDURULMUŞ kimlik listesi.
     *
     * Kaynağın değişmez kuralı: "İş başladığı anda liste dondurulur."
     * Bu yüzden kapsam bir SORGU olarak taşınmaz, kimlik listesi olarak
     * taşınır — plan ile çalıştırma arasında yüklenen dosya işe girmez.
     *
     * @param  'workspace'|'folder'  $scope
     * @return list<int>
     */
    public function idsForScope(int $workspaceId, string $scope, ?int $folderId, bool $trashed): array;

    /**
     * Verilen kimliklerin karar bilgisi — YALNIZ bu çalışma alanınınkiler.
     *
     * Yabancı bir kimlik sessizce DÜŞER: "yok" demek yeterlidir, "başka
     * bir kiracıda var" bilgisi hiçbir cevaptan sızmaz.
     *
     * @param  list<int>  $assetIds
     * @return list<MediaBulkCandidate>
     */
    public function candidates(int $workspaceId, array $assetIds, bool $trashed): array;

    /**
     * ÇÖPTEKİ varlıkları kalıcı siler: dosya, türevleri ve satır.
     * Döner: gerçekten silinen sayı.
     *
     * Süre penceresine BAKMAZ — `purgeTrash` zamanı gelen çöpü temizler,
     * bu ise sahibin bilerek ve yazarak istediği silmedir. Yayında
     * kullanılan ya da yasal saklamadaki varlığa yine de dokunmaz: o iki
     * kilit sahibin isteğinden üstündür.
     *
     * @param  list<int>  $assetIds
     */
    public function purgeAssets(int $workspaceId, array $assetIds): int;

    /**
     * Toplu iş kaydını yazar ve kimliğini döner; aynı anahtar zaten varsa
     * `null` döner ve HİÇBİR şey yazılmaz.
     *
     * Tekillik çağıranda değil burada kapanır: iki eşzamanlı istek
     * arasındaki yarışı yalnız veritabanının kendi kısıtı kapatır.
     *
     * @param  array{planned:int, applied:int, skipped:int, failed:int}  $counts
     */
    public function recordOperation(
        int $workspaceId,
        string $operationKey,
        string $action,
        string $scope,
        array $counts,
        ?int $actorUserId,
    ): ?int;

    /** Bu anahtarla daha önce çalışmış bir iş var mı? */
    public function operationExists(int $workspaceId, string $operationKey): bool;

    /**
     * Denetim izinin TOPLU yarısı — en yeni önce.
     *
     * @return list<array{operationKey:string, action:string, scope:string, applied:int, skipped:int, failed:int, actor:?string, at:?string}>
     */
    public function recentOperations(int $workspaceId, int $limit = 25): array;
}
