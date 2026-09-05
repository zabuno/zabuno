<?php

declare(strict_types=1);

namespace Tests\Feature\PlatformAdmin;

use App\Domain\Platform\PlatformRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * KULLANICI GÖRÜNÜRLÜĞÜ — `docs/122` §3 boşluk 2, Y2.
 *
 * Destek çağrısı hep aynı cümleyle başlar: *"Giremiyorum."* Bugün süperadmin
 * bu cümleye bakacak hiçbir yere sahip değil — kullanıcının hangi çalışma
 * alanlarında olduğu, hangi rolle, e-postasını doğrulamış mı, şu an açık bir
 * oturumu var mı, hiçbiri okunabilir değil.
 *
 * GÖRÜNÜRLÜK, MÜDAHALE DEĞİL. Bu pakette parola sıfırlama/değiştirme,
 * kilitleme, rol verme YOKTUR; uç yalnız okur. Bir destek aracının ilk
 * sürümünde yazma fiili, geri alınamayan ilk kazayı da beraberinde getirir.
 *
 * OLMAYAN ALAN UYDURULMAZ (`docs/109` §8.3/§8.4). Bu üründe bugün bir
 * kullanıcı KİLİDİ kavramı yok: ne `users` tablosunda bir sütun, ne bir
 * yasaklama kaydı var. Bu yüzden ekrana "kilitli değil" yazılmaz — bu cümle
 * ölçülmemiş bir güvenceyi ölçülmüş gibi gösterirdi. Aynı sebeple oturum
 * sayısı, oturumların veritabanında tutulmadığı kurulumda "0" değil
 * BİLİNMEYEN olarak döner.
 */
final class PlatformUserDirectoryApiTest extends TestCase
{
    use RefreshDatabase;

    private const URI = '/api/admin/users';

