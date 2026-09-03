<?php

declare(strict_types=1);

namespace Tests\Feature\Ai;

use App\Application\Ai\Exception\ProviderCallException;
use App\Application\Ai\Port\AiAvailabilityPort;
use App\Application\Ai\Port\AiRequest;
use App\Application\Ai\Port\StructuredGenerationPort;
use App\Application\Ai\Port\VisionExtractionPort;
use App\Application\Ai\UseCase\ExtractMenuFromImage;
use App\Application\Ai\UseCase\GenerateProductDescriptionDraft;
use App\Domain\Ai\AiArtifact;
use App\Domain\Ai\FieldValue;
use App\Domain\Ai\ModelDeployment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * SCHEMA-WIRED — `ArtifactSchemaValidator` gerçek çağrı yolunda (`docs/97` R14-R15).
 *
 * Önceki bulgu: doğrulayıcı yazılmış, kendi izole testi vardı, ama hiçbir
 * use case onu ÇAĞIRMIYORDU. Bugüne kadar zarar vermedi çünkü her
 * sağlayıcının satır-eşleyicisi zaten bir izin-listesi (yalnız bilinen
 * alanlar okunur) — ama iddia edilen "şemaya uymayan cevap kullanıcıya
 * ULAŞMAZ" garantisi (`docs/51` UNK-02) çalışma zamanında aktif değildi.
 * Bu test onu gerçek kılar.
 */
final class SchemaValidationWiredTest extends TestCase
{
    use RefreshDatabase;

    private int $workspaceId;

    private int $menuId;

    private int $menuItemId;

    protected function setUp(): void
    {
        parent::setUp();

        $owner = User::factory()->create(['email_verified_at' => now()]);

        $this->workspaceId = (int) DB::table('workspaces')->insertGetId([
            'name' => 'Zeytin', 'slug' => 'sw-'.Str::lower(Str::random(6)), 'state' => 'active',
            'created_by' => $owner->id, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $brandId = (int) DB::table('brands')->insertGetId([
            'workspace_id' => $this->workspaceId, 'name' => 'Zeytin',
            'slug' => 'sw-b-'.Str::lower(Str::random(6)), 'locale' => 'tr',
            'timezone' => 'Europe/Istanbul', 'currency' => 'TRY',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $locationId = (int) DB::table('locations')->insertGetId([
            'workspace_id' => $this->workspaceId, 'brand_id' => $brandId,
            'display_name' => 'Kadıköy', 'country_code' => 'TR',
            'timezone' => 'Europe/Istanbul', 'city' => 'İstanbul',
            'address_line1' => 'Bahariye Cd. No:1',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->menuId = (int) DB::table('menus')->insertGetId([
            'public_key' => Str::lower(Str::random(10)), 'workspace_id' => $this->workspaceId,
            'location_id' => $locationId, 'name' => 'Ana Menü', 'state' => 'draft',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $categoryId = (int) DB::table('menu_categories')->insertGetId([
            'menu_id' => $this->menuId, 'name' => 'Kebaplar', 'position' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $productId = (int) DB::table('products')->insertGetId([
            'workspace_id' => $this->workspaceId, 'name' => 'Adana Kebap',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->menuItemId = (int) DB::table('menu_items')->insertGetId([
            'category_id' => $categoryId, 'product_id' => $productId,
            'price_minor_amount' => 38000, 'currency_code' => 'TRY',
            'is_visible' => true, 'position' => 1,
            'created_at' => now(), 'updated_at' => now(),
        ]);
    }

    // --- SCHEMA-WIRED-VISION-01 ---------------------------------------------

    #[Test]
    public function a_vision_response_carrying_a_forbidden_field_is_rejected_not_stored(): void
    {
        $poisoned = new class implements VisionExtractionPort
        {
            public function extract(AiRequest $request, array $filePaths): AiArtifact
            {
                return new AiArtifact(
                    capability: $request->capability,
                    model: new ModelDeployment('test', 'platform', 'poisoned'),
                    promptVersion: 'test.v1',
                    schemaVersion: $request->capability->schemaVersion(),
                    fields: [
                        new FieldValue('row.1', ['category' => 'X'], 0.9, false),
                        // Yasak alan — modelin asla iddia edemeyeceği bir şey.
                        new FieldValue('allergen_free', true, 0.9, false),
                    ],
                );
            }
        };

        $useCase = new ExtractMenuFromImage($this->app->make(AiAvailabilityPort::class), $poisoned);

        try {
            $this->expectException(ProviderCallException::class);
            $useCase->handle($this->workspaceId, $this->menuId, '/tmp/irrelevant.png');
        } finally {
            self::assertSame(0, DB::table('ai_artifacts')->count(), 'Yasak alan taşıyan cevap KAYDEDİLMEMELİ.');
        }
    }

    // --- SCHEMA-WIRED-TEXT-01 ------------------------------------------------

    #[Test]
    public function a_text_response_with_the_wrong_schema_version_is_rejected_not_stored(): void
    {
        $poisoned = new class implements StructuredGenerationPort
        {
            public function generate(AiRequest $request): AiArtifact
            {
                return new AiArtifact(
                    capability: $request->capability,
                    model: new ModelDeployment('test', 'platform', 'poisoned'),
                    promptVersion: 'test.v1',
                    // Yanlış şema sürümü — docs/51 UNK-02'nin tam senaryosu.
                    schemaVersion: 'wrong-schema.v9',
                    fields: [new FieldValue('description', 'x', 0.9, false)],
                );
            }
        };

        $useCase = new GenerateProductDescriptionDraft($this->app->make(AiAvailabilityPort::class), $poisoned);

        try {
            $this->expectException(ProviderCallException::class);
            $useCase->handle($this->workspaceId, $this->menuItemId);
        } finally {
            self::assertSame(0, DB::table('ai_artifacts')->count());
        }
    }
}
