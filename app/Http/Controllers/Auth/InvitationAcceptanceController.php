<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Application\Identity\AcceptInvitation;
use App\Application\Identity\FindPendingInvitation;
use App\Http\Controllers\Controller;
use App\Models\Alliance;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use LogicException;

final class InvitationAcceptanceController extends Controller
{
    public function show(
        Request $request,
        FindPendingInvitation $invitations,
        string $token,
    ): Response {
        $invitation = $invitations->byToken($token);
        abort_if($invitation === null, 404);

        $alliance = $invitation->alliance;

        if (! $alliance instanceof Alliance) {
            throw new LogicException('An invitation must reference an alliance.');
        }

        $user = $request->user();

        return Inertia::render('Auth/Invitation', [
            'invitation' => [
                'token' => $token,
                'email' => $invitation->email,
                'expiresAt' => $invitation->expires_at?->toIso8601String(),
                'alliance' => [
                    'id' => $alliance->id,
                    'name' => $alliance->name,
                ],
            ],
            'authenticated' => $user instanceof User,
            'authenticatedEmail' => $user instanceof User ? $user->email : null,
        ]);
    }

    public function accept(
        Request $request,
        AcceptInvitation $acceptInvitation,
        string $token,
    ): RedirectResponse {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $alliance = $acceptInvitation->handle($user, $token);

        $request->session()->put(
            (string) config('identity.active_alliance_session_key'),
            $alliance->id,
        );

        return redirect()->route('alliance.overview');
    }
}
