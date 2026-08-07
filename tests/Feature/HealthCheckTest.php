<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class HealthCheckTest extends TestCase
{
    use RefreshDatabase;

    public function test_liveness_endpoint_is_available_stateless_and_not_cached(): void
    {
        $response = $this->get('/up')->assertOk();

        self::assertStringContainsString(
            'no-store',
            (string) $response->headers->get('Cache-Control'),
        );
        self::assertSame([], $response->headers->getCookies());
        $response->assertHeader('Pragma', 'no-cache');
    }

    public function test_readiness_checks_dependencies_without_exposing_details_sessions_or_caching(): void
    {
        $response = $this->get('/health/ready')
            ->assertOk()
            ->assertJsonPath('status', 'ready');

        $payload = $response->json();
        self::assertIsArray($payload);
        self::assertSame(['status', 'request_id'], array_keys($payload));
        self::assertIsString($payload['request_id']);

        self::assertStringContainsString(
            'no-store',
            (string) $response->headers->get('Cache-Control'),
        );
        self::assertSame([], $response->headers->getCookies());
        $response->assertHeader('Pragma', 'no-cache');
    }
}
