<?php

declare(strict_types=1);

use App\Domain\Platform\Http\Controllers\ReadinessController;
use App\Domain\Platform\Http\Middleware\AssignRequestContext;
use App\Domain\Platform\Http\Middleware\HandleInertiaRequests;
use App\Domain\Platform\Http\Middleware\RecordRequestMetrics;
use App\Domain\Alliances\Http\Middleware\ResolveAllianceContext;
use App\Domain\Platform\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: static function (): void {
            Route::get('/health/ready', ReadinessController::class)
                ->name('health.ready');
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $trustedProxies = array_values(array_filter(array_map(
            static fn (string $proxy): string => trim($proxy),
            explode(',', (string) env('TRUSTED_PROXIES', '')),
        )));

        if ($trustedProxies !== []) {
            $middleware->trustProxies(
                at: $trustedProxies === ['*'] ? '*' : $trustedProxies,
            );
        }

        $middleware->alias([
            'alliance.context' => ResolveAllianceContext::class,
        ]);

        $middleware->append([
            AssignRequestContext::class,
            RecordRequestMetrics::class,
            SecurityHeaders::class,
        ]);

        $middleware->web(append: [
            HandleInertiaRequests::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->context(static function (): array {
            $request = app()->bound('request') ? request() : null;

            return [
                'request_id' => $request instanceof Request
                    ? $request->attributes->get('request_id')
                    : null,
                'trace_id' => $request instanceof Request
                    ? $request->attributes->get('trace_id')
                    : null,
            ];
        });

        $exceptions->respond(static function (Response $response): Response {
            $request = app()->bound('request') ? request() : null;

            if ($request instanceof Request) {
                AssignRequestContext::applyResponseHeaders($response, $request);
            }

            return SecurityHeaders::apply(
                $response,
                $request instanceof Request ? $request : null,
            );
        });
    })
    ->create();
