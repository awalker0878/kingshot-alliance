<?php

declare(strict_types=1);

namespace App\Domain\Platform\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class RecordRequestMetrics
{
    public function handle(Request $request, Closure $next): Response
    {
        $startedAt = hrtime(true);

        try {
            $response = $next($request);
        } catch (Throwable $exception) {
            Log::error('http.request.failed', [
                ...$this->context($request, $startedAt),
                'exception' => $exception::class,
            ]);

            throw $exception;
        }

        Log::info('http.request.completed', [
            ...$this->context($request, $startedAt),
            'status' => $response->getStatusCode(),
        ]);

        return $response;
    }

    /**
     * @return array<string, int|float|string>
     */
    private function context(Request $request, int $startedAt): array
    {
        $route = $request->route();
        $routeName = $route instanceof Route ? $route->getName() : null;

        return [
            'method' => $request->method(),
            'route' => is_string($routeName) && $routeName !== '' ? $routeName : 'unmatched',
            'duration_ms' => round((hrtime(true) - $startedAt) / 1_000_000, 2),
        ];
    }
}
