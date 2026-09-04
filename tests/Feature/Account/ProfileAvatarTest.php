<?php

declare(strict_types=1);

namespace Tests\Feature\Account;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Profil fotoğrafı — sahibin isteği (2026-09-04): "avatar profile fotoğrafımı
 * (media components ile) yükleyebilmeliyim".
 *
 * Kullanıcı yolculuğu: Mehmet Usta hesap menüsünden "Profil"i açar, kendi
 * fotoğrafını medya kütüphanesine yükler ve onu profil fotoğrafı yapar.
 * Fotoğraf ayrı bir dosya yolu değil, kütüphanedeki bir varlıktır: taranır,
 * türevleri üretilir, kotaya sayılır.
 *
 * Kritik sınır: fotoğraf yalnız kullanıcının ÜYE OLDUĞU bir çalışma alanının
 * varlığı olabilir. Aksi hâlde bir kiracı, komşu restoranın görsel kimliğini
 * kendi profiline bağlayarak o dosyayı dışarı sızdırabilirdi.
 *
 * Gereksinim: ACCOUNT-AVATAR-BIND-01, ACCOUNT-AVATAR-ESCAPE-01,
 * ACCOUNT-AVATAR-CLEAR-01.
 */
final class ProfileAvatarTest extends TestCase
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

    private function uploadAvatarAsset(User $user, int $workspaceId): int
    {
        $response = $this->actingAs($user)->withHeaders(['Accept' => 'application/json'])->post(
            "/api/workspaces/{$workspaceId}/media",
            [
                'file' => UploadedFile::fake()->image('mehmet.jpg', 400, 400)->size(60),
                'altText' => 'Mehmet Usta portresi',
                'slot' => 'profileAvatar',
            ]
        );

        $response->assertStatus(201);

        return (int) $response->json('id');
    }

    public function test_a_member_can_bind_and_clear_their_own_avatar(): void
    {
        Storage::fake('local');
        $user = $this->verifiedUser();
        $workspaceId = $this->ownerWorkspace($user, 'zeytin-avatar-bind');
        $assetId = $this->uploadAvatarAsset($user, $workspaceId);

        $bind = $this->actingAs($user)->withHeaders(['Accept' => 'application/json'])
            ->putJson('/api/user/avatar', ['mediaAssetId' => $assetId]);

        $bind->assertStatus(200);
        self::assertSame($assetId, $bind->json('avatarMediaAssetId'), 'ACCOUNT-AVATAR-BIND-01: bağlanan varlık kimliği geri dönmeli.');

        $me = $this->actingAs($user)->withHeaders(['Accept' => 'application/json'])->getJson('/api/user');
        $me->assertStatus(200);
        self::assertSame($assetId, $me->json('avatarMediaAssetId'), 'ACCOUNT-AVATAR-BIND-01: /api/user profil fotoğrafını bildirmeli.');

        $clear = $this->actingAs($user)->withHeaders(['Accept' => 'application/json'])
            ->putJson('/api/user/avatar', ['mediaAssetId' => null]);

        $clear->assertStatus(200);
        self::assertNull($clear->json('avatarMediaAssetId'), 'ACCOUNT-AVATAR-CLEAR-01: fotoğraf kaldırılabilmeli.');
        self::assertNull(
            DB::table('users')->where('id', $user->id)->value('avatar_media_asset_id'),
            'ACCOUNT-AVATAR-CLEAR-01: kayıt da temizlenmeli.'
        );
    }

    public function test_binding_a_foreign_workspaces_asset_is_enumeration_safe_404(): void
    {
        Storage::fake('local');
        $mehmet = $this->verifiedUser();
        $komsu = $this->verifiedUser();
        $this->ownerWorkspace($mehmet, 'zeytin-avatar-escape-a');
        $komsuWorkspace = $this->ownerWorkspace($komsu, 'zeytin-avatar-escape-b');
        $foreignAssetId = $this->uploadAvatarAsset($komsu, $komsuWorkspace);

        $response = $this->actingAs($mehmet)->withHeaders(['Accept' => 'application/json'])
            ->putJson('/api/user/avatar', ['mediaAssetId' => $foreignAssetId]);

        $response->assertStatus(404, 'ACCOUNT-AVATAR-ESCAPE-01: yabancı çalışma alanının görseli 404 olmalı — 403 varlığı ele verirdi.');
        self::assertNull(
            DB::table('users')->where('id', $mehmet->id)->value('avatar_media_asset_id'),
            'ACCOUNT-AVATAR-ESCAPE-01: reddedilen bağlama hiçbir şey yazmamalı.'
        );
    }

    public function test_an_unauthenticated_caller_cannot_bind_an_avatar(): void
    {
        $response = $this->withHeaders(['Accept' => 'application/json'])
            ->putJson('/api/user/avatar', ['mediaAssetId' => 1]);

        $response->assertStatus(401);
    }
}