    private function superAdmin(): User
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        DB::table('platform_role_assignments')->insert([
            'user_id' => $user->getKey(),
            'role' => PlatformRole::SuperAdmin->value,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $user;
    }

    private function workspaceFor(User $owner, string $seed, string $role = 'owner'): int
    {
        $workspaceId = (int) DB::table('workspaces')->insertGetId([
            'name' => 'Restoran '.$seed,
            'slug' => $seed,
            'state' => 'active',
            'created_by' => $owner->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('workspace_memberships')->insert([
            'workspace_id' => $workspaceId,
            'user_id' => $owner->id,
            'role' => $role,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $workspaceId;
    }

    /** @param array<int, array<string, mixed>> $users */
    private function rowFor(array $users, string $email): array
    {
        foreach ($users as $user) {
            if ($user['email'] === $email) {
                return $user;
            }
        }

        self::fail("Dizinde {$email} yok.");
    }

    // --- yetki -------------------------------------------------------------

    #[Test]
    public function a_guest_never_reads_the_directory(): void
    {
        $this->getJson(self::URI)->assertUnauthorized();
    }

    #[Test]
    public function a_verified_user_without_the_platform_role_gets_a_plain_404(): void
    {
        $user = User::factory()->create(['email_verified_at' => now()]);

        $this->actingAs($user)->getJson(self::URI)->assertNotFound();
    }

    // --- içerik ------------------------------------------------------------

    #[Test]
    public function a_user_row_names_every_workspace_they_belong_to_with_the_role(): void
    {
        $staff = User::factory()->create([
            'name' => 'Kerem Aksoy',
            'email' => 'kerem@ornek.test',
            'email_verified_at' => now(),
        ]);

        $first = $this->workspaceFor($staff, 'birinci', 'owner');
        $second = $this->workspaceFor($staff, 'ikinci', 'editor');

        $response = $this->actingAs($this->superAdmin())->getJson(self::URI)->assertOk();

        $row = $this->rowFor($response->json('users'), 'kerem@ornek.test');

        self::assertSame('Kerem Aksoy', $row['name']);
        self::assertNotNull($row['emailVerifiedAt']);

        $memberships = collect($row['memberships'])->sortBy('workspaceId')->values()->all();
        self::assertSame([$first, $second], array_column($memberships, 'workspaceId'));
        self::assertSame(['owner', 'editor'], array_column($memberships, 'role'));
        self::assertSame('Restoran birinci', $memberships[0]['workspaceName']);
        self::assertSame('active', $memberships[0]['workspaceState']);
    }

    #[Test]
    public function an_unverified_address_reads_as_null_not_as_a_reassuring_sentence(): void
    {
        User::factory()->create(['email' => 'dogrulanmamis@ornek.test', 'email_verified_at' => null]);

        $response = $this->actingAs($this->superAdmin())->getJson(self::URI)->assertOk();

        self::assertNull($this->rowFor($response->json('users'), 'dogrulanmamis@ornek.test')['emailVerifiedAt']);
    }

    #[Test]
    public function a_user_who_belongs_to_nothing_is_still_listed_with_an_empty_membership_list(): void
    {
        /*
            Kaydolup hiçbir çalışma alanına girmemiş kullanıcı, destek
            çağrısının en sık kaynağıdır ("hesabım var ama hiçbir şey
            görmüyorum"). Onu listeden düşürmek, tam da aranan kişiyi
            gizlerdi.
        */
        User::factory()->create(['email' => 'yalniz@ornek.test', 'email_verified_at' => now()]);

        $response = $this->actingAs($this->superAdmin())->getJson(self::URI)->assertOk();

        self::assertSame([], $this->rowFor($response->json('users'), 'yalniz@ornek.test')['memberships']);
    }

    #[Test]
    public function the_platform_role_is_visible_so_an_admin_can_be_told_apart(): void
    {
        $admin = $this->superAdmin();

        $response = $this->actingAs($admin)->getJson(self::URI)->assertOk();

        self::assertSame(
            [PlatformRole::SuperAdmin->value],
            $this->rowFor($response->json('users'), $admin->email)['platformRoles']
        );
    }

    #[Test]
    public function the_search_matches_name_and_address_case_insensitively(): void
    {
        User::factory()->create(['name' => 'Zeynep Demir', 'email' => 'zeynep@ornek.test']);
        User::factory()->create(['name' => 'Ali Vural', 'email' => 'ali@baska.test']);

        $admin = $this->superAdmin();

        $byName = $this->actingAs($admin)->getJson(self::URI.'?query=ZEYNEP')->assertOk();
        self::assertSame(['zeynep@ornek.test'], array_column($byName->json('users'), 'email'));

        $byMail = $this->actingAs($admin)->getJson(self::URI.'?query=baska.test')->assertOk();
        self::assertSame(['ali@baska.test'], array_column($byMail->json('users'), 'email'));
    }

    // --- ölçülmemiş olan, ölçülmüş gibi gösterilmez ------------------------

    #[Test]
    public function session_facts_are_unknown_when_sessions_are_not_kept_in_the_database(): void
    {
        /*
            Oturum sürücüsü `file`/`array` olduğunda `sessions` tablosu BOŞ
            kalır. O boşluğa bakıp "hiç kimse açık değil" demek, ölçülmemiş
            olanı ölçülmüş gibi sunmaktır — ve destek görevlisini "kullanıcı
            zaten giriş yapmamış" diye yanlış bir yola sokar.
        */
        config(['session.driver' => 'array']);

        $response = $this->actingAs($this->superAdmin())->getJson(self::URI)->assertOk();

        $row = $response->json('users.0');
        self::assertFalse($row['sessions']['known']);
        self::assertArrayNotHasKey('active', $row['sessions']);
    }

    #[Test]
    public function open_sessions_are_counted_when_the_database_really_holds_them(): void
    {
        config(['session.driver' => 'database']);

        $person = User::factory()->create(['email' => 'acik@ornek.test']);

        DB::table('sessions')->insert([
            [
                'id' => 'oturum-bir',
                'user_id' => $person->id,
                'ip_address' => '203.0.113.9',
                'user_agent' => 'Test',
                'payload' => 'x',
                'last_activity' => 1_800_000_000,
            ],
            [
                'id' => 'oturum-iki',
                'user_id' => $person->id,
                'ip_address' => '203.0.113.10',
                'user_agent' => 'Test',
                'payload' => 'x',
                'last_activity' => 1_800_000_500,
            ],
        ]);

        $response = $this->actingAs($this->superAdmin())->getJson(self::URI.'?query=acik@ornek.test')->assertOk();

        $row = $response->json('users.0');
        self::assertTrue($row['sessions']['known']);
        self::assertSame(2, $row['sessions']['active']);
        self::assertSame(1_800_000_500, $row['sessions']['lastActivity']);
    }

    #[Test]
    public function the_directory_carries_no_secret_and_no_lock_claim(): void
    {
        config(['session.driver' => 'database']);

        $person = User::factory()->create(['email' => 'gizli@ornek.test']);
        DB::table('sessions')->insert([
            'id' => 'oturum-gizli',
            'user_id' => $person->id,
            'ip_address' => '203.0.113.11',
            'user_agent' => 'Mozilla/5.0 gizli',
            'payload' => 'cok-gizli-yuk',
            'last_activity' => 1_800_000_000,
        ]);

        $body = $this->actingAs($this->superAdmin())->getJson(self::URI)->assertOk()->getContent() ?: '';

        foreach (['password', 'remember_token', 'cok-gizli-yuk', '203.0.113.11', 'Mozilla', 'locked', 'suspended'] as $forbidden) {
            self::assertStringNotContainsString(
                $forbidden,
                $body,
                "Dizin gerekenden fazlasını (ya da olmayanı) taşımaz: {$forbidden}."
            );
        }
    }

    #[Test]
    public function the_directory_offers_no_write_verb_at_all(): void
    {
        // Parola sıfırlama/değiştirme bu pakette YOK: görünürlük istendi,
        // müdahale değil.
        $admin = $this->superAdmin();

        foreach (['post', 'put', 'patch', 'delete'] as $verb) {
            $this->actingAs($admin)->json(strtoupper($verb), self::URI)->assertStatus(405);
        }
    }
}
