<?php

declare(strict_types=1);

namespace App\Contexts\Accounts\Authentication\Actions;

use App\Contexts\Accounts\Authentication\Models\AccountSession;
use App\Contexts\Accounts\Identity\Models\User;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use Illuminate\Auth\SessionGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use LogicException;
use Throwable;

final readonly class LogoutAccount
{
    public function __construct(private AuditRecorder $audit) {}

    public function handle(Request $request): void
    {
        if (DB::transactionLevel() !== 0) {
            throw new LogicException('Account logout must run outside an enclosing database transaction.');
        }

        $guard = Auth::guard('web');
        if (! $guard instanceof SessionGuard) {
            throw new LogicException('Account logout requires the maintained web session guard.');
        }

        $user = $request->user();
        $sessionId = $request->session()->getId();
        $persistenceFailure = null;

        try {
            if ($user instanceof User) {
                DB::transaction(function () use ($user, $sessionId): void {
                    $current = User::query()->whereKey($user->id)->lockForUpdate()->first();
                    if (! $current instanceof User) {
                        return;
                    }

                    AccountSession::query()
                        ->where('user_id', $current->id)
                        ->where('session_id_hash', hash('sha256', $sessionId))
                        ->whereNull('revoked_at')
                        ->lockForUpdate()
                        ->update(['revoked_at' => now()]);

                    $this->audit->record(
                        event: 'auth.logout',
                        actor: $current,
                        subject: $current,
                    );
                });
            }
        } catch (Throwable $exception) {
            // The current browser must still lose its prepared authentication
            // state when durable logout recording is unavailable.
            $persistenceFailure = $exception;
        }

        try {
            // Full guard logout rotates remember authority. A normal logout is
            // scoped to this browser, so preserve other remembered browsers.
            $guard->logoutCurrentDevice();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
        } catch (Throwable $cleanupFailure) {
            if ($persistenceFailure instanceof Throwable) {
                report($cleanupFailure);
            } else {
                throw $cleanupFailure;
            }
        }

        if ($persistenceFailure instanceof Throwable) {
            throw $persistenceFailure;
        }
    }
}
