<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

final class SecurityHeadersTest extends TestCase
{
    public function test_responses_include_correlation_and_security_headers(): void
    {
        $response = $this->get('/');

        $response->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');

        self::assertNotEmpty($response->headers->get('X-Request-ID'));
        self::assertMatchesRegularExpression(
            '/^00-[a-f0-9]{32}-[a-f0-9]{16}-0[01]$/',
            (string) $response->headers->get('traceparent')
        );
    }

    public function test_valid_request_id_and_trace_context_are_propagated(): void
    {
        $requestId = '8d8fb695-4ee7-4f4e-ae44-97cd123b62d1';
        $traceparent = '00-4bf92f3577b34da6a3ce929d0e0e4736-00f067aa0ba902b7-01';

        $this->withHeaders([
            'X-Request-ID' => $requestId,
            'traceparent' => $traceparent,
        ])->get('/')
            ->assertHeader('X-Request-ID', $requestId)
            ->assertHeader('traceparent', $traceparent);
    }

    public function test_invalid_correlation_headers_are_replaced(): void
    {
        $response = $this->withHeaders([
            'X-Request-ID' => 'not-a-uuid',
            'traceparent' => 'invalid',
        ])->get('/');

        self::assertNotSame('not-a-uuid', $response->headers->get('X-Request-ID'));
        self::assertMatchesRegularExpression(
            '/^00-[a-f0-9]{32}-[a-f0-9]{16}-0[01]$/',
            (string) $response->headers->get('traceparent')
        );
    }
}

