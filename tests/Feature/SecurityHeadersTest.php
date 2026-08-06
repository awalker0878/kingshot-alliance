<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Str;
use Tests\TestCase;

final class SecurityHeadersTest extends TestCase
{
    public function test_responses_include_correlation_and_security_headers(): void
    {
        $response = $this->get('/');

        $response->assertOk()
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin')
            ->assertHeaderMissing('Strict-Transport-Security');

        self::assertNotEmpty($response->headers->get('X-Request-ID'));
        self::assertMatchesRegularExpression(
            '/^00-(?!0{32})[a-f0-9]{32}-(?!0{16})[a-f0-9]{16}-[a-f0-9]{2}$/',
            (string) $response->headers->get('traceparent')
        );
    }

    public function test_production_responses_include_transport_security(): void
    {
        $originalEnvironment = $this->app->environment();
        $this->app['env'] = 'production';

        try {
            $this->get('/')
                ->assertOk()
                ->assertHeader(
                    'Strict-Transport-Security',
                    'max-age=31536000; includeSubDomains'
                );
        } finally {
            $this->app['env'] = $originalEnvironment;
        }
    }

    public function test_valid_request_id_and_trace_context_are_propagated(): void
    {
        $requestId = (string) Str::uuid();
        $traceparent = '00-4bf92f3577b34da6a3ce929d0e0e4736-00f067aa0ba902b7-a3';

        $this->withHeaders([
            'X-Request-ID' => $requestId,
            'traceparent' => $traceparent,
        ])->get('/')
            ->assertHeader('X-Request-ID', $requestId)
            ->assertHeader('traceparent', $traceparent);
    }

    public function test_invalid_correlation_headers_are_replaced(): void
    {
        $invalidTraceparent = '00-00000000000000000000000000000000-0000000000000000-01';

        $response = $this->withHeaders([
            'X-Request-ID' => 'not-a-uuid',
            'traceparent' => $invalidTraceparent,
        ])->get('/');

        self::assertNotSame('not-a-uuid', $response->headers->get('X-Request-ID'));
        self::assertNotSame($invalidTraceparent, $response->headers->get('traceparent'));
        self::assertMatchesRegularExpression(
            '/^00-(?!0{32})[a-f0-9]{32}-(?!0{16})[a-f0-9]{16}-[a-f0-9]{2}$/',
            (string) $response->headers->get('traceparent')
        );
    }
}
