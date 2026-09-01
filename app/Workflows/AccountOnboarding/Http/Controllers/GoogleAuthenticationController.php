<?php

declare(strict_types=1);

namespace App\Workflows\AccountOnboarding\Http\Controllers;

use App\Contexts\Accounts\Identity\Actions\CreateAccountIdentity;
use App\Contexts\Accounts\Identity\Actions\RecordAccountIdentityUse;
use App\Contexts\Accounts\Identity\Enums\AuthenticationType;
use App\Contexts\Accounts\Identity\Queries\AccountIdentityQuery;
use App\Contexts\Accounts\Identity\Queries\ProviderIdentityQuery;
use App\Contexts\Alliance\Membership\Queries\FindPendingInvitation;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Http\Controller;
use App\Workflows\AccountOnboarding\Actions\RegisterAccount;
use Illuminate\Contracts\Auth\Authenticatable;
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

    public function reauthenticate(Request $request, AccountIdentityQuery $accounts): RedirectResponse
    {
        $this->ensureConfigured();

        $authenticatedUser = $request->user();
        abort_unless($authenticatedUser instanceof Authenticatable, 401);

        $userId = (int) $authenticatedUser->getAuthIdentifier();
        abort_unless($accounts->supportsGoogleAuthentication($userId), 403);

        $request->session()->put('accounts.google_reauthentication_user_id', $userId);

        return Socialite::driver('google')->redirect();
    }

    public function callback(
        Request $request,
        FindPendingInvitation $invitations,
        RegisterAccount $registerAccount,
        AccountIdentityQuery $accounts,
        ProviderIdentityQuery $providerIdentities,
        CreateAccountIdentity $createIdentity,
        RecordAccountIdentityUse $recordIdentityUse,
        AuditRecorder $audit,
    ): RedirectResponse {
        $this->ensureConfigured();

        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (Throwable) {
            $request->session()->forget('accounts.google_reauthentication_user_id');

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
            $request->session()->forget('accounts.google_reauthentication_user_id');

            throw ValidationException::withMessages([
                'google' => 'Google must provide a stable identity and verified email address to sign in.',
            ]);
        }

        $reauthenticationUserId = $request->session()->pull('accounts.google_reauthentication_user_id');
        if ($reauthenticationUserId !== null) {
            return $this->completeReauthentication(
                request: $request,
                expectedUserId: (int) $reauthenticationUserId,
                subject: $subject,
                email: $email,
                accounts: $accounts,
                providerIdentities: $providerIdentities,
                recordIdentityUse: $recordIdentityUse,
                audit: $audit,
            );
        }

        abort_if($request->user() instanceof Authenticatable, 409, 'An authenticated account cannot start a new Google sign-in.');

        $invitationToken = trim((string) $request->session()->pull('accounts.google_invitation_token', ''));
        $invitation = $invitationToken === '' ? null : $invitations->byToken($invitationToken);

        $providerIdentity = $providerIdentities->findByProviderSubject(
            AuthenticationType::Google->value,
            $subject,
        );

        if ($providerIdentity !== null) {
            $account = $accounts->require($providerIdentity->userId);
            abort_unless(
                $accounts->supportsGoogleAuthentication($account->userId) && ! $account->anonymized,
                403,
            );

            $recordIdentityUse->handle($providerIdentity->identityId, $email, true);
        } else {
            if ($accounts->findIdByEmail($email) !== null) {
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

            $account = $accounts->require($result->userId);
            $createIdentity->handle(
                userId: $account->userId,
                provider: AuthenticationType::Google->value,
                providerSubject: $subject,
                providerEmail: $email,
                providerEmailVerified: true,
            );

            abort_unless(Auth::loginUsingId($account->userId) instanceof Authenticatable, 401);
            $request->session()->regenerate();
            $request->session()->put('accounts.google_reauthenticated_at', now()->timestamp);

            $audit->record(
                event: 'auth.login',
                actor: $account,
                subject: $account,
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

        if ($accounts->requiresMultiFactor($account->userId)) {
            $request->session()->put([
                'accounts.two_factor_challenge_user_id' => $account->userId,
                'accounts.two_factor_remember' => false,
                'accounts.two_factor_invitation_token' => $invitationToken,
                'accounts.google_reauthenticated_at' => now()->timestamp,
            ]);

            Auth::guard('web')->logout();
            $request->session()->regenerate();

            return redirect()->route('two-factor.login');
        }

        abort_unless(Auth::loginUsingId($account->userId) instanceof Authenticatable, 401);
        $request->session()->regenerate();
        $request->session()->put('accounts.google_reauthenticated_at', now()->timestamp);

        $audit->record(
            event: 'auth.login',
            actor: $account,
            subject: $account,
            metadata: ['provider' => 'google', 'mfa_method' => null],
        );

        if ($invitationToken !== '') {
            return redirect()->route('invitations.show', ['token' => $invitationToken]);
        }

        return redirect()->intended(route('dashboard'));
    }

    private function completeReauthentication(
        Request $request,
        int $expectedUserId,
        string $subject,
        string $email,
        AccountIdentityQuery $accounts,
        ProviderIdentityQuery $providerIdentities,
        RecordAccountIdentityUse $recordIdentityUse,
        AuditRecorder $audit,
    ): RedirectResponse {
        $authenticatedUser = $request->user();
        abort_unless($authenticatedUser instanceof Authenticatable, 401);

        $userId = (int) $authenticatedUser->getAuthIdentifier();
        $account = $accounts->require($userId);
        abort_unless(
            $account->userId === $expectedUserId
            && $accounts->supportsGoogleAuthentication($account->userId),
            403,
        );

        $identity = $providerIdentities->findForUser(
            $account->userId,
            AuthenticationType::Google->value,
        );
        abort_unless($identity !== null, 403);

        if (! hash_equals($identity->providerSubject, $subject)) {
            $audit->record(
                event: 'auth.google.identity_failed',
                actor: $account,
                subject: $account,
                metadata: ['reason' => 'reauthentication_subject_mismatch'],
            );

            abort(403, 'Google reauthentication did not match this Kingshot Alliance account.');
        }

        $recordIdentityUse->handle($identity->identityId, $email, true);
        $request->session()->put('accounts.google_reauthenticated_at', now()->timestamp);

        $audit->record(
            event: 'auth.reauthenticated',
            actor: $account,
            subject: $account,
            metadata: ['provider' => 'google'],
        );

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
