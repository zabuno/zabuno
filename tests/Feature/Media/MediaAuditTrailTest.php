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
 * FF-97 — medya denetim izi (`docs/49` Faz 7 madde 4).
 *
 * MÜŞTERİ SORUNU. Menüden bir yemeğin fotoğrafı kaybolduğunda restoran
 * sahibinin sorduğu ilk soru "bunu kim sildi?"dir. Kota vardı, izin vardı,
 * mutabakat vardı; kaydı tutan yoktu. Ekipte üç kişi varken bu soru ancak
 * herkesi tek tek sormakla cevaplanıyordu.
 *
 * İz APPEND-ONLY: satır bir kez yazılır ve düzeltilmez — düzeltilebilen bir
 * denetim izi, denetim izi değildir. Varlık silindikten sonra da yaşar,
 * çünkü asıl değeri olan an tam da varlığın artık orada olmadığı andır.
 *
 * Gereksinim: MEDIA-AUDIT-WRITE-01, MEDIA-AUDIT-SURVIVES-DELETE-02,
 * MEDIA-AUDIT-TENANT-03, MEDIA-AUDIT-ACTOR-04.
 */
final class MediaAuditTrailTest extends TestCase
{
    use RefreshDatabase;

    private function verifiedUser(): User
    {
        return User::factory()->create(['email_verified_at' => now()]);
    }

    private function ownerWorkspace(User $owner, string $slug): int
    {
        $workspaceId = (int) DB::table('workspaces')->insertGetId([
            'name' => 'Zeytin Restoranları',
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

    private function upload(User $user, int $workspaceId, string $alt = 'Kuzu pirzola'): int
    {
        $response = $this->actingAs($user)->withHeaders(['Accept' => 'application/json'])->post(
            "/api/workspaces/{$workspaceId}/media",
            [
                'file' => UploadedFile::fake()->image('pirzola.jpg', 400, 400)->size(60),
                'altText' => $alt,
                'slot' => 'menu',
            ]
        );

        $response->assertStatus(201);

        return (int) $response->json('id');
    }

    /** @return array<int, array{action:string, media_asset_id:int, actor_user_id:?int}> */
    private function audits(int $workspaceId): array
    {
        return DB::table('media_audits')
            ->where('workspace_id', $workspaceId)
            ->orderBy('id')
            ->get(['action', 'media_asset_id', 'actor_user_id'])
            ->map(static fn (object $row): array => [
                'action' => (string) $row->action,
                'media_asset_id' => (int) $row->media_asset_id,
                'actor_user_id' => $row->actor_user_id === null ? null : (int) $row->actor_user_id,
            ])
            ->all();
    }

    // --- MEDIA-AUDIT-WRITE-01 ----------------------------------------------

    public function test_uploading_and_renaming_write_the_trail(): void
    {
        Storage::fake('local');
        $user = $this->verifiedUser();
        $workspaceId = $this->ownerWorkspace($user, 'zeytin-audit-write');

        $assetId = $this->upload($user, $workspaceId);

        $this->actingAs($user)->withHeaders(['Accept' => 'application/json'])->patchJson(
            "/api/workspaces/{$workspaceId}/media/{$assetId}",
            ['altText' => 'Izgara kuzu pirzola']
        )->assertStatus(200);

        $actions = array_column($this->audits($workspaceId), 'action');

        self::assertSame(['uploaded', 'renamed'], $actions, 'MEDIA-AUDIT-WRITE-01: yükleme ve yeniden adlandırma ize yazılmalı.');
    }

    // --- MEDIA-AUDIT-SURVIVES-DELETE-02 / ACTOR-04 -------------------------

    public function test_the_trail_records_who_deleted_and_survives_the_deletion(): void
    {
        Storage::fake('local');
        $user = $this->verifiedUser();
        $workspaceId = $this->ownerWorkspace($user, 'zeytin-audit-delete');
        $assetId = $this->upload($user, $workspaceId);

        $this->actingAs($user)->withHeaders(['Accept' => 'application/json'])
            ->deleteJson("/api/workspaces/{$workspaceId}/media/{$assetId}")
            ->assertStatus(204);

        $trashed = array_values(array_filter(
            $this->audits($workspaceId),
            static fn (array $row): bool => $row['action'] === 'trashed'
        ));

        self::assertCount(1, $trashed, 'MEDIA-AUDIT-SURVIVES-DELETE-02: silme ize yazılmalı.');
        self::assertSame($assetId, $trashed[0]['media_asset_id'], 'MEDIA-AUDIT-SURVIVES-DELETE-02: kayıt silinen varlığı göstermeli.');
        self::assertSame(
            (int) $user->getKey(),
            $trashed[0]['actor_user_id'],
            'MEDIA-AUDIT-ACTOR-04: "kim sildi" sorusunun cevabı kayıtta olmalı.'
        );
    }

    public function test_restoring_from_trash_is_recorded_too(): void
    {
        Storage::fake('local');
        $user = $this->verifiedUser();
        $workspaceId = $this->ownerWorkspace($user, 'zeytin-audit-restore');
        $assetId = $this->upload($user, $workspaceId);

        $this->actingAs($user)->withHeaders(['Accept' => 'application/json'])
            ->deleteJson("/api/workspaces/{$workspaceId}/media/{$assetId}")
            ->assertStatus(204);

        $this->actingAs($user)->withHeaders(['Accept' => 'application/json'])
            ->postJson("/api/workspaces/{$workspaceId}/media/{$assetId}/restore")
            ->assertStatus(200);

        $actions = array_column($this->audits($workspaceId), 'action');

        self::assertSame(['uploaded', 'trashed', 'restored'], $actions);
    }

    // --- MEDIA-AUDIT-TENANT-03 --------------------------------------------

    public function test_one_workspace_never_sees_another_workspaces_trail(): void
    {
        Storage::fake('local');
        $mehmet = $this->verifiedUser();
        $komsu = $this->verifiedUser();
        $mine = $this->ownerWorkspace($mehmet, 'zeytin-audit-mine');
        $theirs = $this->ownerWorkspace($komsu, 'zeytin-audit-theirs');

        $this->upload($mehmet, $mine);
        $this->upload($komsu, $theirs);

        self::assertCount(1, $this->audits($mine), 'MEDIA-AUDIT-TENANT-03: iz kiracı sınırında kalmalı.');
        self::assertCount(1, $this->audits($theirs));
    }
}
