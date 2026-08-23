<?php

declare(strict_types=1);

namespace Tests\Feature\Tenancy;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Blind RED test candidate for the restaurant Brand + first Location profile
 * slice (frozen MASTER decisions per task instruction: one Brand per
 * workspace; globally unique public slug; default locale 'en' but persisted/
 * validated from supported codes; timezone required valid IANA; currency
 * required ISO-4217 3 uppercase; description optional; contact email/phone
 * optional; first Location requires display_name and structured address
 * minimum country_code + city + address_line1; Brand slug collision returns
 * validation/conflict; Workspace collision behavior unchanged; authorization
 * via CORE-03 workspace.view/workspace.manage).
 *
 * No production code for this slice exists yet (no app/Domain/Tenancy Brand/
 * Location classes, no brand/location routes, no brands/locations tables),
 * so every request below is expected to fail RED due to a missing route
 * (404 from the framework) or missing table, not from syntax/bootstrap
 * defects in this suite. workspaces / workspace_memberships tables and the
 * CORE-03 authorization port already exist and are reused as-is.
 *
 * Requirement IDs: PROFILE-AUTH-01, PROFILE-BRAND-CREATE-01,
 * PROFILE-BRAND-SLUG-UNIQUE-01, PROFILE-BRAND-ONE-PER-WORKSPACE-01,
 * PROFILE-BRAND-READ-01, PROFILE-BRAND-UPDATE-01, PROFILE-BRAND-LOCALE-01,
 * PROFILE-BRAND-TZ-01, PROFILE-BRAND-CURRENCY-01, PROFILE-MEMBER-READONLY-01,
 * PROFILE-TENANT-ESCAPE-01, PROFILE-LOCATION-CREATE-01,
 * PROFILE-LOCATION-CREATE-VALIDATION-01, PROFILE-LOCATION-READ-01,
 * PROFILE-LOCATION-UPDATE-01, PROFILE-ERROR-SHAPE-01.
 */
