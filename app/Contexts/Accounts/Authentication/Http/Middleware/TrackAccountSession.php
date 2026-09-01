<?php

declare(strict_types=1);

namespace App\Contexts\Accounts\Authentication\Http\Middleware;

use App\Contexts\Accounts\Authentication\Actions\RecordAccountSession;
use App\Contexts\Accounts\Identity\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final readonly class TrackAccountSession
{
    public function __construct(private RecordAccountSession $recordAccountSession) {}

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        $sessionId = $request->hasSession() ? $request->session()->getId() : '';

        if ($user instanceof User && $sessionId !== '') {
            $this->recordAccountSession->handle(
                userId: (int) $user->id,
                sessionId: $sessionId,
                userAgent: (string) $request->userAgent(),
            );
        }

        return $next($request);
    }
}
