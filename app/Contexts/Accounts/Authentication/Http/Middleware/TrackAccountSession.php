<?php

declare(strict_types=1);

namespace App\Contexts\Accounts\Authentication\Http\Middleware;

use App\Contexts\Accounts\Authentication\Actions\RestoreAccountSession;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class TrackAccountSession
{
    public function __construct(private RestoreAccountSession $restoreAccountSession) {}

    public function handle(Request $request, Closure $next): Response
    {
        // Priority places this boundary immediately after StartSession, before
        // authentication, throttling and binding middleware can resolve a user.
        $initialUserId = $this->restoreAccountSession->handle($request)?->getAuthIdentifier();
        $initialSessionId = $request->hasSession() ? $request->session()->getId() : '';

        $response = $next($request);

        if ($request->hasSession() && ($request->session()->getId() !== $initialSessionId
            || $request->user()?->getAuthIdentifier() !== $initialUserId)) {
            $this->restoreAccountSession->handle($request);
        }

        return $response;
    }
}
