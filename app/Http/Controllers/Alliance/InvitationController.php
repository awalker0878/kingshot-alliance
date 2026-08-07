<?php

declare(strict_types=1);

namespace App\Http\Controllers\Alliance;

use App\Application\Identity\AllianceContext;
use App\Application\Identity\CreateInvitation;
use App\Application\Identity\ResendInvitation;
use App\Application\Identity\RevokeInvitation;
use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class InvitationController extends Controller
{
    public function store(
        Request $request,
        AllianceContext $context,
        CreateInvitation $createInvitation,
    ): RedirectResponse {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $validated = $request->validate([
            'email' => ['required', 'string', 'email', 'max:254'],
        ]);

        $issued = $createInvitation->handle($context->alliance(), $user, $validated['email']);

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
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $issued = $resendInvitation->handle($context->alliance(), $user, $invitation);

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
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $revokeInvitation->handle($context->alliance(), $user, $invitation);

        return redirect()->route('alliance.overview');
    }
}
