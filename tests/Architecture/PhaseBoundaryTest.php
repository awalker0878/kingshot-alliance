<?php

declare(strict_types=1);

namespace Tests\Architecture;

use Tests\TestCase;

final class PhaseBoundaryTest extends TestCase
{
    public function test_phase_one_authentication_route_is_not_exposed(): void
    {
        $this->get('/sanctum/csrf-cookie')->assertNotFound();
    }

    public function test_pulse_dashboard_routes_are_not_registered(): void
    {
        $this->get('/pulse')->assertNotFound();
    }

    public function test_horizon_dashboard_and_api_are_denied_until_authorization_exists(): void
    {
        $this->get('/horizon')->assertForbidden();
        $this->get('/horizon/api/stats')->assertForbidden();
    }
}
