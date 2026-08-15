<?php

declare(strict_types=1);

namespace App\Domain\Memberships\Http\Controllers;

use App\Domain\Alliances\Services\AllianceContext;
use App\Contexts\GameWorld\Models\Player;
use App\Domain\Memberships\Actions\CreateInvitation;
use App\Domain\Memberships\Actions\ResendInvitation;
use App\Domain\Memberships\Actions\RevokeInvitation;
use App\Shared\Http\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class InvitationController extends Controller
{
    public function store(
        Request $request,
        AllianceContext $context,
        CreateInvitation $createInvitation,
    ): RedirectResponse {
        $validated = $request->validate([
            'player_id' => ['required', 'string', 'ulid'],
            'email' => ['required', 'string', 'email', 'max:254'],
        ]);

        $target = Player::query()->findOrFail($validated['player_id']);
        $issued = $createInvitation->handle(
            $context->alliance(),
            $context->player(),
            $target,
            $validated['email'],
        );

        return redirect()->route('alliance.overview')->with(
            'invitationLink',
            route('invitations.show', ['token' => $issued->token]),
        );
    }

    public function resend(
        Request $request,
        AllianceContext $context,
        ResendInvitation $resendInvitation,
        string $invitation,
    ): RedirectResponse {
        $issued = $resendInvitation->handle($context->alliance(), $context->player(), $invitation);

        return redirect()->route('alliance.overview')->with(
            'invitationLink',
            route('invitations.show', ['token' => $issued->token]),
        );
    }

    public function destroy(
        Request $request,
        AllianceContext $context,
        RevokeInvitation $revokeInvitation,
        string $invitation,
    ): RedirectResponse {
        $revokeInvitation->handle($context->alliance(), $context->player(), $invitation);

        return redirect()->route('alliance.overview');
    }
}
