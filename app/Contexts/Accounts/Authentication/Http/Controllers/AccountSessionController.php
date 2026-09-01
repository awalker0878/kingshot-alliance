<?php

declare(strict_types=1);

namespace App\Contexts\Accounts\Authentication\Http\Controllers;

use App\Contexts\Accounts\Authentication\Models\AccountSession;
use App\Contexts\Accounts\Identity\Models\User;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Http\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class AccountSessionController extends Controller
{
    public function destroy(Request $request, string $session, AuditRecorder $audit): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $record = AccountSession::query()
            ->where('user_id', $user->id)
            ->where('public_id', $session)
            ->whereNull('revoked_at')
            ->firstOrFail();

        $currentHash = hash('sha256', $request->session()->getId());
        abort_if(hash_equals($record->session_id_hash, $currentHash), 422, 'The current session cannot be revoked from this action.');

        $request->session()->getHandler()->destroy($record->session_id);
        $record->forceFill(['revoked_at' => now()])->save();

        $audit->record(
            event: 'auth.session.revoked',
            actor: $user,
            subject: $user,
            metadata: ['session_public_id' => $record->public_id],
        );

        return back()->with('status', 'session-revoked');
    }

    public function destroyOthers(Request $request, AuditRecorder $audit): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $currentHash = hash('sha256', $request->session()->getId());
        $records = AccountSession::query()
            ->where('user_id', $user->id)
            ->whereNull('revoked_at')
            ->where('session_id_hash', '!=', $currentHash)
            ->get();

        foreach ($records as $record) {
            $request->session()->getHandler()->destroy($record->session_id);
            $record->forceFill(['revoked_at' => now()])->save();
        }

        $audit->record(
            event: 'auth.sessions.revoked',
            actor: $user,
            subject: $user,
            metadata: ['count' => $records->count()],
        );

        return back()->with('status', 'sessions-revoked');
    }
}
