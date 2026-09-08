<?php

declare(strict_types=1);

namespace App\Contexts\Accounts\Authentication\Actions;

use App\Contexts\Accounts\Authentication\Models\AccountSession;
use App\Contexts\Accounts\Identity\Models\User;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use Illuminate\Auth\Events\CurrentDeviceLogout;
use Illuminate\Auth\SessionGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Str;
use LogicException;
use RuntimeException;
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
                        ->update(['revoked_at' => now()]);

                    // The maintained cookie is account-scoped. Rotating its
                    // authority is necessary to reject copies after sign-out.
                    if ($current->isActive() && $current->getRememberToken() !== '') {
                        $current->forceFill(['remember_token' => Str::random(60)])->save();
                    }

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
            Event::defer(function () use ($guard, $request, $sessionId, $persistenceFailure): void {
                try {
                    // Remember authority was already handled under the account
                    // lock. Do not invoke the guard's unlocked token writer.
                    $guard->logoutCurrentDevice();
                } finally {
                    $guard->forgetUser();
                    $request->session()->flush();
                    try {
                        if (! $request->session()->getHandler()->destroy($sessionId)) {
                            throw new RuntimeException('Signed-out session storage cleanup failed.');
                        }
                    } catch (Throwable $cleanupFailure) {
                        // The committed session marker denies replay even if
                        // external storage is unavailable or a stale writer wins.
                        report($cleanupFailure);
                    }
                    $request->session()->regenerate();
                }

                if ($persistenceFailure instanceof Throwable) {
                    // Discard buffered success events after a rolled-back audit.
                    throw $persistenceFailure;
                }
            }, [CurrentDeviceLogout::class]);
        } catch (Throwable $exception) {
            if ($persistenceFailure instanceof Throwable) {
                throw $persistenceFailure;
            }
            // A listener cannot undo the committed logout or prevent cleanup.
            report($exception);
        }
    }
}
