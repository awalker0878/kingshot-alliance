<?php

declare(strict_types=1);

namespace App\Contexts\Accounts\Authentication\Http\Controllers;

use App\Contexts\Accounts\Authentication\Actions\RevokeAccountSession;
use App\Contexts\Accounts\Authentication\Actions\RevokeOtherAccountSessions;
use App\Contexts\Accounts\Identity\Models\User;
use App\Shared\Infrastructure\Http\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class AccountSessionController extends Controller
{
    public function destroy(
        Request $request,
        string $session,
        RevokeAccountSession $revokeAccountSession,
    ): RedirectResponse {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $revokeAccountSession->handle(
            userId: (int) $user->id,
            publicId: $session,
            currentSessionId: $request->session()->getId(),
        );

        return back()->with('status', 'session-revoked');
    }

    public function destroyOthers(
        Request $request,
        RevokeOtherAccountSessions $revokeOtherAccountSessions,
    ): RedirectResponse {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $revokeOtherAccountSessions->handle(
            userId: (int) $user->id,
            currentSessionId: $request->session()->getId(),
        );

        $receipt = $this->receipt('other-sessions-revoked');

        return back()->with('actionReceipt', $receipt);
    }
}
