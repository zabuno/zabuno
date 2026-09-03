<?php

declare(strict_types=1);

namespace App\Application\Ai\UseCase;

use App\Application\Ai\Port\AiAvailability;
use App\Application\Ai\Port\AiAvailabilityPort;
use App\Application\Ai\Port\EmbeddingPort;
use App\Domain\Ai\Capability;
use Illuminate\Support\Facades\DB;

/**
 * Yinelenen ürün adı ADAYLARINI tespit eder — `docs/95`/`docs/96` Faz 2
 * (`docs/32` core-taxonomy: "Duplicate terim tespiti/birleştirme önerisi").
 *
 * YALNIZ TESPİT. Hiçbir kaydı birleştirmez/siler/değiştirmez — insan iki
 * adı görür, kendisi karar verir. Bu yüzden bir `AiArtifact`/insan-onay
 * hattı YOK: üretilen şey kalıcı hale gelebilecek bir taslak değil, salt
 * okunur bir öneri listesidir (`docs/32` core-taxonomy: assistive, ama
 * "assistive" burada "birleştirmeyi öner" anlamına gelir, "birleştir"
 * değil — asıl birleştirme eylemi ayrı, insan tetikli bir iştir ve bu
 * pakette yok).
 */
final class DetectDuplicateProductNames
{
    private const DEFAULT_THRESHOLD = 0.90;

    public function __construct(
        private readonly AiAvailabilityPort $availability,
        private readonly EmbeddingPort $embeddings,
    ) {}

    public function availability(int $workspaceId): AiAvailability
    {
        return $this->availability->isAvailable($workspaceId, Capability::TextEmbedding);
    }

    /**
     * @return list<array{productAId: int, productAName: string, productBId: int, productBName: string, similarity: float}>
     */
    public function handle(int $workspaceId, float $threshold = self::DEFAULT_THRESHOLD): array
    {
        $products = DB::table('products')
            ->where('workspace_id', $workspaceId)
            ->select('id', 'name')
            ->orderBy('id')
            ->get();

        if ($products->count() < 2) {
            return [];
        }

        $embedded = $this->embeddings->embed($workspaceId, $products->pluck('name')->all());
        $rows = $products->values();

        $candidates = [];

        for ($i = 0; $i < $rows->count(); $i++) {
            for ($j = $i + 1; $j < $rows->count(); $j++) {
                $similarity = self::cosineSimilarity($embedded[$i]['vector'], $embedded[$j]['vector']);

                if ($similarity < $threshold) {
                    continue;
                }

                $candidates[] = [
                    'productAId' => (int) $rows[$i]->id,
                    'productAName' => (string) $rows[$i]->name,
                    'productBId' => (int) $rows[$j]->id,
                    'productBName' => (string) $rows[$j]->name,
                    'similarity' => round($similarity, 4),
                ];
            }
        }

        return $candidates;
    }

    /**
     * @param  list<float>  $a
     * @param  list<float>  $b
     */
    private static function cosineSimilarity(array $a, array $b): float
    {
        $dot = 0.0;
        $magA = 0.0;
        $magB = 0.0;
        $length = min(count($a), count($b));

        for ($i = 0; $i < $length; $i++) {
            $dot += $a[$i] * $b[$i];
            $magA += $a[$i] * $a[$i];
            $magB += $b[$i] * $b[$i];
        }

        if ($magA === 0.0 || $magB === 0.0) {
            return 0.0;
        }

        return $dot / (sqrt($magA) * sqrt($magB));
    }
}
