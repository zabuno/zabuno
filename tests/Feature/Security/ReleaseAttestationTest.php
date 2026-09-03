<?php

declare(strict_types=1);

namespace Tests\Feature\Security;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * İNSAN TANIKLIĞI KANITLARI — `docs/98` FF-63.
 *
 * Readiness listesinin üç maddesi (QR saha taraması, RPO/RTO kararı, ASVS
 * raporu) bir test koşturularak üretilemez; bir insanın söylemesiyle var
 * olur ve KİMİN, NE ZAMAN söylediği kanıtın kendisidir. Bu testler o
 * kaydın dürüst kaldığını sınar: yanlış durum kabul edilmez, zorunlu ayrıntı
 * atlanamaz, satır elle değiştirilirse "geçti" demez.
 */
final class ReleaseAttestationTest extends TestCase
{
    use RefreshDatabase;

    private function headers(): array
    {
        return ['Accept' => 'application/json'];
    }

    private function superAdmin(): User
    {
        $user = User::factory()->create(['email_verified_at' => now()]);
        DB::table('platform_role_assignments')->insert([
            'user_id' => $user->id, 'role' => 'super_admin', 'created_at' => now(), 'updated_at' => now(),
        ]);

        return $user;
    }

    /** @return array{User, int} */
    private function ownerWithWorkspace(): array
    {
        $owner = User::factory()->create(['email_verified_at' => now()]);
        $workspaceId = (int) DB::table('workspaces')->insertGetId([
            'name' => 'Zeytin', 'slug' => 'att-'.Str::lower(Str::random(6)), 'state' => 'active',
            'created_by' => $owner->id, 'created_at' => now(), 'updated_at' => now(),
        ]);
        DB::table('workspace_memberships')->insert([
            'workspace_id' => $workspaceId, 'user_id' => $owner->id, 'role' => 'owner',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        return [$owner, $workspaceId];
    }

    // --- ATTEST-RECORD-01 -------------------------------------------------

    #[Test]
    public function a_superadmin_records_the_physical_qr_scan_and_the_owner_reads_it_back(): void
    {
        $admin = $this->superAdmin();
        [$owner, $workspaceId] = $this->ownerWithWorkspace();

        $this->actingAs($admin)->withHeaders($this->headers())
            ->postJson('/api/admin/release-attestations', [
                'key' => 'qr-physical-scan',
                'status' => 'passed',
                'summary' => 'Basılı QR iPhone kamerasıyla tarandı, yayımlı menü açıldı.',
                'payload' => ['device' => 'iPhone 15', 'menu' => 'Ana Menü'],
            ])->assertStatus(201);

        $response = $this->actingAs($owner)->withHeaders($this->headers())
            ->getJson("/api/workspaces/{$workspaceId}/security/evidence/attestations/qr-physical-scan")
            ->assertStatus(200);

        self::assertSame('passed', $response->json('data.status'));
        // Makine kanıtından AYRI etiket: ekran ikisini karıştırmaz.
        self::assertSame('attestation', $response->json('data.kind'));
        self::assertSame($admin->name, $response->json('data.attested_by'));
        self::assertSame('iPhone 15', $response->json('data.payload.device'));
    }

    // --- ATTEST-STATUS-PER-KEY-01 -----------------------------------------

    /**
     * BİR KARAR "GEÇMEZ", BİR RAPOR "KARAR VERİLMEZ".
     *
     * Her maddenin kendi durum kümesi var; bir RPO/RTO kararını "passed"
     * diye kaydetmek, karar ile test sonucunu aynı şey sayardı.
     */
    #[Test]
    public function each_item_accepts_only_its_own_statuses(): void
    {
        $admin = $this->superAdmin();

        $this->actingAs($admin)->withHeaders($this->headers())
            ->postJson('/api/admin/release-attestations', [
                'key' => 'rpo-rto-decision', 'status' => 'passed', 'summary' => 'x',
                'payload' => ['rpo_hours' => '24', 'rto_hours' => '4'],
            ])->assertStatus(422);

        $this->actingAs($admin)->withHeaders($this->headers())
            ->postJson('/api/admin/release-attestations', [
                'key' => 'owasp-asvs-audit', 'status' => 'passed', 'summary' => 'x',
            ])->assertStatus(422);

        $this->actingAs($admin)->withHeaders($this->headers())
            ->postJson('/api/admin/release-attestations', [
                'key' => 'skynet', 'status' => 'recorded', 'summary' => 'x',
            ])->assertStatus(422);
    }

    // --- ATTEST-REQUIRED-DETAIL-01 ----------------------------------------

    /**
     * RAKAMSIZ RPO/RTO KARAR DEĞİLDİR.
     */
    #[Test]
    public function a_decision_without_its_numbers_is_rejected(): void
    {
        $this->actingAs($this->superAdmin())->withHeaders($this->headers())
            ->postJson('/api/admin/release-attestations', [
                'key' => 'rpo-rto-decision', 'status' => 'decided',
                'summary' => 'Günlük yedek yeter.',
            ])->assertStatus(422);
    }

    #[Test]
    public function a_scan_without_a_device_name_is_rejected(): void
    {
        $this->actingAs($this->superAdmin())->withHeaders($this->headers())
            ->postJson('/api/admin/release-attestations', [
                'key' => 'qr-physical-scan', 'status' => 'passed', 'summary' => 'Taradım.',
            ])->assertStatus(422);
    }

    // --- ATTEST-AUTHZ-01 --------------------------------------------------

    #[Test]
    public function a_workspace_owner_cannot_record_and_a_stranger_cannot_read(): void
    {
        [$owner, $workspaceId] = $this->ownerWithWorkspace();

        // Kaydetmek Stage 1 çıkış iddiasının parçasıdır — restoran sahibinin işi değil.
        $this->actingAs($owner)->withHeaders($this->headers())
            ->postJson('/api/admin/release-attestations', [
                'key' => 'owasp-asvs-audit', 'status' => 'recorded', 'summary' => 'x',
            ])->assertStatus(404);

        $stranger = User::factory()->create(['email_verified_at' => now()]);
        $this->actingAs($stranger)->withHeaders($this->headers())
            ->getJson("/api/workspaces/{$workspaceId}/security/evidence/attestations/qr-physical-scan")
            ->assertStatus(404);
    }

    #[Test]
    public function a_missing_attestation_answers_404_not_a_fabricated_status(): void
    {
        [$owner, $workspaceId] = $this->ownerWithWorkspace();

        $this->actingAs($owner)->withHeaders($this->headers())
            ->getJson("/api/workspaces/{$workspaceId}/security/evidence/attestations/rpo-rto-decision")
            ->assertStatus(404);
    }

    // --- ATTEST-TAMPER-01 -------------------------------------------------

    #[Test]
    public function a_row_edited_by_hand_fails_its_integrity_check(): void
    {
        $admin = $this->superAdmin();
        [$owner, $workspaceId] = $this->ownerWithWorkspace();

        $id = $this->actingAs($admin)->withHeaders($this->headers())
            ->postJson('/api/admin/release-attestations', [
                'key' => 'owasp-asvs-audit', 'status' => 'recorded',
                'summary' => 'ASVS 5.0 L1 self-assessment; üçüncü taraf denetimi DEĞİL.',
                'reference' => 'security/OWASP-ASVS-BASELINE.md',
            ])->json('id');

        DB::table('release_attestations')->where('id', $id)->update(['summary' => 'Sertifikalı ve pentest geçti.']);

        $this->actingAs($owner)->withHeaders($this->headers())
            ->getJson("/api/workspaces/{$workspaceId}/security/evidence/attestations/owasp-asvs-audit")
            ->assertStatus(500);
    }

    // --- ATTEST-LATEST-WINS-01 --------------------------------------------

    #[Test]
    public function the_newest_attestation_is_shown_and_the_old_one_stays_as_a_trail(): void
    {
        $admin = $this->superAdmin();
        [$owner, $workspaceId] = $this->ownerWithWorkspace();

        foreach ([['failed', 'İlk deneme: kamera odaklanamadı.'], ['passed', 'İkinci deneme: açıldı.']] as [$status, $summary]) {
            $this->actingAs($admin)->withHeaders($this->headers())
                ->postJson('/api/admin/release-attestations', [
                    'key' => 'qr-physical-scan', 'status' => $status, 'summary' => $summary,
                    'payload' => ['device' => 'Pixel 8'],
                ])->assertStatus(201);
        }

        $response = $this->actingAs($owner)->withHeaders($this->headers())
            ->getJson("/api/workspaces/{$workspaceId}/security/evidence/attestations/qr-physical-scan");

        self::assertSame('passed', $response->json('data.status'));
        self::assertSame(2, DB::table('release_attestations')->count(), 'Tanıklık düzeltilmez, yenisi eklenir.');
    }

    // --- ATTEST-COMMAND-01 ------------------------------------------------

    #[Test]
    public function the_artisan_command_records_the_same_way_the_panel_does(): void
    {
        $exit = Artisan::call('platform:evidence:attest', [
            'key' => 'rpo-rto-decision',
            '--status' => 'decided',
            '--summary' => 'Günlük yedek; 24 saat veri kaybı, 4 saat kesinti kabul edilir.',
            '--payload' => ['rpo_hours=24', 'rto_hours=4'],
        ]);

        self::assertSame(0, $exit);

        $row = DB::table('release_attestations')->where('key', 'rpo-rto-decision')->first();
        self::assertNotNull($row);
        self::assertSame('24', json_decode((string) $row->payload, true)['rpo_hours']);
        // Komuttan yazılan kaydın tanığı yok — panelden gelenin var. İkisi
        // de dürüst: komut "sunucuda biri çalıştırdı" der, isim uydurmaz.
        self::assertNull($row->attested_by_user_id);
    }

    #[Test]
    public function the_command_refuses_a_wrong_status_with_a_non_zero_exit(): void
    {
        $exit = Artisan::call('platform:evidence:attest', [
            'key' => 'rpo-rto-decision', '--status' => 'passed', '--summary' => 'x',
            '--payload' => ['rpo_hours=24', 'rto_hours=4'],
        ]);

        self::assertSame(1, $exit);
        self::assertSame(0, DB::table('release_attestations')->count());
    }

    // --- HOST-CAPABILITY-READ-01 ------------------------------------------

    #[Test]
    public function the_host_capability_record_is_readable_with_its_degradations(): void
    {
        [$owner, $workspaceId] = $this->ownerWithWorkspace();

        $this->actingAs($owner)->withHeaders($this->headers())
            ->getJson("/api/workspaces/{$workspaceId}/security/evidence/host-capability")
            ->assertStatus(404);

        Artisan::call('platform:evidence:host-capability');

        $response = $this->actingAs($owner)->withHeaders($this->headers())
            ->getJson("/api/workspaces/{$workspaceId}/security/evidence/host-capability")
            ->assertStatus(200);

        self::assertSame('automated', $response->json('data.kind'));
        self::assertIsArray($response->json('data.capabilities'));
        self::assertIsArray($response->json('data.degradations'));
        self::assertNotEmpty($response->json('data.claim'));
    }
}
