<?php

declare(strict_types=1);

namespace App\Contexts\Accounts\Authentication\Providers;

use App\Contexts\Accounts\Authentication\Actions\RevokeOtherAccountSessions;
use App\Contexts\Accounts\Authentication\Http\Responses\AccountPasskeyLoginResponse;
use App\Contexts\Accounts\Authentication\Models\AccountPasskey;
use App\Contexts\Accounts\Authentication\Services\RecentAuthentication;
use App\Contexts\Accounts\Identity\Models\User;
use App\Contexts\Accounts\Security\Services\SecurityNotificationService;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Passkeys\Contracts\PasskeyLoginResponse;
use Laravel\Passkeys\Events\PasskeyDeleted;
use Laravel\Passkeys\Events\PasskeyRegistered;
use Laravel\Passkeys\Events\PasskeyVerified;
use Laravel\Passkeys\Passkeys;

final class AuthenticationServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(PasskeyLoginResponse::class, AccountPasskeyLoginResponse::class);
    }

    public function boot(): void
    {
        Passkeys::useUserModel(User::class);
        Passkeys::usePasskeyModel(AccountPasskey::class);
        Passkeys::authorizeLoginUsing(static fn (Request $request, $user): bool =>
            $user instanceof User && $user->anonymized_at === null
        );

        RateLimiter::for('login', static fn (Request $request): Limit => Limit::perMinute(5)->by(
            Str::lower(trim((string) $request->input('email'))).'|'.(string) $request->ip(),
        ));
        RateLimiter::for('google-auth', static fn (Request $request): Limit => Limit::perMinute(10)->by(
            (string) $request->ip(),
        ));
        RateLimiter::for('passkeys', static fn (Request $request): Limit => Limit::perMinute(10)->by(
            ($request->user()?->getAuthIdentifier() ?? 'guest').'|'.(string) $request->ip(),
        ));

        Event::listen(PasskeyRegistered::class, static function (PasskeyRegistered $event): void {
            if (! $event->user instanceof User) {
                return;
            }

            $user = $event->user;
            app(AuditRecorder::class)->record(
                event: 'account.passkey.registered',
                actor: $user,
                subject: $user,
                metadata: ['passkey_public_id' => (string) $event->passkey->public_id],
            );
            app(SecurityNotificationService::class)->publish(
                userId: (int) $user->id,
                event: 'account.passkey.registered',
                title: (string) __('accounts.security.passkey_added.title'),
                body: (string) __('accounts.security.passkey_added.body'),
                idempotencyKey: 'account.passkey.registered:'.$user->id.':'.$event->passkey->public_id,
            );
        });

        Event::listen(PasskeyDeleted::class, static function (PasskeyDeleted $event): void {
            if (! $event->user instanceof User) {
                return;
            }

            $user = $event->user;
            $request = request();
            if ($request->user() instanceof User && (int) $request->user()->id === (int) $user->id) {
                app(RevokeOtherAccountSessions::class)->handle(
                    (int) $user->id,
                    $request->session()->getId(),
                );
                app(RecentAuthentication::class)->clear($request);
            }

            app(AuditRecorder::class)->record(
                event: 'account.passkey.removed',
                actor: $user,
                subject: $user,
                metadata: ['passkey_public_id' => (string) $event->passkey->public_id],
            );
            app(SecurityNotificationService::class)->publish(
                userId: (int) $user->id,
                event: 'account.passkey.removed',
                title: (string) __('accounts.security.passkey_removed.title'),
                body: (string) __('accounts.security.passkey_removed.body'),
                idempotencyKey: 'account.passkey.removed:'.$user->id.':'.$event->passkey->public_id,
            );
        });

        Event::listen(PasskeyVerified::class, static function (PasskeyVerified $event): void {
            if (! $event->user instanceof User) {
                return;
            }

            $request = request();
            $request->session()->put('accounts.passkey_verified_public_id', (string) $event->passkey->public_id);

            if ($request->user() instanceof User && (int) $request->user()->id === (int) $event->user->id) {
                app(RecentAuthentication::class)->mark(
                    $request,
                    'passkey',
                    (string) $event->passkey->public_id,
                );
            }

            app(AuditRecorder::class)->record(
                event: 'auth.passkey.verified',
                actor: $event->user,
                subject: $event->user,
                metadata: ['passkey_public_id' => (string) $event->passkey->public_id],
            );
        });

        $this->loadRoutesFrom(base_path('routes/auth.php'));
    }
}
