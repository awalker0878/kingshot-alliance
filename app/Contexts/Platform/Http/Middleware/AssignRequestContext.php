<?php

declare(strict_types=1);

namespace App\Contexts\Platform\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

final class AssignRequestContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $requestId = $this->resolveRequestId($request);
        $traceparent = $this->resolveTraceparent($request);
        $traceId = substr($traceparent, 3, 32);

        $request->attributes->set('request_id', $requestId);
        $request->attributes->set('trace_id', $traceId);
        $request->attributes->set('traceparent', $traceparent);

        Log::withContext([
            'request_id' => $requestId,
            'trace_id' => $traceId,
        ]);

        return self::applyResponseHeaders($next($request), $request);
    }

    public static function applyResponseHeaders(Response $response, Request $request): Response
    {
        $requestId = $request->attributes->get('request_id');
        $traceparent = $request->attributes->get('traceparent');

        if (is_string($requestId) && $requestId !== '') {
            $response->headers->set('X-Request-ID', $requestId);
        }

        if (is_string($traceparent) && $traceparent !== '') {
            $response->headers->set('traceparent', $traceparent);
        }

        return $response;
    }

    private function resolveRequestId(Request $request): string
    {
        $candidate = $request->headers->get('X-Request-ID');

        return is_string($candidate) && Str::isUuid($candidate)
            ? $candidate
            : (string) Str::uuid();
    }

    private function resolveTraceparent(Request $request): string
    {
        $candidate = $request->headers->get('traceparent');

        if (is_string($candidate) && $this->isValidTraceparent($candidate)) {
            return sprintf(
                '00-%s-%s-%s',
                substr($candidate, 3, 32),
                bin2hex(random_bytes(8)),
                substr($candidate, -2),
            );
        }

        return sprintf('00-%s-%s-01', bin2hex(random_bytes(16)), bin2hex(random_bytes(8)));
    }

    private function isValidTraceparent(string $candidate): bool
    {
        if (preg_match(
            '/^00-([a-f0-9]{32})-([a-f0-9]{16})-([a-f0-9]{2})$/',
            $candidate,
            $matches,
        ) !== 1) {
            return false;
        }

        return $matches[1] !== str_repeat('0', 32)
            && $matches[2] !== str_repeat('0', 16);
    }
}
