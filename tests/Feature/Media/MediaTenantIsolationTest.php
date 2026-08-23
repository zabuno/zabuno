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
 * Blind RED HTTP-delivery candidate for cross-tenant escape-scope coverage
 * of the S1-WP03a Media Quarantine Intake bounded slice (see
 * MediaIntakeTest for the frozen route + JSON contract this file assumes;
 * frozen scope per task instruction: an unauthenticated caller, a
 * non-member of the workspace, and a member of a foreign workspace naming
 * another workspace's media id must all be blocked enumeration-safe —
 * never a 403 that reveals the resource exists, and never a cross-tenant
 * payload leak).
 *
 * None of the three Media routes are registered in routes/api.php, so
 * every request below is expected to fail RED with a 404 route-not-found
 * response before any RBAC/tenant-guard logic runs — not a logic assertion
 * failure or a bootstrap defect in this suite.
 *
 * Requirement IDs: MEDIA-ESCAPE-LIST-01, MEDIA-ESCAPE-DELETE-01,
 * MEDIA-ESCAPE-UPLOAD-01, MEDIA-ESCAPE-NONMEMBER-01.
 */
final class MediaTenantIsolationTest extends TestCase
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

    // --- MEDIA-ESCAPE-LIST-01 / DELETE-01 -----------------------------------

    public function test_a_foreign_workspace_owner_cannot_list_or_delete_another_workspaces_quarantined_asset(): void
    {
        Storage::fake('local');
        $ownerA = $this->verifiedUser();
        $ownerB = $this->verifiedUser();
        $workspaceA = $this->ownerWorkspace($ownerA, 'zeytin-media-escape-a');
        $workspaceB = $this->ownerWorkspace($ownerB, 'zeytin-media-escape-b');

        $file = UploadedFile::fake()->image('logo.jpg', 200, 200)->size(50);
        $upload = $this->actingAs($ownerB)->withHeaders($this->jsonHeaders())->post(
            "/api/workspaces/{$workspaceB}/media",
            ['file' => $file, 'altText' => 'B logosu', 'slot' => 'menu']
        );
        $upload->assertStatus(201);
        $mediaIdB = (int) $upload->json('id');

        $listAsA = $this->actingAs($ownerA)->withHeaders($this->jsonHeaders())->getJson(
            "/api/workspaces/{$workspaceA}/media"
        );
        $listAsA->assertStatus(200);
        $ids = collect($listAsA->json('data') ?? [])->pluck('id')->all();
        self::assertNotContains($mediaIdB, $ids, 'MEDIA-ESCAPE-LIST-01: workspaceA listesi workspaceB\'nin varlığını içermemeli.');

        $deleteForeign = $this->actingAs($ownerA)->withHeaders($this->jsonHeaders())->deleteJson(
            "/api/workspaces/{$workspaceA}/media/{$mediaIdB}"
        );
        $deleteForeign->assertStatus(404, 'MEDIA-ESCAPE-DELETE-01: workspaceA, workspaceB\'ye ait media\'yı silememeli — 404, 403 değil.');

        $stillListedForB = $this->actingAs($ownerB)->withHeaders($this->jsonHeaders())->getJson(
            "/api/workspaces/{$workspaceB}/media"
        );
        $stillListedForB->assertStatus(200);
        $idsForB = collect($stillListedForB->json('data') ?? [])->pluck('id')->all();
        self::assertContains($mediaIdB, $idsForB, 'MEDIA-ESCAPE-DELETE-01: workspaceA\'nın başarısız silme denemesi workspaceB\'nin gerçek varlığını etkilememeli.');
    }

    // --- MEDIA-ESCAPE-UPLOAD-01 ---------------------------------------------

    public function test_uploading_into_a_foreign_workspace_is_enumeration_safe_404(): void
    {
        Storage::fake('local');
        $ownerA = $this->verifiedUser();
        $ownerB = $this->verifiedUser();
        $this->ownerWorkspace($ownerA, 'zeytin-media-escape-upload-a');
        $workspaceB = $this->ownerWorkspace($ownerB, 'zeytin-media-escape-upload-b');

        $file = UploadedFile::fake()->image('logo.jpg', 200, 200)->size(50);

        $response = $this->actingAs($ownerA)->withHeaders($this->jsonHeaders())->post(
            "/api/workspaces/{$workspaceB}/media",
            ['file' => $file, 'altText' => 'Yabancı workspace yüklemesi', 'slot' => 'menu']
        );

        $response->assertStatus(404, 'MEDIA-ESCAPE-UPLOAD-01: workspaceA üyesi, workspaceB\'ye media yükleyememeli — 404, 403 değil.');
    }

    // --- MEDIA-ESCAPE-NONMEMBER-01 ------------------------------------------

    public function test_unauthenticated_and_nonmember_callers_are_blocked_enumeration_safe_on_every_media_route(): void
    {
        $owner = $this->verifiedUser();
        $nonmember = $this->verifiedUser();
        $workspaceId = $this->ownerWorkspace($owner, 'zeytin-media-escape-nonmember');

        $file = UploadedFile::fake()->image('logo.jpg', 200, 200)->size(50);

        $guestUpload = $this->post("/api/workspaces/{$workspaceId}/media", [
            'file' => $file, 'altText' => 'Logo', 'slot' => 'menu',
        ], $this->jsonHeaders());
        $guestUpload->assertStatus(401, 'MEDIA-ESCAPE-NONMEMBER-01: kimliksiz istek 401 dönmeli.');

        $nonmemberList = $this->actingAs($nonmember)->withHeaders($this->jsonHeaders())->getJson(
            "/api/workspaces/{$workspaceId}/media"
        );
        $nonmemberList->assertStatus(404, 'MEDIA-ESCAPE-NONMEMBER-01: workspace üyesi olmayan kullanıcı listeleme için 404 almalı, 403 değil.');

        $nonmemberUpload = $this->actingAs($nonmember)->withHeaders($this->jsonHeaders())->post(
            "/api/workspaces/{$workspaceId}/media",
            ['file' => $file, 'altText' => 'Logo', 'slot' => 'menu']
        );
        $nonmemberUpload->assertStatus(404, 'MEDIA-ESCAPE-NONMEMBER-01: workspace üyesi olmayan kullanıcı yükleme için 404 almalı, 403 değil.');

        $nonmemberDelete = $this->actingAs($nonmember)->withHeaders($this->jsonHeaders())->deleteJson(
            "/api/workspaces/{$workspaceId}/media/1"
        );
        $nonmemberDelete->assertStatus(404, 'MEDIA-ESCAPE-NONMEMBER-01: workspace üyesi olmayan kullanıcı silme için 404 almalı, 403 değil.');
    }
}
