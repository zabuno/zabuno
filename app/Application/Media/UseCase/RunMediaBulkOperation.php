<?php

declare(strict_types=1);

namespace App\Application\Media\UseCase;

use App\Application\Media\Dto\MediaBulkCandidate;
use App\Application\Media\Port\MediaAuditPort;
use App\Application\Media\Port\MediaBulkPort;
use App\Application\Media\Port\MediaFolderRepositoryPort;
use App\Application\Media\Port\MediaRepositoryPort;
use App\Domain\Media\MediaBulkAction;

/**
 * TOPLU İŞİN ÇALIŞTIRILMASI — kaynağın "Sonuç" adımı
 * (`docs/reference/panel-v3/MedyaModulu.dc.html`).
 *
 * Bu sınıf YENİ BİR İŞLEME HATTI DEĞİLDİR. Her eylem, o eylemin zaten var
 * olan tek-dosya yoluna gider: `ReprocessMediaAsset`, `moveAsset`,
 * `delete`, `purgeAssets`. Aynı gerekçe `ConvertMediaAssetsToFormat` ve
 * `RegenerateWorkspaceDerivatives` içinde de yazılıdır — "asıl korunuyor
 * mu / yeni sürüm açılıyor mu / iş kaydı düşüyor mu" soruları tek bir
 * yerde cevaplanmalı, toplu yolun kendi kopyasında değil.
 *
 * Üç davranış kaynağın kendi cümlelerinden gelir:
 *
 * 1. **Tek dosyanın hatası işi durdurmaz.** "İlk hatada dur" kaynakta bir
 *    SEÇENEKTİR ve varsayılanı kapalıdır; bozuk tek bir dosya yüzünden
 *    diğer kırk dokuzun işlenmemesi sahibin işini görmez. Hata SAYILIR ve
 *    dosya adıyla raporlanır.
 * 2. **Rapor dosya dosyadır.** "1.798 dosya işlendi" tek başına, hangi
 *    dosyanın neden atlandığını arayan sahibi karanlıkta bırakır.
 * 3. **Denetim kaydı iş bittikten SONRA yazılır.** Denenip olmamış bir
 *    şeyi kaydetmek yanlış olurdu (`DeleteMediaController` ile aynı kural).
 */
final class RunMediaBulkOperation
{
    public function __construct(
        private readonly PlanMediaBulkOperation $plan,
        private readonly ReprocessMediaAsset $reprocess,
        private readonly MediaRepositoryPort $media,
        private readonly MediaFolderRepositoryPort $folders,
        private readonly MediaBulkPort $bulk,
        private readonly MediaAuditPort $audit,
    ) {}

