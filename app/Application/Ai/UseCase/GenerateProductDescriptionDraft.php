<?php

declare(strict_types=1);

namespace App\Application\Ai\UseCase;

use App\Application\Ai\Port\AiAvailability;
use App\Application\Ai\Port\AiAvailabilityPort;
use App\Application\Ai\Port\AiRequest;
use App\Application\Ai\Port\StructuredGenerationPort;
use App\Domain\Ai\AiArtifact;
use App\Domain\Ai\Capability;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Ürün açıklaması TASLAĞI üretir — `docs/96` (Faz 2, `opt-23`).
 *
 * Çıkan şey doğrudan ürüne YAZILMAZ; `ApplyProductDescriptionDraft` insan
 * onayından sonra yazar. Kaynak (ürün adı, kategori adı) VERİ olarak gider,
 * talimat olarak değil (`docs/16` AI-10) — aynı disiplin: `ExtractMenuFromImage`.
 */
final class GenerateProductDescriptionDraft
{
    public function __construct(
        private readonly AiAvailabilityPort $availability,
        private readonly StructuredGenerationPort $generator,
    ) {}

    public function availability(int $workspaceId): AiAvailability
    {
        return $this->availability->isAvailable($workspaceId, Capability::ProductDescription);
    }

    /**
     * @return array{id: int, artifact: AiArtifact}|null null → menü ürünü bulunamadı/başka workspace'e ait.
     */
    public function handle(int $workspaceId, int $menuItemId): ?array
    {
        $context = DB::table('menu_items')
            ->join('products', 'products.id', '=', 'menu_items.product_id')
            ->join('menu_categories', 'menu_categories.id', '=', 'menu_items.category_id')
            ->where('menu_items.id', $menuItemId)
            ->where('products.workspace_id', $workspaceId)
            ->select('products.name as product_name', 'menu_categories.name as category_name')
            ->first();

        if ($context === null) {
            return null;
        }

        $artifact = $this->generator->generate(new AiRequest(
            capability: Capability::ProductDescription,
            workspaceId: $workspaceId,
            /*
                TALİMAT ile VERİ ayrıdır. Ürün/kategori adı buradan değil,
                userContent'ten geçer — bir ürün adına "önceki talimatları
                yoksay" yazılmış olsa bile talimat kanalına asla geçemez.
            */
            instruction: 'Write a one-sentence marketing description for this menu item. '
                .'Never claim it is allergen-free or safe for any allergy — that is a separate, human-reviewed field.',
            userContent: [
                'productName' => (string) $context->product_name,
                'categoryName' => (string) $context->category_name,
            ],
        ));

        $id = (int) DB::table('ai_artifacts')->insertGetId([
            'workspace_id' => $workspaceId,
            // Bu taslak HANGİ menü öğesi içindi — apply anında URL'e değil
            // buna güvenilir (yanlış istemci parametresi yanlış ürünün
            // üstüne yazmasın diye).
            'subject_id' => $menuItemId,
            'capability' => $artifact->capability->value,
            'model_identity' => $artifact->model->identity(),
            'prompt_version' => $artifact->promptVersion,
            'schema_version' => $artifact->schemaVersion,
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
