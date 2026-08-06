<?php

declare(strict_types=1);

use App\Http\Middleware\AssignRequestContext;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\RecordRequestMetrics;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
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
    })
    ->create();
