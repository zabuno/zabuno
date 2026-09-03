<?php

declare(strict_types=1);

namespace App\Application\Ai\UseCase;

use App\Application\Ai\Exception\ProviderCallException;
use App\Application\Ai\Exception\SchemaViolationException;
use App\Application\Ai\Port\AiAvailability;
use App\Application\Ai\Port\AiAvailabilityPort;
use App\Application\Ai\Port\AiRequest;
use App\Application\Ai\Port\VisionExtractionPort;
use App\Domain\Ai\AiArtifact;
use App\Domain\Ai\Capability;
use App\Infrastructure\Ai\ArtifactSchemaValidator;
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
        private readonly ArtifactSchemaValidator $schema = new ArtifactSchemaValidator,
    ) {}

    /** Yetenek kapalıysa `null` döner; SEBEBİ çağıranın işidir. */
    public function availability(int $workspaceId): AiAvailability
    {
        return $this->availability->isAvailable($workspaceId, Capability::MenuExtract);
    }

    /**
     * @return array{id: int, artifact: AiArtifact}
     */
    /** @param  array<string, mixed>  $options  ör. `purpose: batch` (`docs/98` FF-75) */
    public function handle(int $workspaceId, int $menuId, string $absolutePath, array $options = []): array
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
                options: ['menuId' => $menuId] + $options,
            ),
            [$absolutePath],
        );

        /*
            Sağlayıcının cevabı şemaya uymuyorsa BURADA durur — kaydedilmez,
            kullanıcıya ulaşmaz (`docs/51` UNK-02, `docs/97` R14-R15). Daha
            önce bu doğrulayıcı yazılmıştı ama hiçbir yerden çağrılmıyordu;
            arayüzün izin-listesi eşleyicileri riski örtüyordu, garanti
            etmiyordu.
        */
        try {
            $this->schema->validate($artifact);
        } catch (SchemaViolationException $violation) {
            throw new ProviderCallException($artifact->model->provider, 'invalid-schema: '.$violation->getMessage());
        }

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
            'used_fallback' => $artifact->usedFallback,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return ['id' => $id, 'artifact' => $artifact];
    }
}
