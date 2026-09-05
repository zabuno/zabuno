<?php

declare(strict_types=1);

namespace App\Application\Media\UseCase;

use App\Application\Media\Dto\MediaBulkCandidate;
use App\Application\Media\Port\MediaBulkPort;
use App\Domain\Media\MediaAssetStatus;
use App\Domain\Media\MediaBulkAction;
use App\Domain\Media\MediaBulkSkipReason;

/**
 * KURU ÇALIŞMA — kaynağın "Etki" adımı
 * (`docs/reference/panel-v3/MedyaModulu.dc.html`).
 *
 * Kaynağın kendi cümlesi bu sınıfın sözleşmesidir: *"Hiçbir dosyaya
 * dokunulmadı. Sunucu her dosyayı tek tek kontrol etti; aşağıdaki sayılar
 * gerçek."* İki iddia var ve ikisi de bağlayıcı:
 *
 * 1. **Hiçbir şeye dokunulmadı.** Bu sınıf hiçbir yazma çağrısı yapmaz;
 *    yalnız okur ve sınıflandırır.
 * 2. **Sayılar gerçek.** Oran, katsayı, tahmin yok. Kanonik kaynağın kendi
 *    JS'i atlama sayılarını `Math.round(scopeN * 0.04)` gibi ORANLARLA
 *    üretiyordu — bir tasarım dosyasında bu doğrudur, üründe yalandır.
 *    Burada her sayı bir satırdan gelir.
 *
 * ÇALIŞTIRMA da bu sınıfı yeniden çağırır. Ayrı bir sınıflandırma yazmak,
 * "kuru çalışmada 20 dosya dedi, 24'ünü işledi" farkını mümkün kılardı —
 * ve o fark, kuru çalışmayı tamamen değersiz kılan tek şeydir.
 */
final class PlanMediaBulkOperation
{
    /**
     * Tek çağrının işleyebileceği en fazla dosya.
     *
     * Sınır var, çünkü işlem SENKRONDUR: 1.800 dosyalık bir dönüştürme tek
     * bir istekte zaman aşımına uğrar ve sahip işin yarıda kaldığını
     * hiçbir yerden öğrenemez. Sınıra takılanlar `remaining` olarak SAYILIR
     * ve sahip düğmeye yeniden bastığında kaldığı yerden devam eder — aynı
     * desen `ConvertMediaAssetsToFormat` ve `ReprocessMediaBatchController`
     * içinde de var.
     *
     * Dönüştürme/optimize sınırı daha DARDIR: o iki eylem görsel kodlar,
     * diğer üçü tek satır günceller.
     */
    public const ENCODING_BATCH_LIMIT = 25;

    public const METADATA_BATCH_LIMIT = 200;

    public function __construct(private readonly MediaBulkPort $bulk) {}

    /**
     * @param  list<int>  $assetIds  DONDURULMUŞ kapsam listesi
     * @param  array<string, mixed>  $config
     * @return array{
     *     candidates: list<MediaBulkCandidate>,
     *     apply: list<MediaBulkCandidate>,
     *     skipped: list<array{id:int, name:string, reason:string}>,
     *     overflow: list<int>,
     *     batchLimit: int,
     *     scopeBytes: int
     * }
     */
    public function __invoke(int $workspaceId, MediaBulkAction $action, array $assetIds, array $config = []): array
    {
        $candidates = $this->bulk->candidates(
            $workspaceId,
            array_values(array_unique(array_map('intval', $assetIds))),
            $action->operatesOnTrash(),
        );

        $apply = [];
        $skipped = [];

        foreach ($candidates as $candidate) {
            $reason = $this->skipReasonFor($action, $candidate, $config);

            if ($reason === null) {
                $apply[] = $candidate;

                continue;
            }

            $skipped[] = [
                'id' => $candidate->id,
                'name' => $candidate->name,
                'reason' => $reason->value,
            ];
        }

        $limit = $this->batchLimitFor($action);
        $overflow = array_map(
            static fn (MediaBulkCandidate $candidate): int => $candidate->id,
            array_slice($apply, $limit),
        );

        return [
            'candidates' => $candidates,
            'apply' => array_slice($apply, 0, $limit),
            'skipped' => $skipped,
            'overflow' => array_values($overflow),
            'batchLimit' => $limit,
            'scopeBytes' => array_sum(array_map(
                static fn (MediaBulkCandidate $candidate): int => $candidate->sizeBytes,
                $candidates,
            )),
        ];
    }

