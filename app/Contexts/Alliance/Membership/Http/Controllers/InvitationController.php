<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Membership\Http\Controllers;

use App\Contexts\Alliance\Lifecycle\Services\AllianceContext;
use App\Contexts\Alliance\Membership\Actions\CreateInvitation;
use App\Contexts\Alliance\Membership\Actions\ResendInvitation;
use App\Contexts\Alliance\Membership\Actions\RevokeInvitation;
use App\Contexts\GameWorld\Players\Models\Player;
use App\Shared\Infrastructure\Http\Controller;
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

        $target = Player::query()
            ->whereKey((string) $validated['player_id'])
            ->firstOrFail();
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
