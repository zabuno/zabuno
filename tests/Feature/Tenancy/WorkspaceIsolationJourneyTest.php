<?php

declare(strict_types=1);

namespace Tests\Feature\Tenancy;

use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Blind RED test candidate for S1-WP02B (docs/34). Frozen scope: a verified
 * authenticated user creates a workspace (atomic with owner membership),
 * lists only their own memberships, and switches/reads the current
 * workspace-context, which is always revalidated server-side against
 * membership + workspace state rather than trusted from the session alone.
 * No S1-WP02B production code exists yet (no app/Domain/Tenancy,
 * app/Models/Workspace, or routes/api.php workspace routes), so every
 * request below hits a route/table that does not exist, and every
 * assertion is expected to fail RED until S1-WP02B is implemented.
 *
 * Requirement IDs covered (docs/34 RED→GREEN table): S1WP02B-AUTH-01,
 * CREATE-01, CREATE-TXN-01, MEMBERSHIP-UNIQUE-01, LIST-01, SWITCH-01,
 * CURRENT-01, TENANT-ESCAPE-01, TENANT-ESCAPE-02, SESSION-STATE-01,
 * STATE-01, USER-NOT-TENANT-BOUND-01, RATE-01, AI-01.
 */
final class WorkspaceIsolationJourneyTest extends TestCase
{
    use RefreshDatabase;

    private const WORKSPACES_URI = '/api/workspaces';

    private const CONTEXT_URI = '/api/workspace-context';

    private function jsonHeaders(): array
    {
        return ['Accept' => 'application/json'];
    }

    private function verifiedUser(): User
    {
        return User::factory()->create(['email_verified_at' => now()]);
    }

    // --- S1WP02B-AUTH-01 -----------------------------------------------

    public function test_all_workspace_endpoints_require_authenticated_and_verified_user(): void
    {
        $unverified = User::factory()->unverified()->create();

        $guestRequests = [
            'create' => fn () => $this->withHeaders($this->jsonHeaders())->postJson(self::WORKSPACES_URI, ['name' => 'Zeytin Restoranları']),
            'list' => fn () => $this->withHeaders($this->jsonHeaders())->getJson(self::WORKSPACES_URI),
            'switch' => fn () => $this->withHeaders($this->jsonHeaders())->putJson(self::CONTEXT_URI, ['workspace_id' => 1]),
            'current' => fn () => $this->withHeaders($this->jsonHeaders())->getJson(self::CONTEXT_URI),
        ];

        foreach ($guestRequests as $label => $request) {
            $response = $request();
            self::assertContains(
                $response->getStatusCode(),
                [401, 403],
                "AUTH-01: kimliksiz {$label} isteği 401/403 ile reddedilmeli (404 kabul edilmez)."
            );
        }

        $unverifiedRequests = [
            'create' => fn () => $this->actingAs($unverified)->withHeaders($this->jsonHeaders())->postJson(self::WORKSPACES_URI, ['name' => 'Zeytin Restoranları']),
            'list' => fn () => $this->actingAs($unverified)->withHeaders($this->jsonHeaders())->getJson(self::WORKSPACES_URI),
            'switch' => fn () => $this->actingAs($unverified)->withHeaders($this->jsonHeaders())->putJson(self::CONTEXT_URI, ['workspace_id' => 1]),
            'current' => fn () => $this->actingAs($unverified)->withHeaders($this->jsonHeaders())->getJson(self::CONTEXT_URI),
        ];

        foreach ($unverifiedRequests as $label => $request) {
            $response = $request();
            $response->assertStatus(403, "AUTH-01: doğrulanmamış (unverified) kullanıcının {$label} isteği 403 ile reddedilmeli.");
        }
    }

    // --- S1WP02B-CREATE-01 -----------------------------------------------

    public function test_create_returns_201_with_onboarding_state_and_generated_slug_and_becomes_current_context(): void
    {
        $user = $this->verifiedUser();

        $response = $this->actingAs($user)->withHeaders($this->jsonHeaders())
            ->postJson(self::WORKSPACES_URI, ['name' => 'Zeytin Restoranları']);

        $response->assertStatus(201);
        $body = $response->json();

        self::assertSame('Zeytin Restoranları', $body['name'] ?? null, 'CREATE-01: dönen isim gönderilenle aynı olmalı.');
        self::assertSame('onboarding', $body['state'] ?? null, 'CREATE-01: yeni workspace onboarding durumunda başlamalı.');
        self::assertNotEmpty($body['slug'] ?? null, 'CREATE-01: sunucu benzersiz slug üretmeli.');

        $this->assertDatabaseHas('workspaces', [
            'name' => 'Zeytin Restoranları',
            'state' => 'onboarding',
        ]);

        $current = $this->actingAs($user)->withHeaders($this->jsonHeaders())->getJson(self::CONTEXT_URI);
        $current->assertStatus(200);
        self::assertSame($body['id'] ?? null, $current->json('id') ?? $current->json('workspace_id'), 'CREATE-01: oluşturulan workspace mevcut bağlam olmalı.');
    }

