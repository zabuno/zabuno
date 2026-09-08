<?php

declare(strict_types=1);

namespace Tests\Feature\PublicSite;

use Database\Seeders\PlanCatalogueSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class SceneCiFixtureTest extends TestCase
{
    use RefreshDatabase;

    public function test_ci_seeds_the_catalogue_before_starting_the_visual_server(): void
    {
        $workflow = (string) file_get_contents(base_path('.github/workflows/ci.yml'));
        $seed = strpos($workflow, 'php artisan db:seed --class=PlanCatalogueSeeder');
        $serve = strpos($workflow, 'nohup php artisan serve --port=8291');

        self::assertNotFalse($seed, 'Scene CI must measure published plans, not an empty catalogue.');
        self::assertNotFalse($serve);
        self::assertLessThan($serve, $seed);
    }

    public function test_failed_visual_measurements_keep_images_for_diagnosis(): void
    {
        $workflow = (string) file_get_contents(base_path('.github/workflows/ci.yml'));

        self::assertStringContainsString('--png storage/app/scene-visual', $workflow);
        self::assertMatchesRegularExpression(
            '/- name: Preserve scene visual evidence\R\s+if: always\(\)\R\s+uses: actions\/upload-artifact@v4/',
            $workflow,
        );
        self::assertStringContainsString('path: storage/app/scene-visual', $workflow);
    }

    public function test_the_canonical_fixture_renders_every_plan_without_local_developer_data(): void
    {
        $this->seed(PlanCatalogueSeeder::class);

        $response = $this->withHeader('Accept-Language', 'en-US')->get('/pricing');
        $response->assertOk();

        foreach (PlanCatalogueSeeder::catalogue() as $plan) {
            $response->assertSee($plan['name']);
        }

        self::assertSame(count(PlanCatalogueSeeder::catalogue()), substr_count(
            (string) $response->getContent(),
            'class="site-pricing-plan-name"',
        ));
    }
}
