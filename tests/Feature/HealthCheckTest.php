<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class HealthCheckTest extends TestCase
{
    use RefreshDatabase;

    public function test_liveness_endpoint_is_available(): void
    {
        $this->get('/up')->assertOk();
    }

    public function test_readiness_checks_database_and_cache(): void
    {
        $this->get('/health/ready')
            ->assertOk()
            ->assertJsonPath('status', 'ready')
            ->assertJsonPath('checks.database', true)
            ->assertJsonPath('checks.cache', true);
    }
}