final class RestaurantProfileJourneyTest extends TestCase
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

    private function locationUri(int $workspaceId, int $locationId): string
    {
        return "/api/workspaces/{$workspaceId}/brand/locations/{$locationId}";
    }

    private function validBrandPayload(string $name = 'Zeytin Restoranları'): array
    {
        return [
            'name' => $name,
            'timezone' => 'Europe/Istanbul',
            'currency' => 'TRY',
        ];
    }

    private function validLocationPayload(): array
    {
        return [
            'display_name' => 'Zeytin Kadıköy',
            'country_code' => 'TR',
            'city' => 'İstanbul',
            'address_line1' => 'Bağdat Caddesi No:1',
        ];
    }

    // --- PROFILE-AUTH-01 -----------------------------------------------------

    public function test_all_brand_and_location_endpoints_require_authenticated_and_verified_user(): void
    {
        $owner = $this->verifiedUser();
        $workspaceId = $this->workspaceOwnedBy($owner, 'Zeytin Restoranları', 'zeytin-restoranlari-auth');
        $unverified = User::factory()->unverified()->create();

        $guestRequests = [
            'brand.create' => fn () => $this->withHeaders($this->jsonHeaders())->postJson($this->brandUri($workspaceId), $this->validBrandPayload()),
            'brand.read' => fn () => $this->withHeaders($this->jsonHeaders())->getJson($this->brandUri($workspaceId)),
            'brand.update' => fn () => $this->withHeaders($this->jsonHeaders())->putJson($this->brandUri($workspaceId), $this->validBrandPayload()),
        ];

        foreach ($guestRequests as $label => $request) {
            $response = $request();
            self::assertContains(
                $response->getStatusCode(),
                [401, 403],
                "AUTH-01: kimliksiz {$label} isteği 401/403 ile reddedilmeli (404 kabul edilmez)."
            );
        }

        $unverifiedResponse = $this->actingAs($unverified)->withHeaders($this->jsonHeaders())
            ->postJson($this->brandUri($workspaceId), $this->validBrandPayload());
        $unverifiedResponse->assertStatus(403, 'AUTH-01: doğrulanmamış (unverified) kullanıcının brand.create isteği 403 ile reddedilmeli.');
    }

    // --- PROFILE-BRAND-CREATE-01 ----------------------------------------------

    public function test_owner_creates_the_workspace_brand_with_default_locale_and_persists_it(): void
    {
        $owner = $this->verifiedUser();
        $workspaceId = $this->workspaceOwnedBy($owner, 'Zeytin Restoranları', 'zeytin-restoranlari-create');

        $response = $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->postJson($this->brandUri($workspaceId), $this->validBrandPayload());

        $response->assertStatus(201);
        $body = $response->json();

        self::assertSame('Zeytin Restoranları', $body['name'] ?? null, 'CREATE-01: dönen isim gönderilenle aynı olmalı.');
        self::assertNotEmpty($body['slug'] ?? null, 'CREATE-01: sunucu global benzersiz slug üretmeli.');
        self::assertSame('en', $body['locale'] ?? null, 'CREATE-01: locale gönderilmediğinde varsayılan "en" olmalı.');
        self::assertSame('Europe/Istanbul', $body['timezone'] ?? null);
        self::assertSame('TRY', $body['currency'] ?? null);

        $this->assertDatabaseHas('brands', [
            'workspace_id' => $workspaceId,
            'name' => 'Zeytin Restoranları',
            'locale' => 'en',
        ]);
    }

    // --- PROFILE-BRAND-SLUG-UNIQUE-01 -----------------------------------------

    public function test_brand_slug_collision_across_workspaces_returns_validation_or_conflict_response(): void
    {
        $ayse = $this->verifiedUser();
        $deniz = $this->verifiedUser();

        $ayseWorkspaceId = $this->workspaceOwnedBy($ayse, 'Zeytin Restoranları', 'zeytin-restoranlari-slug-1');
        $denizWorkspaceId = $this->workspaceOwnedBy($deniz, 'Zeytin Restoranları', 'zeytin-restoranlari-slug-2');

        $first = $this->actingAs($ayse)->withHeaders($this->jsonHeaders())
            ->postJson($this->brandUri($ayseWorkspaceId), array_merge($this->validBrandPayload(), ['slug' => 'zeytin']));
        $first->assertStatus(201, 'SLUG-UNIQUE-01: ön koşul ilk brand oluşturma 201 dönmeli.');

        $second = $this->actingAs($deniz)->withHeaders($this->jsonHeaders())
            ->postJson($this->brandUri($denizWorkspaceId), array_merge($this->validBrandPayload(), ['slug' => 'zeytin']));

        self::assertContains(
            $second->getStatusCode(),
            [409, 422],
            'SLUG-UNIQUE-01: global benzersiz slug çakışması validation (422) veya conflict (409) olarak reddedilmeli.'
        );
    }

    // --- PROFILE-BRAND-ONE-PER-WORKSPACE-01 -----------------------------------

    public function test_a_second_brand_create_attempt_on_the_same_workspace_is_rejected(): void
    {
        $owner = $this->verifiedUser();
        $workspaceId = $this->workspaceOwnedBy($owner, 'Zeytin Restoranları', 'zeytin-restoranlari-one-brand');

        $first = $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->postJson($this->brandUri($workspaceId), $this->validBrandPayload());
        $first->assertStatus(201, 'ONE-PER-WORKSPACE-01: ön koşul ilk brand oluşturma 201 dönmeli.');

        $second = $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->postJson($this->brandUri($workspaceId), $this->validBrandPayload('İkinci Marka'));

        self::assertContains(
            $second->getStatusCode(),
            [409, 422],
            'ONE-PER-WORKSPACE-01: bir workspace başına yalnızca bir Brand olabilir; ikinci create reddedilmeli.'
        );
    }

    // --- PROFILE-BRAND-READ-01 / PROFILE-BRAND-UPDATE-01 -----------------------

    public function test_owner_can_read_and_update_their_own_brand(): void
    {
        $owner = $this->verifiedUser();
        $workspaceId = $this->workspaceOwnedBy($owner, 'Zeytin Restoranları', 'zeytin-restoranlari-read-update');

        $create = $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->postJson($this->brandUri($workspaceId), $this->validBrandPayload());
        $create->assertStatus(201);

        $read = $this->actingAs($owner)->withHeaders($this->jsonHeaders())->getJson($this->brandUri($workspaceId));
        $read->assertStatus(200);
        self::assertSame('Zeytin Restoranları', $read->json('name'));

        $update = $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->putJson($this->brandUri($workspaceId), array_merge($this->validBrandPayload('Zeytin Restoranları Güncel'), ['description' => 'Ege mutfağı']));
        $update->assertStatus(200);
        self::assertSame('Zeytin Restoranları Güncel', $update->json('name'));
        self::assertSame('Ege mutfağı', $update->json('description'));

        $this->assertDatabaseHas('brands', [
            'workspace_id' => $workspaceId,
            'name' => 'Zeytin Restoranları Güncel',
            'description' => 'Ege mutfağı',
        ]);
    }

    // --- PROFILE-BRAND-LOCALE-01 / TZ-01 / CURRENCY-01 --------------------------

    public function test_brand_create_rejects_invalid_locale_timezone_and_currency(): void
    {
        $owner = $this->verifiedUser();
        $workspaceId = $this->workspaceOwnedBy($owner, 'Zeytin Restoranları', 'zeytin-restoranlari-invalid-fields');

        $invalidLocale = $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->postJson($this->brandUri($workspaceId), array_merge($this->validBrandPayload(), ['locale' => 'zz']));
        $invalidLocale->assertStatus(422, 'LOCALE-01: desteklenmeyen locale kodu 422 dönmeli.');

        $invalidTimezone = $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->postJson($this->brandUri($workspaceId), array_merge($this->validBrandPayload(), ['timezone' => 'Not/AZone']));
        $invalidTimezone->assertStatus(422, 'TZ-01: geçersiz IANA timezone 422 dönmeli.');

        $invalidCurrency = $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->postJson($this->brandUri($workspaceId), array_merge($this->validBrandPayload(), ['currency' => 'try']));
        $invalidCurrency->assertStatus(422, 'CURRENCY-01: küçük harf/geçersiz ISO-4217 kodu 422 dönmeli.');
    }

    // --- PROFILE-MEMBER-READONLY-01 --------------------------------------------

    public function test_member_can_read_but_not_create_or_update_the_brand(): void
    {
        $owner = $this->verifiedUser();
        $member = $this->verifiedUser();
        $workspaceId = $this->workspaceOwnedBy($owner, 'Zeytin Restoranları', 'zeytin-restoranlari-member-readonly');
        $this->addMember($workspaceId, $member, 'member');

        $create = $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->postJson($this->brandUri($workspaceId), $this->validBrandPayload());
        $create->assertStatus(201);

        $memberRead = $this->actingAs($member)->withHeaders($this->jsonHeaders())->getJson($this->brandUri($workspaceId));
        $memberRead->assertStatus(200, 'MEMBER-READONLY-01: Member kendi workspace brand\'ini okuyabilmeli.');

        $memberUpdate = $this->actingAs($member)->withHeaders($this->jsonHeaders())
            ->putJson($this->brandUri($workspaceId), $this->validBrandPayload('Member Değiştirdi'));
        $memberUpdate->assertStatus(403, 'MEMBER-READONLY-01: Member brand güncelleyememeli (workspace.manage yok).');

        $strangerWorkspaceId = $this->workspaceOwnedBy($this->verifiedUser(), 'Deniz Kebap', 'deniz-kebap-member-readonly');
        $memberCreateElsewhere = $this->actingAs($member)->withHeaders($this->jsonHeaders())
            ->postJson($this->brandUri($strangerWorkspaceId), $this->validBrandPayload());
        self::assertContains(
            $memberCreateElsewhere->getStatusCode(),
            [403, 404],
            'MEMBER-READONLY-01: Member başka workspace üzerinde brand create deneyemez.'
        );
    }

    // --- PROFILE-TENANT-ESCAPE-01 -----------------------------------------------

    public function test_foreign_or_no_membership_user_cannot_read_or_mutate_a_brand(): void
    {
        $owner = $this->verifiedUser();
        $stranger = $this->verifiedUser();
        $workspaceId = $this->workspaceOwnedBy($owner, 'Zeytin Restoranları', 'zeytin-restoranlari-tenant-escape');

        $create = $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->postJson($this->brandUri($workspaceId), $this->validBrandPayload());
        $create->assertStatus(201);

        $strangerRead = $this->actingAs($stranger)->withHeaders($this->jsonHeaders())->getJson($this->brandUri($workspaceId));
        $strangerRead->assertStatus(404, 'TENANT-ESCAPE-01: üye olunmayan workspace brand\'i okunamaz (404, enumeration-safe).');

        $strangerUpdate = $this->actingAs($stranger)->withHeaders($this->jsonHeaders())
            ->putJson($this->brandUri($workspaceId), $this->validBrandPayload('Yabancı Güncelleme'));
        $strangerUpdate->assertStatus(404, 'TENANT-ESCAPE-01: üye olunmayan workspace brand\'i güncellenemez (404, enumeration-safe).');

        $nonexistentWorkspaceRead = $this->actingAs($stranger)->withHeaders($this->jsonHeaders())
            ->getJson($this->brandUri(999999999));
        self::assertSame(
            $strangerRead->getStatusCode(),
            $nonexistentWorkspaceRead->getStatusCode(),
            'TENANT-ESCAPE-01: yabancı ve var-olmayan workspace aynı imzayı taşımalı (enumeration-safe).'
        );
    }

    // --- PROFILE-LOCATION-CREATE-01 --------------------------------------------

    public function test_owner_creates_the_first_location_for_their_brand(): void
    {
        $owner = $this->verifiedUser();
        $workspaceId = $this->workspaceOwnedBy($owner, 'Zeytin Restoranları', 'zeytin-restoranlari-location-create');

        $brand = $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->postJson($this->brandUri($workspaceId), $this->validBrandPayload());
        $brand->assertStatus(201);

        $response = $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->postJson($this->locationsUri($workspaceId), $this->validLocationPayload());

        $response->assertStatus(201);
        $body = $response->json();

        self::assertSame('Zeytin Kadıköy', $body['display_name'] ?? null);
        self::assertSame('TR', $body['country_code'] ?? null);
        self::assertSame('İstanbul', $body['city'] ?? null);

        $this->assertDatabaseHas('locations', [
            'workspace_id' => $workspaceId,
            'display_name' => 'Zeytin Kadıköy',
            'country_code' => 'TR',
        ]);
    }

    // --- PROFILE-LOCATION-CREATE-VALIDATION-01 ----------------------------------

    public function test_location_create_rejects_missing_required_fields(): void
    {
        $owner = $this->verifiedUser();
        $workspaceId = $this->workspaceOwnedBy($owner, 'Zeytin Restoranları', 'zeytin-restoranlari-location-validation');

        $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->postJson($this->brandUri($workspaceId), $this->validBrandPayload());

        $missingDisplayName = $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->postJson($this->locationsUri($workspaceId), array_diff_key($this->validLocationPayload(), ['display_name' => true]));
        $missingDisplayName->assertStatus(422, 'LOCATION-CREATE-VALIDATION-01: display_name zorunlu.');

        $missingCountryCode = $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->postJson($this->locationsUri($workspaceId), array_diff_key($this->validLocationPayload(), ['country_code' => true]));
        $missingCountryCode->assertStatus(422, 'LOCATION-CREATE-VALIDATION-01: country_code zorunlu.');

        $missingCity = $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->postJson($this->locationsUri($workspaceId), array_diff_key($this->validLocationPayload(), ['city' => true]));
        $missingCity->assertStatus(422, 'LOCATION-CREATE-VALIDATION-01: city zorunlu.');

        $missingAddressLine1 = $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->postJson($this->locationsUri($workspaceId), array_diff_key($this->validLocationPayload(), ['address_line1' => true]));
        $missingAddressLine1->assertStatus(422, 'LOCATION-CREATE-VALIDATION-01: address_line1 zorunlu.');
    }

    // --- PROFILE-LOCATION-READ-01 / PROFILE-LOCATION-UPDATE-01 -------------------

    public function test_owner_reads_and_updates_the_location_member_reads_only_and_stranger_is_denied(): void
    {
        $owner = $this->verifiedUser();
        $member = $this->verifiedUser();
        $stranger = $this->verifiedUser();
        $workspaceId = $this->workspaceOwnedBy($owner, 'Zeytin Restoranları', 'zeytin-restoranlari-location-rw');
        $this->addMember($workspaceId, $member, 'member');

        $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->postJson($this->brandUri($workspaceId), $this->validBrandPayload());
        $location = $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->postJson($this->locationsUri($workspaceId), $this->validLocationPayload());
        $location->assertStatus(201);
        $locationId = (int) $location->json('id');

        $ownerRead = $this->actingAs($owner)->withHeaders($this->jsonHeaders())->getJson($this->locationUri($workspaceId, $locationId));
        $ownerRead->assertStatus(200);

        $ownerUpdate = $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->putJson($this->locationUri($workspaceId, $locationId), array_merge($this->validLocationPayload(), ['display_name' => 'Zeytin Kadıköy Güncel']));
        $ownerUpdate->assertStatus(200);
        self::assertSame('Zeytin Kadıköy Güncel', $ownerUpdate->json('display_name'));

        $memberRead = $this->actingAs($member)->withHeaders($this->jsonHeaders())->getJson($this->locationUri($workspaceId, $locationId));
        $memberRead->assertStatus(200, 'LOCATION-READ-01: Member kendi workspace lokasyonunu okuyabilmeli.');

        $memberUpdate = $this->actingAs($member)->withHeaders($this->jsonHeaders())
            ->putJson($this->locationUri($workspaceId, $locationId), $this->validLocationPayload());
        $memberUpdate->assertStatus(403, 'LOCATION-UPDATE-01: Member lokasyon güncelleyememeli.');

        $strangerRead = $this->actingAs($stranger)->withHeaders($this->jsonHeaders())->getJson($this->locationUri($workspaceId, $locationId));
        $strangerRead->assertStatus(404, 'LOCATION-READ-01: üye olunmayan workspace lokasyonu okunamaz.');
    }

    // --- PROFILE-ERROR-SHAPE-01 -----------------------------------------------

    public function test_validation_errors_use_a_consistent_json_error_shape(): void
    {
        $owner = $this->verifiedUser();
        $workspaceId = $this->workspaceOwnedBy($owner, 'Zeytin Restoranları', 'zeytin-restoranlari-error-shape');

        $response = $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->postJson($this->brandUri($workspaceId), ['timezone' => 'Europe/Istanbul', 'currency' => 'TRY']);

        $response->assertStatus(422);
        $response->assertJsonStructure(['message', 'errors']);
        self::assertArrayHasKey('name', $response->json('errors') ?? [], 'ERROR-SHAPE-01: eksik zorunlu alan (name) errors altında anahtarlanmalı.');
    }
}
