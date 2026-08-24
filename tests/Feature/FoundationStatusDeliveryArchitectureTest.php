<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Http\Controllers\FoundationStatusController;
use Illuminate\Support\Facades\Route;
use ReflectionClass;
use Tests\TestCase;

/**
 * Encodes docs/03 ADR-L02 (MVC delivery, thin controller) and ADR-L03
 * (strict OOP: declare(strict_types=1), final classes) for the GET '/'
 * route. The route resolves to App\Http\Controllers\FoundationStatusController
 * (final, public __invoke, strict_types=1) in routes/web.php; this test is a
 * regression contract that keeps it that way — it fails again if the route
 * is ever regressed back to a Closure or the controller loses either ADR
 * property.
 */
final class FoundationStatusDeliveryArchitectureTest extends TestCase
{
    private const CONTROLLER_PATH = __DIR__.'/../../app/Http/Controllers/FoundationStatusController.php';

    /**
     * Fixed, non-secret test-local key (32 raw bytes of 0x2A, base64-encoded).
     * phpunit.xml carries no APP_KEY, so the real config/.env stays untouched;
     * this only satisfies the encrypter used by the 'web' middleware group
     * (session/cookie encryption) so GET '/' can be exercised end-to-end.
     */
    private const TEST_APP_KEY = 'base64:KioqKioqKioqKioqKioqKioqKioqKioqKioqKioqKio=';

    protected function setUp(): void
    {
        parent::setUp();

        config(['app.key' => self::TEST_APP_KEY]);
    }

    public function test_root_route_action_is_not_a_closure_and_resolves_to_foundation_status_controller(): void
    {
        $route = collect(Route::getRoutes())->first(
            fn ($route) => $route->uri() === '/' && in_array('GET', $route->methods(), true)
        );

        self::assertNotNull($route, "GET '/' route tanımı bulunamadı.");
        self::assertNotSame(
            'Closure',
            $route->getActionName(),
            "GET '/' route action hâlâ bir Closure (ADR-L02: Delivery=MVC, ince controller)."
        );
        self::assertSame(
            FoundationStatusController::class,
            $route->getControllerClass(),
            "GET '/' route action App\\Http\\Controllers\\FoundationStatusController'a çözülmüyor."
        );
        self::assertSame(
            '__invoke',
            $route->getActionMethod(),
            "GET '/' route action FoundationStatusController::__invoke'a çözülmüyor."
        );
    }

    public function test_foundation_status_controller_is_final_with_public_invoke_method(): void
    {
        self::assertTrue(
            class_exists(FoundationStatusController::class),
            'App\\Http\\Controllers\\FoundationStatusController sınıfı mevcut değil.'
        );

        $reflection = new ReflectionClass(FoundationStatusController::class);

        self::assertTrue(
            $reflection->isFinal(),
            'FoundationStatusController final olmalı (ADR-L03: sınıflar varsayılan olarak final).'
        );
        self::assertTrue(
            $reflection->hasMethod('__invoke'),
            'FoundationStatusController bir __invoke metodu taşımalı.'
        );
        self::assertTrue(
            $reflection->getMethod('__invoke')->isPublic(),
            'FoundationStatusController::__invoke public olmalı.'
        );
    }

    public function test_foundation_status_controller_file_declares_strict_types(): void
    {
        self::assertFileExists(
            self::CONTROLLER_PATH,
            'app/Http/Controllers/FoundationStatusController.php dosyası mevcut değil.'
        );

        $contents = (string) file_get_contents(self::CONTROLLER_PATH);

        self::assertMatchesRegularExpression(
            '/^\s*declare\s*\(\s*strict_types\s*=\s*1\s*\)\s*;/m',
            $contents,
            'FoundationStatusController.php declare(strict_types=1) taşımıyor (ADR-L03).'
        );
    }

    public function test_root_route_responds_ok_with_expected_blade_mount_and_title(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('id="app"', false);
        $response->assertSee('Zabuno');
    }

    /**
     * Encodes docs/03 ADR-L04/L05 (finite, registry-driven Core Kernel):
     * the coreModuleCount the shell renders must be *derived* from the
     * CORE-05 module registry (config('core-modules')), never a hardcoded
     * literal duplicated in the delivery layer. count(config('core-modules'))
     * is 16 today (CORE-01..16, docs/04 §5) but this test pins the contract
     * to the registry's own count, not to the literal 16, so it stays true
     * if the registry ever changes.
     */
    public function test_root_route_view_data_and_blade_mount_carry_registry_derived_core_module_count(): void
    {
        $expectedCount = count(config('core-modules'));

        $response = $this->get('/');

        $response->assertOk();
        $response->assertViewHas(
            'coreModuleCount',
            $expectedCount,
        );
        $response->assertSee(
            sprintf('data-core-module-count="%d"', $expectedCount),
            false,
        );
    }
}