    public function test_create_generates_unique_slugs_for_repeated_identical_names(): void
    {
        $user = $this->verifiedUser();

        $first = $this->actingAs($user)->withHeaders($this->jsonHeaders())
            ->postJson(self::WORKSPACES_URI, ['name' => 'Zeytin Restoranları']);
        $second = $this->actingAs($user)->withHeaders($this->jsonHeaders())
            ->postJson(self::WORKSPACES_URI, ['name' => 'Zeytin Restoranları']);

        $first->assertStatus(201);
        $second->assertStatus(201);

        self::assertNotSame(
            $first->json('slug'),
            $second->json('slug'),
            'CREATE-01: aynı isimle iki workspace aynı slug\'ı paylaşamaz (benzersizlik).'
        );
    }

    // --- S1WP02B-CREATE-TXN-01 --------------------------------------------

    public function test_create_and_owner_membership_are_atomic_and_a_mid_transaction_failure_leaves_no_records(): void
    {
        $user = $this->verifiedUser();

        $beforeWorkspaceCount = DB::table('workspaces')->count();
        $beforeMembershipCount = DB::table('workspace_memberships')->count();

        // Deterministic, implementation-neutral DB-induced failure: an
        // SQLite BEFORE INSERT trigger unconditionally aborts every insert
        // into workspace_memberships for the duration of this test, forcing
        // whatever real INSERT the create transaction issues to fail
        // mid-transaction without assuming any auto-increment value or
        // requiring a production-only test hook.
        DB::statement(
            'CREATE TRIGGER wp02b_block_membership_insert '
            .'BEFORE INSERT ON workspace_memberships '
            .'BEGIN SELECT RAISE(ABORT, \'CREATE-TXN-01 forced membership failure\'); END;'
        );

        try {
            // The exception raised by the trigger is expected to be caught
            // and rendered by the application's own exception handling as an
            // HTTP error response (not to bubble out of the test); we do not
            // assert a specific status here, since the invariant under test
            // is the atomic row-count outcome, not the exact error code.
            $this->actingAs($user)->withHeaders($this->jsonHeaders())
                ->postJson(self::WORKSPACES_URI, ['name' => 'Zeytin Restoranları']);
        } finally {
            DB::statement('DROP TRIGGER IF EXISTS wp02b_block_membership_insert;');
        }

        $afterWorkspaceCount = DB::table('workspaces')->count();
        $afterMembershipCount = DB::table('workspace_memberships')->count();

        self::assertSame(
            $beforeWorkspaceCount,
            $afterWorkspaceCount,
            'CREATE-TXN-01: owner-membership INSERT ara hatası sonrası sahipsiz workspace kalmamalı (workspace sayısı değişmemeli).'
        );
        self::assertSame(
            $beforeMembershipCount,
            $afterMembershipCount,
            'CREATE-TXN-01: owner-membership INSERT ara hatası sonrası hiçbir membership kaydı kalmamalı.'
        );
    }

    // --- S1WP02B-MEMBERSHIP-UNIQUE-01 ---------------------------------------

