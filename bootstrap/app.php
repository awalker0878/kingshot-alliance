<?php

declare(strict_types=1);

use App\Http\Middleware\AssignRequestContext;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\RecordRequestMetrics;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
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
        $exceptions->context(static fn (): array => [
            'request_id' => request()?->attributes->get('request_id'),
            'trace_id' => request()?->attributes->get('trace_id'),
        ]);

        $exceptions->respond(static function (Response $response): Response {
            $request = request();

            if ($request instanceof Request) {
                AssignRequestContext::applyResponseHeaders($response, $request);
            }

            return SecurityHeaders::apply($response);
        });
    })
    ->create();