    public function batchLimitFor(MediaBulkAction $action): int
    {
        return match ($action) {
            MediaBulkAction::Optimize, MediaBulkAction::Convert => self::ENCODING_BATCH_LIMIT,
            default => self::METADATA_BATCH_LIMIT,
        };
    }

    /**
     * Bir dosya bu eylemden NEDEN muaf? `null` ise muaf değil.
     *
     * Sıralama önemlidir ve en KATI kilit önce sorulur: yasal saklamadaki
     * bir dosya aynı zamanda karantinada da olabilir; sahibin okuması
     * gereken sebep, kaldırılması en zor olandır.
     *
     * @param  array<string, mixed>  $config
     */
    private function skipReasonFor(
        MediaBulkAction $action,
        MediaBulkCandidate $candidate,
        array $config,
    ): ?MediaBulkSkipReason {
        if ($candidate->legalHold) {
            return MediaBulkSkipReason::LegalHold;
        }

        if ($action->operatesOnTrash() && ! $candidate->trashed) {
            return MediaBulkSkipReason::NotInTrash;
        }

        /*
            Yayın kilidi yalnız YIKICI eylemlerde geçerlidir. Yayındaki bir
            görseli yeniden üretmek onu bozmaz — yeni sürüm açılır, yayın
            kendi dondurulmuş sürümünü göstermeye devam eder (`docs/49`
            Faz 3). Onu da atlamak, sahibin en çok bakılan fotoğraflarını
            hiç iyileştiremeyeceği anlamına gelirdi.
        */
        if ($action->isDestructive() && $candidate->usedByPublication) {
            return MediaBulkSkipReason::PublishedUsage;
        }

        if (in_array($action, [MediaBulkAction::Optimize, MediaBulkAction::Convert], true)) {
            if ($candidate->status !== MediaAssetStatus::Ready->value) {
                return MediaBulkSkipReason::Quarantine;
            }

            /*
                PDF ve SVG yeniden boyutlandırılamaz: ikisi de raster bir
                boru hattından geçmez. Kaynağın kendi cümlesi ("PDF ve SVG
                dosyaları yeniden boyutlandırılamaz") burada bir açıklama
                değil, bir kapıdır.
            */
            if (! str_starts_with($candidate->mimeType, 'image/') || $candidate->mimeType === 'image/svg+xml') {
                return MediaBulkSkipReason::UnsupportedFormat;
            }

            if ($action === MediaBulkAction::Convert) {
                $format = is_string($config['format'] ?? null) ? $config['format'] : '';

                // "Zaten AVIF — atlandı": aynı biçime dönüştürmek yalnız
                // gereksiz bir sürüm açar ve kotayı boşuna yer.
                if ($format !== '' && $candidate->mimeType === 'image/'.$format) {
                    return MediaBulkSkipReason::AlreadyDone;
                }
            }

            return null;
        }

        if ($action === MediaBulkAction::Move) {
            if ($candidate->status !== MediaAssetStatus::Ready->value) {
                return MediaBulkSkipReason::Quarantine;
            }

            $destination = array_key_exists('folderId', $config) && $config['folderId'] !== null
                ? (int) $config['folderId']
                : null;

            if ($candidate->folderId === $destination) {
                return MediaBulkSkipReason::AlreadyDone;
            }

            return null;
        }

        /*
            Çöpe atma karantinadaki dosyayı da kapsar ve kapsamalıdır:
            taraması takılmış bir dosyayı silememek, sahibi kütüphanesini
            temizleyemez hâlde bırakırdı.
        */
        return null;
    }
}