    public function test_duplicate_workspace_membership_pair_is_rejected_by_db_unique_constraint(): void
    {
        $user = $this->verifiedUser();

        $workspaceId = DB::table('workspaces')->insertGetId([
            'name' => 'Zeytin Restoranları',
            'slug' => 'zeytin-restoranlari',
            'state' => 'onboarding',
            'created_by' => $user->id,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('workspace_memberships')->insert([
            'workspace_id' => $workspaceId,
            'user_id' => $user->id,
            'role' => 'owner',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->expectException(QueryException::class);

        DB::table('workspace_memberships')->insert([
            'workspace_id' => $workspaceId,
            'user_id' => $user->id,
            'role' => 'owner',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    // --- S1WP02B-LIST-01 -----------------------------------------------------

    public function test_list_returns_only_the_callers_own_memberships(): void
    {
        $ayse = $this->verifiedUser();
        $deniz = $this->verifiedUser();

        $this->actingAs($ayse)->withHeaders($this->jsonHeaders())
            ->postJson(self::WORKSPACES_URI, ['name' => 'Zeytin Restoranları']);
        $this->actingAs($deniz)->withHeaders($this->jsonHeaders())
            ->postJson(self::WORKSPACES_URI, ['name' => 'Deniz Kebap']);

        $list = $this->actingAs($ayse)->withHeaders($this->jsonHeaders())->getJson(self::WORKSPACES_URI);

        $list->assertStatus(200);
        $names = array_column((array) $list->json(), 'name');

        self::assertContains('Zeytin Restoranları', $names, 'LIST-01: çağıranın kendi workspace\'i listede olmalı.');
        self::assertNotContains('Deniz Kebap', $names, 'LIST-01: başka kullanıcının workspace\'i sızmamalı.');
    }

    // --- S1WP02B-SWITCH-01 ----------------------------------------------------

    public function test_switching_to_a_workspace_the_caller_is_a_member_of_returns_200(): void
    {
        $user = $this->verifiedUser();
        $create = $this->actingAs($user)->withHeaders($this->jsonHeaders())
            ->postJson(self::WORKSPACES_URI, ['name' => 'Zeytin Restoranları']);

        $switch = $this->actingAs($user)->withHeaders($this->jsonHeaders())
            ->putJson(self::CONTEXT_URI, ['workspace_id' => $create->json('id')]);

        $switch->assertStatus(200);
    }

    // --- S1WP02B-CURRENT-01 ----------------------------------------------------

    public function test_current_context_returns_the_active_workspace_summary(): void
    {
        $user = $this->verifiedUser();
        $create = $this->actingAs($user)->withHeaders($this->jsonHeaders())
            ->postJson(self::WORKSPACES_URI, ['name' => 'Zeytin Restoranları']);

        $current = $this->actingAs($user)->withHeaders($this->jsonHeaders())->getJson(self::CONTEXT_URI);

        $current->assertStatus(200);
        self::assertSame($create->json('id'), $current->json('id') ?? $current->json('workspace_id'));
    }

    public function test_current_context_without_any_prior_selection_returns_409_workspace_context_required(): void
    {
        $user = $this->verifiedUser();

        $current = $this->actingAs($user)->withHeaders($this->jsonHeaders())->getJson(self::CONTEXT_URI);

        $current->assertStatus(409);
        self::assertSame('workspace_context_required', $current->json('error') ?? $current->json('code'));
    }

    // --- S1WP02B-TENANT-ESCAPE-01 / 02 -----------------------------------------

    public function test_switching_to_a_foreign_workspace_returns_404(): void
    {
        $ayse = $this->verifiedUser();
        $deniz = $this->verifiedUser();

        $foreign = $this->actingAs($deniz)->withHeaders($this->jsonHeaders())
            ->postJson(self::WORKSPACES_URI, ['name' => 'Deniz Kebap']);

        $foreign->assertStatus(201, 'TENANT-ESCAPE-01: ön koşul workspace oluşturma 201 dönmeli (yoksa sonraki 404 rota yokluğundan kaynaklanabilir).');
        self::assertIsInt($foreign->json('id'), 'TENANT-ESCAPE-01: oluşturulan yabancı workspace tamsayı id taşımalı (yoksa sonraki 404 rota yokluğundan kaynaklanabilir).');

        $switch = $this->actingAs($ayse)->withHeaders($this->jsonHeaders())
            ->putJson(self::CONTEXT_URI, ['workspace_id' => $foreign->json('id')]);

        $switch->assertStatus(404, 'TENANT-ESCAPE-01: üye olunmayan workspace\'e switch 404 dönmeli.');
    }

    public function test_switching_to_a_nonexistent_workspace_returns_the_identical_404_signature_as_a_foreign_workspace(): void
    {
        $ayse = $this->verifiedUser();
        $deniz = $this->verifiedUser();

        $foreign = $this->actingAs($deniz)->withHeaders($this->jsonHeaders())
            ->postJson(self::WORKSPACES_URI, ['name' => 'Deniz Kebap']);

        $foreign->assertStatus(201, 'TENANT-ESCAPE-02: ön koşul workspace oluşturma 201 dönmeli (yoksa sonraki 404 rota yokluğundan kaynaklanabilir).');
        self::assertIsInt($foreign->json('id'), 'TENANT-ESCAPE-02: oluşturulan yabancı workspace tamsayı id taşımalı (yoksa sonraki 404 rota yokluğundan kaynaklanabilir).');

        $foreignSwitch = $this->actingAs($ayse)->withHeaders($this->jsonHeaders())
            ->putJson(self::CONTEXT_URI, ['workspace_id' => $foreign->json('id')]);

        $nonexistentSwitch = $this->actingAs($ayse)->withHeaders($this->jsonHeaders())
            ->putJson(self::CONTEXT_URI, ['workspace_id' => 999999999]);

        $nonexistentSwitch->assertStatus(404, 'TENANT-ESCAPE-02: var olmayan workspace\'e switch 404 dönmeli.');
        self::assertSame(
            $foreignSwitch->getStatusCode(),
            $nonexistentSwitch->getStatusCode(),
            'TENANT-ESCAPE-02: yabancı ve var-olmayan aynı imzayı taşımalı (enumeration-safe).'
        );
        self::assertSame(
            $foreignSwitch->getContent(),
            $nonexistentSwitch->getContent(),
            'TENANT-ESCAPE-02: yabancı ve var-olmayan yanıt gövdesi bit-bit aynı olmalı (existence sızdırılmaz).'
        );
    }

    // --- S1WP02B-SESSION-STATE-01 --------------------------------------------

    public function test_stale_session_context_referencing_a_membership_the_user_left_is_silently_cleared_on_read(): void
    {
        $user = $this->verifiedUser();
        $create = $this->actingAs($user)->withHeaders($this->jsonHeaders())
            ->postJson(self::WORKSPACES_URI, ['name' => 'Zeytin Restoranları']);

        $workspaceId = $create->json('id');

        // Membership revoked out-of-band (e.g. removed by another process); the
        // session may still carry the stale workspace id.
        $membership = DB::table('workspace_memberships')->where('workspace_id', $workspaceId)->where('user_id', $user->id)->first();
        DB::table('workspace_memberships')->where('workspace_id', $workspaceId)->where('user_id', $user->id)->delete();

        $current = $this->actingAs($user)->withHeaders($this->jsonHeaders())->getJson(self::CONTEXT_URI);

        $current->assertStatus(409, 'SESSION-STATE-01: bayat/yabancılaşmış bağlam sessizce temizlenip 409 dönmeli.');
        self::assertStringNotContainsString(
            (string) $workspaceId,
            (string) $current->getContent(),
            'SESSION-STATE-01: temizlenen bağlamın id\'si yanıt gövdesinde sızdırılmamalı.'
        );

        // Prove the clearing is real (server-side session state), not an
        // artifact of re-checking membership on every request without ever
        // having cleared anything: restore the exact same membership row
        // without performing any new switch, and re-read current context.
        // If the stale workspace id had not actually been cleared, this
        // restored membership would make the very same GET return 200 again.
        DB::table('workspace_memberships')->insert([
            'workspace_id' => $membership->workspace_id,
            'user_id' => $membership->user_id,
            'role' => $membership->role,
            'created_at' => $membership->created_at,
            'updated_at' => $membership->updated_at,
        ]);

        $afterRestore = $this->actingAs($user)->withHeaders($this->jsonHeaders())->getJson(self::CONTEXT_URI);

        $afterRestore->assertStatus(
            409,
            'SESSION-STATE-01: bağlam gerçekten temizlenmişse, üyelik geri yüklense bile tekrar switch yapılmadan 409 dönmeye devam etmeli.'
        );
    }

    // --- S1WP02B-STATE-01 ------------------------------------------------------

    public static function ineligibleStateProvider(): array
    {
        return [
            'suspended' => ['suspended'],
            'archived' => ['archived'],
            'deletion_pending' => ['deletion_pending'],
            'deleted' => ['deleted'],
        ];
    }

    #[DataProvider('ineligibleStateProvider')]
    public function test_ineligible_workspace_states_are_rejected_as_current_context(string $state): void
    {
        $user = $this->verifiedUser();
        $create = $this->actingAs($user)->withHeaders($this->jsonHeaders())
            ->postJson(self::WORKSPACES_URI, ['name' => 'Zeytin Restoranları']);
        $workspaceId = $create->json('id');

        DB::table('workspaces')->where('id', $workspaceId)->update(['state' => $state]);

        // GET current: the workspace was the already-selected context (set by
        // create above), so revalidation must reject it with 409 and clear it.
        $current = $this->actingAs($user)->withHeaders($this->jsonHeaders())->getJson(self::CONTEXT_URI);
        $current->assertStatus(409, "STATE-01: {$state} durumundaki mevcut bağlam GET current-context'te 409 dönüp temizlenmeli.");

        // PUT switch: attempting to (re-)select the same ineligible workspace
        // must be rejected enumeration-safe, i.e. identically to a foreign
        // or nonexistent workspace id (404), not merely "not current".
        $switch = $this->actingAs($user)->withHeaders($this->jsonHeaders())
            ->putJson(self::CONTEXT_URI, ['workspace_id' => $workspaceId]);

        $switch->assertStatus(404, "STATE-01: {$state} durumundaki workspace mevcut bağlam olarak seçilemez (switch).");
    }

    public function test_onboarding_and_active_states_are_eligible_as_current_context(): void
    {
        $user = $this->verifiedUser();
        $create = $this->actingAs($user)->withHeaders($this->jsonHeaders())
            ->postJson(self::WORKSPACES_URI, ['name' => 'Zeytin Restoranları']);
        $workspaceId = $create->json('id');

        DB::table('workspaces')->where('id', $workspaceId)->update(['state' => 'active']);

        $switch = $this->actingAs($user)->withHeaders($this->jsonHeaders())
            ->putJson(self::CONTEXT_URI, ['workspace_id' => $workspaceId]);

        $switch->assertStatus(200, 'STATE-01: active durumundaki workspace mevcut bağlam olarak seçilebilir.');
    }

    // --- S1WP02B-USER-NOT-TENANT-BOUND-01 ---------------------------------

    public function test_a_single_user_may_belong_to_multiple_workspaces(): void
    {
        $ayse = $this->verifiedUser();

        $first = $this->actingAs($ayse)->withHeaders($this->jsonHeaders())
            ->postJson(self::WORKSPACES_URI, ['name' => 'Zeytin Restoranları']);
        $second = $this->actingAs($ayse)->withHeaders($this->jsonHeaders())
            ->postJson(self::WORKSPACES_URI, ['name' => 'Danışmanlık Zinciri']);

        $first->assertStatus(201);
        $second->assertStatus(201);

        $list = $this->actingAs($ayse)->withHeaders($this->jsonHeaders())->getJson(self::WORKSPACES_URI);
        $names = array_column((array) $list->json(), 'name');

        self::assertContains('Zeytin Restoranları', $names, 'USER-NOT-TENANT-BOUND-01: kullanıcı birden fazla workspace\'e üye olabilmeli.');
        self::assertContains('Danışmanlık Zinciri', $names, 'USER-NOT-TENANT-BOUND-01: kullanıcı birden fazla workspace\'e üye olabilmeli.');
    }

    // --- S1WP02B-RATE-01 --------------------------------------------------

    public function test_workspace_creation_throttles_at_5_per_minute_per_verified_user(): void
    {
        $user = $this->verifiedUser();

        $last = null;
        for ($i = 0; $i < 6; $i++) {
            $last = $this->actingAs($user)->withHeaders($this->jsonHeaders())
                ->postJson(self::WORKSPACES_URI, ['name' => "Workspace {$i}"]);
        }

        $last->assertStatus(429, 'RATE-01: doğrulanmış kullanıcı başına 6. create isteği 429 dönmeli (5/dk).');
    }

    // --- S1WP02B-AI-01 --------------------------------------------------

    public function test_create_response_is_identical_with_ai_kill_switch_and_zero_credit_engaged(): void
    {
        Http::fake();

        $user = $this->verifiedUser();

        $baseline = $this->actingAs($user)->withHeaders($this->jsonHeaders())
            ->postJson(self::WORKSPACES_URI, ['name' => 'Zeytin Restoranları']);

        Config::set('ai.enabled', false);
        Config::set('ai.credit', 0);

        // Distinct name: rows from the baseline call are deliberately left in
        // place (no truncate/FK-disable), so a second workspace needs its own
        // name to avoid a slug-uniqueness confound between the two calls.
        $aiOff = $this->actingAs($user)->withHeaders($this->jsonHeaders())
            ->postJson(self::WORKSPACES_URI, ['name' => 'Zeytin Restoranları AI Kapalı']);

        self::assertSame(
            $baseline->getStatusCode(),
            $aiOff->getStatusCode(),
            'AI-01: AI kill-switch/no-credit karar yolunu değiştirmemeli (status).'
        );
        self::assertSame(
            $baseline->json('state'),
            $aiOff->json('state'),
            'AI-01: AI kill-switch/no-credit karar yolunu değiştirmemeli (state).'
        );
        self::assertSame(
            'Zeytin Restoranları AI Kapalı',
            $aiOff->json('name'),
            'AI-01: AI kapalıyken de gönderilen isim aynen dönmeli.'
        );

        Http::assertNothingSent();
    }
}
