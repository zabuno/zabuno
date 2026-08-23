<?php

declare(strict_types=1);

namespace Tests\Feature\Tenancy;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * RED test candidate for GET /api/workspaces/{workspace}/brand/locations
 * (list all locations for the workspace's Brand). No production code for
 * this list endpoint exists yet: routes/api.php only registers POST
 * .../brand/locations (create) and GET/PUT .../brand/locations/{location}
 * (single-resource show/update). There is no list route and no matching
 * use case, so every authenticated+verified request below is expected to
 * fail RED with a 404 from the framework's router (unmatched route),
 * not from malformed fixtures or guessed production response shapes.
 *
 * Requirement scope: enumerate LocationProfile-shaped items for the
 * caller's own workspace/brand only, ordered, empty-array when the brand
 * has zero locations, enumeration-safe 404 for foreign/non-member
 * workspaces, and auth/verified behavior matching sibling protected
 * brand/location routes (PROFILE-AUTH-01 pattern).
 */
final class ListLocationsTest extends TestCase
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

    private function addMember(int $workspaceId, User $user, string $role = 'member'): void
    {
        DB::table('workspace_memberships')->insert([
            'workspace_id' => $workspaceId,
            'user_id' => $user->id,
            'role' => $role,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function brandUri(int $workspaceId): string
    {
        return "/api/workspaces/{$workspaceId}/brand";
    }

    private function locationsUri(int $workspaceId): string
    {
        return "/api/workspaces/{$workspaceId}/brand/locations";
    }

    private function validBrandPayload(string $name = 'Zeytin Restoranları'): array
    {
        return [
            'name' => $name,
            'timezone' => 'Europe/Istanbul',
            'currency' => 'TRY',
        ];
    }

    private function validLocationPayload(string $displayName = 'Zeytin Kadıköy'): array
    {
        return [
            'display_name' => $displayName,
            'country_code' => 'TR',
            'city' => 'İstanbul',
            'address_line1' => 'Bağdat Caddesi No:1',
        ];
    }

    // --- LOCATION-LIST-01 -------------------------------------------------------

    public function test_owner_lists_only_their_workspace_locations_ordered(): void
    {
        $owner = $this->verifiedUser();
        $workspaceId = $this->workspaceOwnedBy($owner, 'Zeytin Restoranları', 'zeytin-restoranlari-list-01');

        $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->postJson($this->brandUri($workspaceId), $this->validBrandPayload())
            ->assertStatus(201);

        $first = $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->postJson($this->locationsUri($workspaceId), $this->validLocationPayload('Zeytin Kadıköy'));
        $first->assertStatus(201);

        $second = $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->postJson($this->locationsUri($workspaceId), $this->validLocationPayload('Zeytin Beşiktaş'));
        $second->assertStatus(201);

        $otherOwner = $this->verifiedUser();
        $otherWorkspaceId = $this->workspaceOwnedBy($otherOwner, 'Deniz Kebap', 'deniz-kebap-list-01');
        $this->actingAs($otherOwner)->withHeaders($this->jsonHeaders())
            ->postJson($this->brandUri($otherWorkspaceId), $this->validBrandPayload('Deniz Kebap'))
            ->assertStatus(201);
        $this->actingAs($otherOwner)->withHeaders($this->jsonHeaders())
            ->postJson($this->locationsUri($otherWorkspaceId), $this->validLocationPayload('Deniz Şubesi'))
            ->assertStatus(201);

        $response = $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->getJson($this->locationsUri($workspaceId));

        $response->assertStatus(200, 'LOCATION-LIST-01: kendi workspace lokasyon listesi 200 dönmeli.');
        $body = $response->json();

        self::assertIsArray($body, 'LOCATION-LIST-01: yanıt gövdesi bir dizi olmalı.');
        self::assertCount(2, $body, 'LOCATION-LIST-01: yalnız çağıranın kendi workspace/brand\'ine ait 2 lokasyon dönmeli.');

        self::assertSame('Zeytin Kadıköy', $body[0]['display_name'] ?? null, 'LOCATION-LIST-01: sıralama beklenen sıradaki ilk kayıt.');
        self::assertSame('Zeytin Beşiktaş', $body[1]['display_name'] ?? null, 'LOCATION-LIST-01: sıralama beklenen sıradaki ikinci kayıt.');

        foreach ($body as $item) {
            self::assertSame($workspaceId, $item['workspace_id'] ?? null, 'LOCATION-LIST-01: dönen her öğe yalnız çağıranın workspace\'ine ait olmalı.');
            self::assertArrayHasKey('id', $item);
            self::assertArrayHasKey('brand_id', $item);
            self::assertArrayHasKey('country_code', $item);
            self::assertArrayHasKey('city', $item);
            self::assertArrayHasKey('address_line1', $item);
        }
    }

    // --- LOCATION-LIST-EMPTY-01 --------------------------------------------------

    public function test_owner_with_zero_locations_receives_an_empty_array(): void
    {
        $owner = $this->verifiedUser();
        $workspaceId = $this->workspaceOwnedBy($owner, 'Zeytin Restoranları', 'zeytin-restoranlari-list-empty');

        $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->postJson($this->brandUri($workspaceId), $this->validBrandPayload())
            ->assertStatus(201);

        $response = $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->getJson($this->locationsUri($workspaceId));

        $response->assertStatus(200, 'LOCATION-LIST-EMPTY-01: sıfır lokasyonlu brand için 200 dönmeli.');
        self::assertSame([], $response->json(), 'LOCATION-LIST-EMPTY-01: sıfır lokasyonlu brand için boş dizi dönmeli.');
    }

    // --- LOCATION-LIST-TENANT-ESCAPE-01 -------------------------------------------

    public function test_foreign_or_no_membership_user_gets_enumeration_safe_404(): void
    {
        $owner = $this->verifiedUser();
        $stranger = $this->verifiedUser();
        $workspaceId = $this->workspaceOwnedBy($owner, 'Zeytin Restoranları', 'zeytin-restoranlari-list-escape');

        $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->postJson($this->brandUri($workspaceId), $this->validBrandPayload())
            ->assertStatus(201);
        $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->postJson($this->locationsUri($workspaceId), $this->validLocationPayload())
            ->assertStatus(201);

        $strangerResponse = $this->actingAs($stranger)->withHeaders($this->jsonHeaders())
            ->getJson($this->locationsUri($workspaceId));
        $strangerResponse->assertStatus(404, 'LOCATION-LIST-TENANT-ESCAPE-01: üye olunmayan workspace lokasyon listesi okunamaz (404).');

        $nonexistentResponse = $this->actingAs($stranger)->withHeaders($this->jsonHeaders())
            ->getJson($this->locationsUri(999999999));

        self::assertSame(
            $strangerResponse->getStatusCode(),
            $nonexistentResponse->getStatusCode(),
            'LOCATION-LIST-TENANT-ESCAPE-01: yabancı ve var-olmayan workspace aynı imzayı taşımalı (enumeration-safe).'
        );
    }

    // --- LOCATION-LIST-AUTH-01 ----------------------------------------------------

    public function test_list_endpoint_requires_authenticated_and_verified_user(): void
    {
        $owner = $this->verifiedUser();
        $workspaceId = $this->workspaceOwnedBy($owner, 'Zeytin Restoranları', 'zeytin-restoranlari-list-auth');
        $unverified = User::factory()->unverified()->create();

        $guestResponse = $this->withHeaders($this->jsonHeaders())->getJson($this->locationsUri($workspaceId));
        self::assertContains(
            $guestResponse->getStatusCode(),
            [401, 403],
            'LOCATION-LIST-AUTH-01: kimliksiz liste isteği 401/403 ile reddedilmeli (404 kabul edilmez).'
        );

        $unverifiedResponse = $this->actingAs($unverified)->withHeaders($this->jsonHeaders())
            ->getJson($this->locationsUri($workspaceId));
        $unverifiedResponse->assertStatus(403, 'LOCATION-LIST-AUTH-01: doğrulanmamış (unverified) kullanıcının liste isteği 403 ile reddedilmeli.');
    }
}
