<?php

declare(strict_types=1);

namespace App\Contexts\Accounts\Authentication\Http\Middleware;

use App\Contexts\Accounts\Authentication\Actions\RecordAccountSession;
use App\Contexts\Accounts\Identity\Models\User;
use Closure;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

final readonly class TrackAccountSession
{
    public function __construct(private RecordAccountSession $recordAccountSession) {}

    public function handle(Request $request, Closure $next): Response
    {
        $initialUserId = $request->user()?->getAuthIdentifier();
        $initialSessionId = $request->hasSession() ? $request->session()->getId() : '';
        $this->recordActiveSession($request);

        $response = $next($request);

        if ($request->hasSession() && ($request->session()->getId() !== $initialSessionId
            || $request->user()?->getAuthIdentifier() !== $initialUserId)) {
            $this->recordActiveSession($request);
        }

        return $response;
    }

    private function recordActiveSession(Request $request): void
    {
        $user = $request->user();
        $sessionId = $request->hasSession() ? $request->session()->getId() : '';

        if ($user instanceof User && $sessionId !== '') {
            $active = $user->anonymized_at === null && $this->recordAccountSession->handle(
                userId: (int) $user->id,
                sessionId: $sessionId,
                userAgent: (string) $request->userAgent(),
            );

            if (! $active) {
                Auth::logoutCurrentDevice();
                $request->session()->invalidate();
                $request->session()->regenerateToken();

                throw new AuthenticationException('Unauthenticated.', ['web']);
            }
        }

    }
}
