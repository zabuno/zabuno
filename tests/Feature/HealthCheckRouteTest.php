<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Exercises bootstrap/app.php's ->withRouting(health: '/up') wiring (frozen
 * S1-WP01A scope: "/up health") through the real Laravel container.
 */
final class HealthCheckRouteTest extends TestCase
{
    public function test_up_endpoint_reports_application_is_alive(): void
    {
        $response = $this->get('/up');

        $response->assertOk();
    }

    public function test_up_endpoint_does_not_require_authentication(): void
    {
        $response = $this->withHeaders(['Accept' => 'application/json'])->get('/up');

        $response->assertOk();
        $response->assertHeaderMissing('Location');
    }
}
