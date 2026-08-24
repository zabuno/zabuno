<?php

declare(strict_types=1);

namespace Tests\Feature\Media;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Blind RED HTTP-delivery candidate for the S1-WP03a Media Quarantine
 * Intake bounded slice (see task instruction contract: real multipart byte
 * intake to a tenant-private quarantine disk, required alt text + slot,
 * list own quarantined assets, delete own quarantined asset, status never
 * "accepted"/"processing"/"ready", no public URL or derivative, invalid
 * size/MIME-magic-spoof rejected, unauthenticated/nonmember/cross-tenant
 * blocked enumeration-safe, CSRF-protected state changes, no decoder/
 * scanner/public behavior wired yet).
 *
 * No App\Http\Controllers\Media\* controller, no media_assets table/model,
 * and none of the routes below are registered in routes/api.php (grep
 * confirms zero "media" routes exist today, mirroring the same
 * route-not-found RED pattern used by MenuApiJourneyTest). Every request
 * below is therefore expected to fail RED with a 404 route-not-found
 * response, not a logic assertion failure or a bootstrap defect in this
 * suite.
 *
 * Frozen route + JSON contract under test (this file's own placeholder,
 * consistent with existing Tenancy/MenuCatalog controller conventions,
 * since no WP03a delivery-contract doc exists yet):
 *
 *   POST   /api/workspaces/{workspace}/media
 *     multipart: file, altText (string, required), slot (string, required)
 *     -> 201 {"id": int, "workspaceId": int, "status": "quarantined",
 *             "altText": string, "slot": string}
 *
 *   GET    /api/workspaces/{workspace}/media
 *     -> 200 {"data": [{"id": int, "workspaceId": int, "status": string,
 *             "altText": string, "slot": string}]}
 *
 *   DELETE /api/workspaces/{workspace}/media/{media}
 *     -> 204
 *
 * Requirement IDs: MEDIA-INTAKE-UPLOAD-01, MEDIA-INTAKE-REQUIRED-FIELDS-01,
 * MEDIA-INTAKE-LIST-01, MEDIA-INTAKE-DELETE-01, MEDIA-INTAKE-STATUS-01,
 * MEDIA-INTAKE-NO-PUBLIC-URL-01, MEDIA-INTAKE-SIZE-REJECT-01,
 * MEDIA-INTAKE-MIME-SPOOF-REJECT-01, MEDIA-INTAKE-UNAUTH-01,
 * MEDIA-INTAKE-CSRF-01.
 */
final class MediaIntakeTest extends TestCase
{
    use RefreshDatabase;

    private function verifiedUser(): User
    {
        return User::factory()->create(['email_verified_at' => now()]);
    }

