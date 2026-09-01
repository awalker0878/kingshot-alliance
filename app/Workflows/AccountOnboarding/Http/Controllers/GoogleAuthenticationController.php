<?php

declare(strict_types=1);

namespace App\Workflows\AccountOnboarding\Http\Controllers;

use App\Contexts\Accounts\Identity\Actions\CreateAccountIdentity;
use App\Contexts\Accounts\Identity\Actions\RecordAccountIdentityUse;
use App\Contexts\Accounts\Identity\Enums\AuthenticationType;
use App\Contexts\Accounts\Identity\Models\AccountIdentity;
use App\Contexts\Accounts\Identity\Models\User;
use App\Contexts\Alliance\Membership\Queries\FindPendingInvitation;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Http\Controller;
use App\Workflows\AccountOnboarding\Actions\RegisterAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Laravel\Socialite\AbstractUser;
use Laravel\Socialite\Facades\Socialite;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Throwable;

final class GoogleAuthenticationController extends Controller
{
    public function redirect(Request $request): RedirectResponse
    {
        $this->ensureConfigured();

        $invitationToken = trim((string) $request->query('invitation', ''));

        if ($invitationToken === '') {
            $request->session()->forget('accounts.google_invitation_token');
        } else {
            $request->session()->put('accounts.google_invitation_token', $invitationToken);
        }

        return Socialite::driver('google')->redirect();
    }

    public function callback(
        Request $request,
        FindPendingInvitation $invitations,
        RegisterAccount $registerAccount,
        CreateAccountIdentity $createIdentity,
        RecordAccountIdentityUse $recordIdentityUse,
        AuditRecorder $audit,
    ): RedirectResponse {
        $this->ensureConfigured();

        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (Throwable) {
            throw ValidationException::withMessages([
                'google' => 'Google sign-in could not be completed. Please try again.',
            ]);
        }

        $email = Str::lower(trim((string) $googleUser->getEmail()));
        $subject = trim((string) $googleUser->getId());
        $rawUser = $googleUser instanceof AbstractUser ? $googleUser->getRaw() : [];
        $emailVerified = filter_var(
            $rawUser['email_verified'] ?? $rawUser['verified_email'] ?? false,
            FILTER_VALIDATE_BOOL,
        );

        if (
            $subject === ''
            || $email === ''
            || ! filter_var($email, FILTER_VALIDATE_EMAIL)
            || ! $emailVerified
        ) {
            throw ValidationException::withMessages([
                'google' => 'Google must provide a stable identity and verified email address to sign in.',
            ]);
        }

        $invitationToken = trim((string) $request->session()->pull('accounts.google_invitation_token', ''));
        $invitation = $invitationToken === '' ? null : $invitations->byToken($invitationToken);

        $identity = AccountIdentity::query()
            ->where('provider', AuthenticationType::Google->value)
            ->where('provider_subject', $subject)
            ->first();

        if ($identity !== null) {
            $user = $identity->user()->firstOrFail();
            abort_unless($user->supportsGoogleAuthentication() && $user->anonymized_at === null, 403);
            $recordIdentityUse->handle($identity, $email, true);
        } else {
            if (User::query()->where('email', $email)->exists()) {
                $audit->record(
                    event: 'auth.google.identity_failed',
                    metadata: ['reason' => 'email_collision'],
                );

                throw ValidationException::withMessages([
                    'google' => 'That email is already registered. Sign in using the method configured for that Kingshot Alliance account.',
                ]);
            }

            $registrationMode = (string) config('accounts.registration_mode', 'open');

            if ($registrationMode !== 'open' && $invitation === null) {
                abort(403, 'A valid invitation is required to register.');
            }

            if ($invitationToken !== '' && $invitation === null) {
                abort(404);
            }

            if ($invitation !== null && ! hash_equals(Str::lower($invitation->email), $email)) {
                throw ValidationException::withMessages([
                    'google' => 'Use the Google account that received this invitation.',
                ]);
            }

            $name = trim((string) $googleUser->getName());
            if ($name === '') {
                $name = Str::before($email, '@');
            }

            $result = $registerAccount->handle(
                name: Str::limit($name, 100, ''),
                email: $email,
                password: null,
                timezone: (string) config('app.timezone', 'UTC'),
                invitationToken: $invitationToken === '' ? null : $invitationToken,
                emailVerified: true,
                authenticationType: AuthenticationType::Google,
            );

            $user = User::query()->findOrFail($result->userId);
            $createIdentity->handle(
                userId: (int) $user->id,
                provider: AuthenticationType::Google->value,
                providerSubject: $subject,
                providerEmail: $email,
                providerEmailVerified: true,
            );

            Auth::login($user);
            $request->session()->regenerate();
            $request->session()->put('accounts.google_reauthenticated_at', now()->timestamp);

            $audit->record(
                event: 'auth.login',
                actor: $user,
                subject: $user,
                metadata: ['provider' => 'google', 'mfa_method' => null],
            );

            if ($result->joinedAlliance() && $result->playerId !== null) {
                $request->session()->put(
                    (string) config('game_world.active_player_session_key'),
                    $result->playerId,
                );

                return redirect()->route('alliance.overview');
            }

            return redirect()->route('dashboard');
        }

        if ($user->two_factor_confirmed_at !== null && (string) $user->two_factor_secret !== '') {
            $request->session()->put([
                'accounts.two_factor_challenge_user_id' => $user->id,
                'accounts.two_factor_remember' => false,
                'accounts.two_factor_invitation_token' => $invitationToken,
                'accounts.google_reauthenticated_at' => now()->timestamp,
            ]);

            Auth::guard('web')->logout();
            $request->session()->regenerate();

            return redirect()->route('two-factor.login');
        }

        Auth::login($user);
        $request->session()->regenerate();
        $request->session()->put('accounts.google_reauthenticated_at', now()->timestamp);

        $audit->record(
            event: 'auth.login',
            actor: $user,
            subject: $user,
            metadata: ['provider' => 'google', 'mfa_method' => null],
        );

        if ($invitationToken !== '') {
            return redirect()->route('invitations.show', ['token' => $invitationToken]);
        }

        return redirect()->intended(route('dashboard'));
    }

    private function ensureConfigured(): void
    {
        abort_unless(
            filled(config('services.google.client_id'))
            && filled(config('services.google.client_secret'))
            && filled(config('services.google.redirect')),
            404,
        );
    }
}
