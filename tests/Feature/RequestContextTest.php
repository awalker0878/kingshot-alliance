<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;
use Tests\TestCase;

final class RequestContextTest extends TestCase
{
    public function test_valid_request_and_trace_context_are_preserved(): void
    {
        $requestId = (string) Str::uuid();
        $traceparent = '00-4bf92f3577b34da6a3ce929d0e0e4736-00f067aa0ba902b7-a3';

        $this->withHeaders([
            'X-Request-ID' => $requestId,
            'traceparent' => $traceparent,
        ])->get('/up')
            ->assertOk()
            ->assertHeader('X-Request-ID', $requestId)
            ->assertHeader('traceparent', $traceparent);
    }

    public function test_invalid_request_and_trace_context_are_replaced(): void
    {
        $invalidTraceparent = '00-00000000000000000000000000000000-0000000000000000-01';

        $response = $this->withHeaders([
            'X-Request-ID' => 'not-a-uuid',
            'traceparent' => $invalidTraceparent,
        ])->get('/up')->assertOk();

        $requestId = (string) $response->headers->get('X-Request-ID');
        $traceparent = (string) $response->headers->get('traceparent');

        self::assertTrue(Str::isUuid($requestId));
        self::assertNotSame($invalidTraceparent, $traceparent);
        self::assertMatchesRegularExpression(
            '/^00-(?!0{32})[a-f0-9]{32}-(?!0{16})[a-f0-9]{16}-[a-f0-9]{2}$/',
            $traceparent,
        );
    }

    public function test_rendered_error_responses_keep_correlation_and_security_headers(): void
    {
        Route::get('/_test/failure', static function (): never {
            abort(500);
        });

        $requestId = (string) Str::uuid();
        $traceparent = '00-4bf92f3577b34da6a3ce929d0e0e4736-00f067aa0ba902b7-01';

        $this->withHeaders([
            'X-Request-ID' => $requestId,
            'traceparent' => $traceparent,
        ])->get('/_test/failure')
            ->assertStatus(500)
            ->assertHeader('X-Request-ID', $requestId)
            ->assertHeader('traceparent', $traceparent)
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('X-Frame-Options', 'DENY');
    }
}
