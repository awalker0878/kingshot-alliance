<?php

declare(strict_types=1);

use App\Contexts\Accounts\Authentication\Http\Middleware\RequireRecentAccountAuthentication;
use App\Contexts\Accounts\Authentication\Http\Middleware\TrackAccountSession;
use App\Contexts\Alliance\Lifecycle\Http\Middleware\ResolveAllianceContext;
use App\Contexts\GameWorld\GiftCodes\Http\Middleware\RequireGiftCodeCurator;
use App\Contexts\GameWorld\Players\Http\Middleware\HandleInertiaRequests;
use App\Contexts\GameWorld\Players\Http\Middleware\RequireCurrentPlayerContextVersion;
use App\Contexts\GameWorld\Players\Http\Middleware\ResolvePlayerContext;
use App\Contexts\Platform\Administration\Http\Middleware\RequirePlatformAdministrator;
use App\Contexts\Platform\Integrations\Http\Middleware\AuthenticateApiCredential;
use App\Shared\Infrastructure\Observability\Http\Middleware\AssignRequestContext;
use App\Shared\Infrastructure\Observability\Http\Middleware\RecordRequestMetrics;
use App\Shared\Infrastructure\Runtime\Http\Controllers\ReadinessController;
use App\Shared\Infrastructure\Security\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Symfony\Component\HttpFoundation\Response;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: static function (): void {
            Route::get('/health/ready', ReadinessController::class)->name('health.ready');
            Route::middleware('web')->group(base_path('routes/account.php'));
            Route::middleware('web')->group(base_path('routes/alliance-content-parity.php'));
            Route::middleware('web')->group(base_path('routes/assistant.php'));
            Route::middleware('web')->group(base_path('routes/contributions.php'));
            Route::middleware('web')->group(base_path('routes/event-history.php'));
            Route::middleware('web')->group(base_path('routes/gift-codes.php'));
            Route::middleware('web')->group(base_path('routes/governance.php'));
            Route::middleware('web')->group(base_path('routes/integrations.php'));
            Route::middleware('web')->group(base_path('routes/king-perks.php'));
            Route::middleware('web')->group(base_path('routes/kingdoms.php'));
            Route::middleware('web')->group(base_path('routes/notifications.php'));
            Route::middleware('web')->group(base_path('routes/platform.php'));
            Route::middleware('web')->group(base_path('routes/progression.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'alliance.context' => ResolveAllianceContext::class,
            'platform.admin' => RequirePlatformAdministrator::class,
            'gift-code.curator' => RequireGiftCodeCurator::class,
            'api.credential' => AuthenticateApiCredential::class,
            'password.confirm' => RequireRecentAccountAuthentication::class,
        ]);
        $middleware->append([AssignRequestContext::class, RecordRequestMetrics::class, SecurityHeaders::class]);
        $middleware->appendToPriorityList(StartSession::class, TrackAccountSession::class);
        $middleware->web(append: [TrackAccountSession::class, ResolvePlayerContext::class, RequireCurrentPlayerContextVersion::class, HandleInertiaRequests::class]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->context(static function (): array {
            $request = app()->bound('request') ? request() : null;

            return ['request_id' => $request instanceof Request ? $request->attributes->get('request_id') : null, 'trace_id' => $request instanceof Request ? $request->attributes->get('trace_id') : null];
        });
        $exceptions->respond(static function (Response $response): Response {
            $request = app()->bound('request') ? request() : null;
            if ($request instanceof Request) {
                AssignRequestContext::applyResponseHeaders($response, $request);
            }

            return SecurityHeaders::apply($response, $request instanceof Request ? $request : null);
        });
    })
    ->create();
