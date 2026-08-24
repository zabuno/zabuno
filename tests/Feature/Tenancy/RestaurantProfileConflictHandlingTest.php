<?php

declare(strict_types=1);

namespace Tests\Feature\Tenancy;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

/**
 * RED correction candidate raised by independent P2 review of the Restaurant
 * Profile slice: neither the one-Brand-per-workspace constraint
 * (`brands.workspace_id` unique) nor the global-unique-slug constraint
 * (`brands.slug` unique, see database/migrations/
 * 2026_08_19_000001_create_brands_and_locations_tables.php) is checked at
 * the application layer before insert for an *explicitly supplied* slug —
 * App\Application\Tenancy\Profile\UseCase\CreateBrand only pre-checks
 * `slugExists()` when the caller does NOT pass a slug (auto-generation
 * path). A second create on the same workspace, or two workspaces
 * explicitly requesting the same slug, therefore hits the database unique
 * index directly and Laravel's default handler renders the resulting
 * Illuminate\Database\QueryException as an uncaught 500, not a stable
 * 409/422 JSON error shape.
 *
 * These tests are expected to fail RED with a 500 response (or a response
 * missing the expected JSON error shape) against the current
 * implementation, not from a bootstrap/syntax defect in this suite. The
 * race is simulated deterministically via two sequential HTTP requests
 * against the same immutable precondition (no concurrency/threading
 * involved), matching how the underlying unique index would reject a real
 * concurrent duplicate.
 *
 * Requirement IDs: PROFILE-BRAND-ONE-PER-WORKSPACE-01,
 * PROFILE-BRAND-SLUG-UNIQUE-01, PROFILE-ERROR-SHAPE-01.
 */
final class RestaurantProfileConflictHandlingTest extends TestCase
{
    use RefreshDatabase;

    private function jsonHeaders(): array
    {
        return ['Accept' => 'application/json'];
    }

    private function verifiedUser(): User
    {
        return User::factory()->create(['email_verified_at' => now()]);
    }

    private function workspaceOwnedBy(User $owner, string $name, string $slug): int
    {
        $workspaceId = (int) DB::table('workspaces')->insertGetId([
            'name' => $name,
            'slug' => $slug,
            'state' => 'active',
            'created_by' => $owner->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('workspace_memberships')->insert([
            'workspace_id' => $workspaceId,
            'user_id' => $owner->id,
            'role' => 'owner',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $workspaceId;
    }

    private function brandUri(int $workspaceId): string
    {
        return "/api/workspaces/{$workspaceId}/brand";
    }

    private function validBrandPayload(string $name = 'Zeytin Restoranları'): array
    {
        return [
            'name' => $name,
            'timezone' => 'Europe/Istanbul',
            'currency' => 'TRY',
        ];
    }

    private function assertStableConflictJson(TestResponse $response, string $message): void
    {
        self::assertContains(
            $response->getStatusCode(),
            [409, 422],
            $message.' (500 kabul edilmez.)'
        );
        $response->assertJsonStructure(['message']);
    }

    // --- PROFILE-BRAND-ONE-PER-WORKSPACE-01 (deterministic race) --------------

    public function test_a_racing_second_brand_create_on_the_same_workspace_surfaces_a_stable_409_or_422_not_a_500(): void
    {
        $owner = $this->verifiedUser();
        $workspaceId = $this->workspaceOwnedBy($owner, 'Zeytin Restoranları', 'zeytin-restoranlari-race-one-brand');

        $first = $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->postJson($this->brandUri($workspaceId), $this->validBrandPayload());
        $first->assertStatus(201, 'Ön koşul: ilk brand oluşturma 201 dönmeli.');

        $racingSecond = $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->postJson($this->brandUri($workspaceId), $this->validBrandPayload('Yarışan İkinci Marka'));

        $this->assertStableConflictJson(
            $racingSecond,
            'ONE-PER-WORKSPACE-01: workspace_id unique kısıtına çarpan ikinci create isteği stable 409/422 dönmeli.'
        );
    }

    // --- PROFILE-BRAND-SLUG-UNIQUE-01 (explicit slug, deterministic race) -----

    public function test_a_racing_explicit_slug_collision_across_workspaces_surfaces_a_stable_409_or_422_not_a_500(): void
    {
        $ayse = $this->verifiedUser();
        $deniz = $this->verifiedUser();

        $ayseWorkspaceId = $this->workspaceOwnedBy($ayse, 'Zeytin Restoranları', 'zeytin-restoranlari-race-slug-1');
        $denizWorkspaceId = $this->workspaceOwnedBy($deniz, 'Deniz Kebap', 'deniz-kebap-race-slug-2');

        $first = $this->actingAs($ayse)->withHeaders($this->jsonHeaders())
            ->postJson($this->brandUri($ayseWorkspaceId), array_merge($this->validBrandPayload(), ['slug' => 'zeytin-race']));
        $first->assertStatus(201, 'Ön koşul: ilk brand oluşturma 201 dönmeli.');

        $racingSecond = $this->actingAs($deniz)->withHeaders($this->jsonHeaders())
            ->postJson($this->brandUri($denizWorkspaceId), array_merge($this->validBrandPayload('Deniz Kebap'), ['slug' => 'zeytin-race']));

        $this->assertStableConflictJson(
            $racingSecond,
            'SLUG-UNIQUE-01: slug unique kısıtına çarpan açıkça belirtilmiş slug isteği stable 409/422 dönmeli.'
        );
    }
}
