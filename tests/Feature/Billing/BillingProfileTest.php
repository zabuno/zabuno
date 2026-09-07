<?php

declare(strict_types=1);

namespace Tests\Feature\Billing;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * BILLING-PROFILE — alıcı bilgisi uydurulmaz, kiracı kendisi yazar.
 *
 * Bugüne kadar tek alıcı profili `.env`'deki sandbox personasıydı. Gerçek
 * bir fatura gerçek bir unvan, vergi numarası ve adres ister; bunlar
 * çalışma alanı başına bir satırdır ve TAM olmadan kaydedilmez — yarım bir
 * profil "var" görünüp ilk ödemede patlamamalı.
 */
final class BillingProfileTest extends TestCase
{
    use RefreshDatabase;

    private function jsonHeaders(): array
    {
        return ['Accept' => 'application/json'];
    }

    private function verifiedUser(string $email): User
    {
        return User::factory()->create(['email' => $email, 'email_verified_at' => now()]);
    }

    private function workspaceOwnedBy(User $owner, string $slug): int
    {
        $workspaceId = (int) DB::table('workspaces')->insertGetId([
            'name' => 'Kadıköy Kebap',
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

    private function addMember(int $workspaceId, User $user, string $role): void
    {
        DB::table('workspace_memberships')->insert([
            'workspace_id' => $workspaceId,
            'user_id' => $user->id,
            'role' => $role,
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    private function uri(int $workspaceId): string
    {
        return "/api/workspaces/{$workspaceId}/billing-profile";
    }

    private function validProfile(): array
    {
        return [
            'legal_name' => 'Kadıköy Kebap Gıda Ltd. Şti.',
            'tax_number' => '1234567890',
            'tax_office' => 'Kadıköy',
            'address' => 'Moda Cad. No:1',
            'city' => 'Istanbul',
            'country' => 'TR',
            'email' => 'muhasebe@kadikoykebap.test',
            'phone' => '+905551112233',
        ];
    }

    #[Test]
    public function the_profile_surface_is_enumeration_safe_and_write_needs_billing_manage(): void
    {
        $owner = $this->verifiedUser('owner-profile@example.test');
        $workspaceId = $this->workspaceOwnedBy($owner, 'profile-authz');
        $manager = $this->verifiedUser('manager-profile@example.test');
        $this->addMember($workspaceId, $manager, 'manager');
        $outsider = $this->verifiedUser('outsider-profile@example.test');

        $this->withHeaders($this->jsonHeaders())->getJson($this->uri($workspaceId))->assertStatus(401);
        $this->actingAs($outsider)->withHeaders($this->jsonHeaders())->getJson($this->uri($workspaceId))->assertStatus(404);
        $this->actingAs($manager)->withHeaders($this->jsonHeaders())->getJson($this->uri($workspaceId))->assertOk();
        $this->actingAs($manager)->withHeaders($this->jsonHeaders())->putJson($this->uri($workspaceId), $this->validProfile())->assertStatus(404);

        self::assertSame(0, DB::table('billing_profiles')->count());
    }

    #[Test]
    public function a_missing_profile_says_so_and_an_incomplete_profile_is_rejected_field_by_field(): void
    {
        $owner = $this->verifiedUser('owner-incomplete@example.test');
        $workspaceId = $this->workspaceOwnedBy($owner, 'profile-incomplete');

        $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->getJson($this->uri($workspaceId))
            ->assertOk()
            ->assertExactJson(['state' => 'missing']);

        $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->putJson($this->uri($workspaceId), array_merge($this->validProfile(), [
                'legal_name' => '',
                'email' => 'not-an-email',
                'country' => 'Turkey',
            ]))
            ->assertStatus(422)
            ->assertJsonValidationErrors(['legal_name', 'email', 'country']);

        self::assertSame(0, DB::table('billing_profiles')->count(), 'Yarım profil KAYDEDİLMEZ.');
    }

    #[Test]
    public function a_complete_profile_is_stored_once_per_workspace_and_read_back(): void
    {
        $owner = $this->verifiedUser('owner-complete@example.test');
        $workspaceId = $this->workspaceOwnedBy($owner, 'profile-complete');

        $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->putJson($this->uri($workspaceId), $this->validProfile())
            ->assertOk()
            ->assertJson(array_merge(['state' => 'complete'], $this->validProfile()));

        $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->putJson($this->uri($workspaceId), array_merge($this->validProfile(), ['city' => 'Ankara']))
            ->assertOk()
            ->assertJsonPath('city', 'Ankara');

        self::assertSame(1, DB::table('billing_profiles')->where('workspace_id', $workspaceId)->count(), 'Çalışma alanı başına TEK satır: ikinci kayıt günceller.');

        $this->actingAs($owner)->withHeaders($this->jsonHeaders())
            ->getJson($this->uri($workspaceId))
            ->assertOk()
            ->assertJsonPath('state', 'complete')
            ->assertJsonPath('legal_name', 'Kadıköy Kebap Gıda Ltd. Şti.')
            ->assertJsonPath('city', 'Ankara');
    }
}
