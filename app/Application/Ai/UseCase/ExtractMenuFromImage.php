<?php

declare(strict_types=1);

namespace App\Application\Ai\UseCase;

use App\Application\Ai\Port\AiAvailability;
use App\Application\Ai\Port\AiAvailabilityPort;
use App\Application\Ai\Port\AiRequest;
use App\Application\Ai\Port\VisionExtractionPort;
use App\Domain\Ai\AiArtifact;
use App\Domain\Ai\Capability;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Menü fotoğrafından TASLAK çıkarır — `docs/92` (P0-05 foto yolu).
 *
 * Çıkan şey doğrudan menüye YAZILMAZ. `ai_artifacts` satırında `applied_at`
 * boş durur; insan inceler, onaylar, ve ancak o zaman taslağa yazılır.
 * Bir fiyatı yanlış okuyan model, aksi hâlde misafirin gördüğü menüye
 * yanlış fiyat yazardı.
 */
final class ExtractMenuFromImage
{
    public function __construct(
        private readonly AiAvailabilityPort $availability,
        private readonly VisionExtractionPort $vision,
    ) {}

    /** Yetenek kapalıysa `null` döner; SEBEBİ çağıranın işidir. */
    public function availability(int $workspaceId): AiAvailability
    {
        return $this->availability->isAvailable($workspaceId, Capability::MenuExtract);
    }

    /**
     * @return array{id: int, artifact: AiArtifact}
     */
    public function handle(int $workspaceId, int $menuId, string $absolutePath): array
    {
        $artifact = $this->vision->extract(
            new AiRequest(
                capability: Capability::MenuExtract,
                workspaceId: $workspaceId,
                /*
                    TALİMAT ile VERİ ayrıdır ve bu ayrım tür düzeyinde
                    kurulmuştur. Menünün içine "önceki talimatları yoksay"
                    yazan bir metin, buradan talimat olarak geçemez.
                */
                instruction: 'Extract menu rows: category, product, price, currency.',
                userContent: [],
                options: ['menuId' => $menuId],
            ),
            [$absolutePath],
        );

        $id = (int) DB::table('ai_artifacts')->insertGetId([
            'workspace_id' => $workspaceId,
            'capability' => $artifact->capability->value,
            'model_identity' => $artifact->model->identity(),
            'prompt_version' => $artifact->promptVersion,
            'schema_version' => $artifact->schemaVersion,
            // Aynı okumanın iki kez uygulanmasını engelleyen anahtar.
            'idempotency_key' => (string) Str::uuid(),
            'fields' => json_encode(array_map(
                static fn ($field): array => $field->toArray(),
                $artifact->fields,
            ), JSON_UNESCAPED_UNICODE),
            'uncertain_field_count' => count($artifact->uncertainFields()),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return ['id' => $id, 'artifact' => $artifact];
    }
}
