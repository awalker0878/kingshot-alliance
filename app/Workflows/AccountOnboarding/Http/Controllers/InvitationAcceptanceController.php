<?php

declare(strict_types=1);

namespace App\Workflows\AccountOnboarding\Http\Controllers;

use App\Contexts\Accounts\Identity\Queries\AccountIdentityQuery;
use App\Contexts\Alliance\Membership\Queries\FindPendingInvitation;
use App\Contexts\GameWorld\Players\Queries\PlayerReferenceQuery;
use App\Shared\Infrastructure\Http\Controller;
use App\Workflows\AccountOnboarding\Actions\AcceptInvitationForAccount;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Inertia\Inertia;
use Inertia\Response;

final class InvitationAcceptanceController extends Controller
{
    public function show(
        FindPendingInvitation $invitations,
        PlayerReferenceQuery $players,
        AccountIdentityQuery $accounts,
        string $token,
    ): Response {
        $invitation = $invitations->byToken($token);
        abort_if($invitation === null, 404);

        $player = $players->find($invitation->playerId);
        $authId = Auth::id();
        $account = is_numeric($authId) ? $accounts->find((int) $authId) : null;

        return Inertia::render('Auth/Invitation', [
            'invitation' => [
                'token' => $token,
                'email' => $invitation->email,
                'expiresAt' => $invitation->expiresAt,
                'alliance' => [
                    'id' => $invitation->allianceId,
                    'name' => $invitation->allianceName,
                ],
                'player' => [
                    'id' => $invitation->playerId,
                    'name' => $player?->currentName,
                ],
            ],
            'authenticated' => $account !== null,
            'authenticatedEmail' => $account?->email,
        ]);
    }

    public function accept(
        Request $request,
        AcceptInvitationForAccount $acceptInvitation,
        string $token,
    ): RedirectResponse {
        $authId = Auth::id();
        abort_unless(is_numeric($authId), 401);

        $result = $acceptInvitation->handle((int) $authId, $token);

        $request->session()->put(
            (string) config('game_world.active_player_session_key'),
            $result->playerId,
        );

        return redirect()->route('alliance.overview');
    }
}
