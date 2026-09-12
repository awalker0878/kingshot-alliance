<?php

declare(strict_types=1);

namespace App\Contexts\Accounts\MultiFactorAuthentication\Http\Controllers;

use App\Contexts\Accounts\MultiFactorAuthentication\Actions\CompleteMfaLogin;
use App\Contexts\Accounts\MultiFactorAuthentication\Services\MfaLoginChallenge;
use App\Shared\Infrastructure\Http\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

final class TwoFactorChallengeController extends Controller
{
    public function create(Request $request, MfaLoginChallenge $challenges): Response|RedirectResponse
    {
        if ($challenges->pending($request) === null) {
            return redirect()->route('login');
        }

        return Inertia::render('Accounts/Access/TwoFactorChallenge');
    }

    public function store(
        Request $request,
        CompleteMfaLogin $complete,
    ): RedirectResponse {
        $validated = $request->validate(['code' => ['nullable', 'string', 'max:32'], 'recovery_code' => ['nullable', 'string', 'max:64']]);
        $code = trim((string) ($validated['code'] ?? ''));
        $recoveryCode = trim((string) ($validated['recovery_code'] ?? ''));
        $invitationToken = $complete->handle($request, $code, $recoveryCode);
        if ($invitationToken !== null && $invitationToken !== '') {
            return redirect()->route('invitations.show', ['token' => $invitationToken]);
        }

        return redirect()->intended(route('dashboard'));
    }
}