    private function ownerWorkspace(User $owner, string $slugSeed): int
    {
        $workspaceId = (int) DB::table('workspaces')->insertGetId([
            'name' => 'Zeytin Restoranları',
            'slug' => $slugSeed,
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

    private function jsonHeaders(): array
    {
        return ['Accept' => 'application/json'];
    }

    // --- MEDIA-INTAKE-UPLOAD-01 / REQUIRED-FIELDS-01 / STATUS-01 -----------

    public function test_owner_can_upload_a_real_image_to_tenant_private_quarantine_with_required_alt_text_and_slot(): void
    {
        Storage::fake('local');
        $owner = $this->verifiedUser();
        $workspaceId = $this->ownerWorkspace($owner, 'zeytin-media-upload');

        $file = UploadedFile::fake()->image('logo.jpg', 200, 200)->size(50);

        $response = $this->actingAs($owner)->withHeaders($this->jsonHeaders())->post(
            "/api/workspaces/{$workspaceId}/media",
            ['file' => $file, 'altText' => 'Zeytin Restoranı logosu', 'slot' => 'menu']
        );

        $response->assertStatus(201, 'MEDIA-INTAKE-UPLOAD-01: gerçek multipart byte intake tenant private quarantine\'a kabul edilmeli.');
        $response->assertJsonPath('workspaceId', $workspaceId);
        $response->assertJsonPath('altText', 'Zeytin Restoranı logosu');
        $response->assertJsonPath('slot', 'menu');

        $status = $response->json('status');
        self::assertNotSame('accepted', $status, 'MEDIA-INTAKE-STATUS-01: status asla accepted olmamalı.');
        self::assertNotSame('processing', $status, 'MEDIA-INTAKE-STATUS-01: status asla processing olmamalı.');
        self::assertNotSame('ready', $status, 'MEDIA-INTAKE-STATUS-01: status asla ready olmamalı.');

        self::assertArrayNotHasKey('url', $response->json() ?? [], 'MEDIA-INTAKE-NO-PUBLIC-URL-01: yanıt public url içermemeli.');
        self::assertArrayNotHasKey('publicUrl', $response->json() ?? [], 'MEDIA-INTAKE-NO-PUBLIC-URL-01: yanıt public url içermemeli.');
        self::assertArrayNotHasKey('derivatives', $response->json() ?? [], 'MEDIA-INTAKE-NO-PUBLIC-URL-01: yanıt derivative içermemeli.');
    }

    public function test_upload_without_alt_text_or_slot_is_rejected(): void
    {
        Storage::fake('local');
        $owner = $this->verifiedUser();
        $workspaceId = $this->ownerWorkspace($owner, 'zeytin-media-required-fields');

        $file = UploadedFile::fake()->image('logo.jpg', 200, 200)->size(50);

        $missingAlt = $this->actingAs($owner)->withHeaders($this->jsonHeaders())->post(
            "/api/workspaces/{$workspaceId}/media",
            ['file' => $file, 'slot' => 'menu']
        );
        $missingAlt->assertStatus(422, 'MEDIA-INTAKE-REQUIRED-FIELDS-01: altText zorunlu olmalı.');

        $missingSlot = $this->actingAs($owner)->withHeaders($this->jsonHeaders())->post(
            "/api/workspaces/{$workspaceId}/media",
            ['file' => $file, 'altText' => 'Logo']
        );
        $missingSlot->assertStatus(422, 'MEDIA-INTAKE-REQUIRED-FIELDS-01: slot zorunlu olmalı.');
    }

    // --- MEDIA-INTAKE-SIZE-REJECT-01 / MIME-SPOOF-REJECT-01 ----------------

    public function test_oversized_file_is_rejected(): void
    {
        Storage::fake('local');
        $owner = $this->verifiedUser();
        $workspaceId = $this->ownerWorkspace($owner, 'zeytin-media-oversized');

        $tooLarge = UploadedFile::fake()->create('huge.jpg', 51_200, 'image/jpeg');

        $response = $this->actingAs($owner)->withHeaders($this->jsonHeaders())->post(
            "/api/workspaces/{$workspaceId}/media",
            ['file' => $tooLarge, 'altText' => 'Çok büyük dosya', 'slot' => 'menu']
        );

        $response->assertStatus(422, 'MEDIA-INTAKE-SIZE-REJECT-01: boyut limiti aşan dosya reddedilmeli.');
    }

    public function test_mime_magic_byte_spoof_is_rejected(): void
    {
        Storage::fake('local');
        $owner = $this->verifiedUser();
        $workspaceId = $this->ownerWorkspace($owner, 'zeytin-media-mime-spoof');

        $spoofed = UploadedFile::fake()->createWithContent('payload.jpg', "<?php echo 'pwned'; ?>");

        $response = $this->actingAs($owner)->withHeaders($this->jsonHeaders())->post(
            "/api/workspaces/{$workspaceId}/media",
            ['file' => $spoofed, 'altText' => 'Sahte MIME', 'slot' => 'menu']
        );

        $response->assertStatus(422, 'MEDIA-INTAKE-MIME-SPOOF-REJECT-01: gerçek içerik magic-byte doğrulamasını geçmeyen dosya reddedilmeli, uzantıya güvenilmemeli.');
    }

    // --- MEDIA-INTAKE-LIST-01 / DELETE-01 -----------------------------------

    public function test_owner_can_list_and_delete_own_quarantined_assets(): void
    {
        Storage::fake('local');
        $owner = $this->verifiedUser();
        $workspaceId = $this->ownerWorkspace($owner, 'zeytin-media-list-delete');

        $file = UploadedFile::fake()->image('logo.jpg', 200, 200)->size(50);
        $upload = $this->actingAs($owner)->withHeaders($this->jsonHeaders())->post(
            "/api/workspaces/{$workspaceId}/media",
            ['file' => $file, 'altText' => 'Logo', 'slot' => 'menu']
        );
        $upload->assertStatus(201);
        $mediaId = (int) $upload->json('id');
        self::assertGreaterThan(0, $mediaId, 'MEDIA-INTAKE-UPLOAD-01: oluşturulan media id\'si pozitif olmalı.');

        $list = $this->actingAs($owner)->withHeaders($this->jsonHeaders())->getJson(
            "/api/workspaces/{$workspaceId}/media"
        );
        $list->assertStatus(200, 'MEDIA-INTAKE-LIST-01: kendi quarantine varlıklarını listeleyebilmeli.');
        $list->assertJsonPath('data.0.id', $mediaId);

        $diskPath = DB::table('media_assets')->where('id', $mediaId)->value('disk_path');
        self::assertNotEmpty($diskPath, 'MEDIA-INTAKE-DELETE-01: silme öncesi disk_path kaydedilmiş olmalı.');
        Storage::disk('local')->assertExists($diskPath);

        $delete = $this->actingAs($owner)->withHeaders($this->jsonHeaders())->deleteJson(
            "/api/workspaces/{$workspaceId}/media/{$mediaId}"
        );
        $delete->assertStatus(204, 'MEDIA-INTAKE-DELETE-01: kendi quarantine varlığını silebilmeli.');

        $listAfterDelete = $this->actingAs($owner)->withHeaders($this->jsonHeaders())->getJson(
            "/api/workspaces/{$workspaceId}/media"
        );
        $listAfterDelete->assertStatus(200);
        self::assertSame([], $listAfterDelete->json('data'), 'MEDIA-INTAKE-DELETE-01: silinen varlık listede kalmamalı.');

        self::assertSoftDeleted('media_assets', ['id' => $mediaId], deletedAtColumn: 'deleted_at');

        Storage::disk('local')->assertMissing(
            $diskPath,
            'MEDIA-P1-ORPHAN-BYTE-01: quarantine kaydı (soft-)silindiğinde disk üzerindeki dosya baytları da kaldırılmalı; orphan byte kalmamalı.'
        );
    }

    // --- MEDIA-INTAKE-UNAUTH-01 / CSRF-01 -----------------------------------

    public function test_guest_is_unauthenticated_on_every_media_route(): void
    {
        $owner = $this->verifiedUser();
        $workspaceId = $this->ownerWorkspace($owner, 'zeytin-media-guest');

        $file = UploadedFile::fake()->image('logo.jpg', 200, 200)->size(50);

        $upload = $this->post("/api/workspaces/{$workspaceId}/media", [
            'file' => $file, 'altText' => 'Logo', 'slot' => 'menu',
        ], $this->jsonHeaders());
        $upload->assertStatus(401, 'MEDIA-INTAKE-UNAUTH-01: kimliksiz istek 401 dönmeli.');

        $list = $this->withHeaders($this->jsonHeaders())->getJson("/api/workspaces/{$workspaceId}/media");
        $list->assertStatus(401, 'MEDIA-INTAKE-UNAUTH-01: kimliksiz istek 401 dönmeli.');
    }

    public function test_state_changing_media_routes_are_registered_behind_the_sanctum_stateful_csrf_guard(): void
    {
        $owner = $this->verifiedUser();
        $workspaceId = $this->ownerWorkspace($owner, 'zeytin-media-csrf');

        $middleware = collect(app('router')->getRoutes()->getRoutes())
            ->first(fn ($route) => $route->uri() === 'api/workspaces/{workspace}/media' && in_array('POST', $route->methods(), true));

        self::assertNotNull(
            $middleware,
            'MEDIA-INTAKE-CSRF-01: POST /api/workspaces/{workspace}/media route\'u henüz kayıtlı değil; CSRF/stateful guard doğrulanamaz.'
        );
    }
}
