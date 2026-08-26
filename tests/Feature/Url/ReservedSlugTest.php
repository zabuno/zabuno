<?php

declare(strict_types=1);

namespace Tests\Feature\Url;

use App\Domain\Url\UrlPolicy;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * URL ad alanı koruması.
 *
 * Bir işletme kendine `menu` slug'ını alabilseydi, o yol iki şey birden
 * ifade ederdi ve hangisinin kazandığı route sırasına kalırdı — bir gün
 * yapılan masum bir route düzenlemesi başka birinin menüsünü düşürürdü.
 *
 * Requirement ID'leri: URL-RESERVED-12, URL-RESERVED-COVERS-ROUTES-13.
 */
final class ReservedSlugTest extends TestCase
{
    use RefreshDatabase;

    // --- URL-RESERVED-12 ---------------------------------------------------

    public function test_a_brand_cannot_take_a_slug_the_router_already_owns(): void
    {
        $owner = User::factory()->create(['email_verified_at' => now()]);

        $workspaceId = (int) DB::table('workspaces')->insertGetId([
            'name' => 'Zeytin', 'slug' => 'zeytin-'.uniqid(), 'state' => 'active',
            'created_by' => $owner->id, 'created_at' => now(), 'updated_at' => now(),
        ]);

        DB::table('workspace_memberships')->insert([
            'workspace_id' => $workspaceId, 'user_id' => $owner->id, 'role' => 'owner',
            'created_at' => now(), 'updated_at' => now(),
        ]);

        $response = $this->actingAs($owner)->withHeaders(['Accept' => 'application/json'])
            ->postJson("/api/workspaces/{$workspaceId}/brand", [
                'name' => 'Menü',
                'slug' => 'menu',
                'timezone' => 'Europe/Istanbul',
                'currency' => 'TRY',
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors('slug');
    }

    // --- URL-RESERVED-COVERS-ROUTES-13 -------------------------------------

    public function test_every_top_level_route_prefix_is_actually_reserved(): void
    {
        // Liste elle tutulur; bu test onun gerçekle uyumunu zorlar. Yeni bir
        // üst düzey yol eklenip listeye yazılmazsa, o yol bir gün bir
        // işletmenin slug'ı olur.
        $policy = $this->app->make(UrlPolicy::class);
        $missing = [];

        foreach ($this->app->make('router')->getRoutes() as $route) {
            $uri = $route->uri();

            if ($uri === '/' || str_starts_with($uri, '{')) {
                continue;
            }

            $first = explode('/', $uri)[0];

            if (str_starts_with($first, '{')) {
                continue;
            }

            if (! $policy->isReservedSlug($first)) {
                $missing[$first] = $first;
            }
        }

        self::assertSame(
            [],
            array_values($missing),
            'URL-RESERVED-COVERS-ROUTES-13: bu üst düzey yollar rezerve listesinde yok; '
            .'bir işletme onları slug olarak alabilir (config/url-policy.php).'
        );
    }
}