    /**
     * @param  list<int>  $assetIds  DONDURULMUŞ kapsam listesi
     * @param  array<string, mixed>  $config
     * @return array{
     *     applied:int, skipped:int, failed:int, remaining:int,
     *     results: list<array{id:int, name:string, status:string, reason:?string}>
     * }
     */
    public function __invoke(
        int $workspaceId,
        MediaBulkAction $action,
        array $assetIds,
        array $config,
        ?int $actorUserId,
    ): array {
        /*
            ÇALIŞTIRMA kendi sınıflandırmasını yapmaz, kuru çalışmanınkini
            YENİDEN çağırır. İki ayrı sınıflandırma, "önizlemede 20 dedi,
            24'ünü işledi" farkını mümkün kılardı — ve o fark, kuru
            çalışmayı tamamen değersiz kılan tek şeydir.
        */
        $plan = ($this->plan)($workspaceId, $action, $assetIds, $config);

        $results = [];
        $applied = 0;
        $failed = 0;

        foreach ($plan['skipped'] as $skip) {
            $results[] = [
                'id' => $skip['id'],
                'name' => $skip['name'],
                'status' => 'skip',
                'reason' => $skip['reason'],
            ];
        }

        $purgeIds = [];

        foreach ($plan['apply'] as $candidate) {
            if ($action === MediaBulkAction::Purge) {
                // Kalıcı silme TEK çağrıda toplanır: her dosya için ayrı
                // bir depo çağrısı, silme sırasını ve dosya temizliğini iki
                // yere bölerdi.
                $purgeIds[] = $candidate->id;

                continue;
            }

            $outcome = $this->applyOne($workspaceId, $action, $candidate, $config);

            if ($outcome === null) {
                $applied++;
                $results[] = [
                    'id' => $candidate->id,
                    'name' => $candidate->name,
                    'status' => 'ok',
                    'reason' => null,
                ];

                // Denetim izi VARLIK BAŞINA da yazılır: sahip altı ay sonra
                // "bu fotoğrafa ne oldu?" diye sorduğunda cevabı o
                // fotoğrafın kendi izinde bulmalı, "bir yerde toplu bir iş
                // çalıştı" cümlesinde değil.
                $this->audit->record($workspaceId, $candidate->id, $this->auditAction($action), $actorUserId);

                continue;
            }

            $failed++;
            $results[] = [
                'id' => $candidate->id,
                'name' => $candidate->name,
                'status' => 'error',
                'reason' => $outcome,
            ];
        }

        if ($purgeIds !== []) {
            $purged = $this->bulk->purgeAssets($workspaceId, $purgeIds);
            $applied += $purged;

            /*
                Depo kaç dosyayı gerçekten sildiğini söyler; ilk N tanesi
                değil, silinenler o sayıdır. Hangi dosyanın silindiğini
                satır satır iddia etmek, silinemeyen bir dosyayı silinmiş
                göstermek riskini taşır — o yüzden rapor sayıya dayanır ve
                fark HATA olarak yazılır.
            */
            foreach (array_slice($purgeIds, 0, $purged) as $index => $purgedId) {
                $results[] = [
                    'id' => $purgedId,
                    'name' => $plan['apply'][$index]->name,
                    'status' => 'ok',
                    'reason' => null,
                ];

                $this->audit->record($workspaceId, $purgedId, 'purged', $actorUserId);
            }

            $failed += count($purgeIds) - $purged;
        }

        return [
            'applied' => $applied,
            'skipped' => count($plan['skipped']),
            'failed' => $failed,
            'remaining' => count($plan['overflow']),
            'results' => $results,
        ];
    }

    /**
     * Tek dosyaya eylemi uygular. Döner: `null` başarı, aksi hâlde SEBEP
     * KODU — cümle değil, çünkü cümleyi ürün yazar (`docs/37`).
     *
     * @param  array<string, mixed>  $config
     */
    private function applyOne(
        int $workspaceId,
        MediaBulkAction $action,
        MediaBulkCandidate $candidate,
        array $config,
    ): ?string {
        return match ($action) {
            MediaBulkAction::Optimize => ($this->reprocess)($workspaceId, $candidate->id) === 'reprocessed'
                ? null
                : 'reprocess-failed',
            MediaBulkAction::Convert => ($this->reprocess)(
                $workspaceId,
                $candidate->id,
                is_string($config['format'] ?? null) ? $config['format'] : null,
            ) === 'reprocessed' ? null : 'convert-failed',
            MediaBulkAction::Move => $this->folders->moveAsset(
                $workspaceId,
                $candidate->id,
                array_key_exists('folderId', $config) && $config['folderId'] !== null
                    ? (int) $config['folderId']
                    : null,
            ) ? null : 'move-failed',
            MediaBulkAction::Trash => $this->trash($candidate->id),
            // Kalıcı silme buraya HİÇ gelmez; çağıran onu toplu yola ayırır.
            MediaBulkAction::Purge => 'unreachable',
        };
    }

    private function trash(int $assetId): ?string
    {
        $this->media->delete($assetId);

        return null;
    }

    /**
     * Varlık başına denetim izine yazılan eylem adı. Var olan sözlükle
     * aynı kelimeler kullanılır (`trashed`, `reprocessed`): tek dosya
     * silmenin ve toplu silmenin izde farklı görünmesi, "kim ne yaptı"
     * listesini iki dile bölerdi.
     */
    private function auditAction(MediaBulkAction $action): string
    {
        return match ($action) {
            MediaBulkAction::Optimize, MediaBulkAction::Convert => 'reprocessed',
            MediaBulkAction::Move => 'moved',
            MediaBulkAction::Trash => 'trashed',
            MediaBulkAction::Purge => 'purged',
        };
    }
}
