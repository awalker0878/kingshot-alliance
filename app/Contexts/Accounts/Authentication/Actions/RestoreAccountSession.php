<?php

declare(strict_types=1);

namespace App\Contexts\Accounts\Authentication\Actions;

use App\Contexts\Accounts\Authentication\Services\AccountLoginProofs;
use App\Contexts\Accounts\Authentication\Services\RecentAuthentication;
use App\Contexts\Accounts\Identity\Models\User;
use App\Contexts\Accounts\MultiFactorAuthentication\Services\MfaLoginChallenge;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Auth\Events\Authenticated;
use Illuminate\Auth\Events\Login;
use Illuminate\Auth\SessionGuard;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use LogicException;
use Throwable;

final readonly class RestoreAccountSession
{
    public function __construct(
        private RecordAccountSession $sessions,
        private AccountLoginProofs $proofs,
        private AuditRecorder $audit,
        private RecentAuthentication $recentAuthentication,
        private MfaLoginChallenge $challenges,
    ) {}

    public function handle(Request $request): ?User
    {
        if (! $request->hasSession()) {
            return null;
        }
        $guard = Auth::guard('web');
        if (! $guard instanceof SessionGuard) {
            throw new LogicException('Account session restoration requires the maintained web session guard.');
        }
        $alreadyResolved = $guard->hasUser();
        if (! $alreadyResolved && $request->cookies->has($guard->getRecallerName()) && DB::transactionLevel() !== 0) {
            throw new LogicException('Remembered account restoration must run outside an enclosing database transaction.');
        }

        $restored = null;
        $committed = false;
        try {
            Event::defer(function () use ($request, $guard, $alreadyResolved, &$restored, &$committed): void {
                // The maintained guard validates the recaller and rotates raw
                // storage before the account completion transaction begins.
                $user = $guard->user();
                if (! $user instanceof User) {
                    $committed = true;

                    return;
                }
                if (! $alreadyResolved && $guard->viaRemember()) {
                    DB::transaction(function () use ($request, $user): void {
                        $current = User::query()->whereKey($user->id)->lockForUpdate()->first();
                        if ($current === null || ! $this->proofs->matchesRemembered($current, $user)) {
                            throw new AuthenticationException('Unauthenticated.', ['web']);
                        }
                        $this->record($request, $current);
                        $this->audit->record(event: 'auth.login', actor: $current, subject: $current,
                            metadata: ['provider' => 'remember', 'mfa_method' => null]);
                    });
                    // A remembered browser is not a fresh credential ceremony.
                    $this->recentAuthentication->clear($request);
                    $this->challenges->clear($request);
                } else {
                    $this->record($request, $user);
                }
                $restored = $user;
                $committed = true;
            }, [Login::class, Authenticated::class]);
        } catch (Throwable $exception) {
            if ($committed) {
                report($exception);

                return $restored;
            }
            // Do not reload a prepared guard ID while abandoning failed raw
            // rotation or completion, and do not retry failing raw destruction.
            $request->session()->forget($guard->getName());
            try {
                $guard->logoutCurrentDevice();
            } catch (Throwable $cleanupFailure) {
                report($cleanupFailure);
            } finally {
                $guard->forgetUser();
                $request->session()->flush();
                $request->session()->regenerate();
            }
            throw $exception;
        }

        return $restored;
    }

    private function record(Request $request, User $user): void
    {
        if (! $this->sessions->handle((int) $user->id, $request->session()->getId(), (string) $request->userAgent())) {
            throw new AuthenticationException('Unauthenticated.', ['web']);
        }
    }
}
