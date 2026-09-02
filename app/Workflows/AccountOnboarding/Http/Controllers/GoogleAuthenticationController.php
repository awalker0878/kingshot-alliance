<?php

declare(strict_types=1);

namespace App\Workflows\AccountOnboarding\Http\Controllers;

use App\Contexts\Accounts\Authentication\Actions\RecordAuthenticationAuditEvent;
use App\Contexts\Accounts\Authentication\Actions\RevokeOtherAccountSessions;
use App\Contexts\Accounts\Authentication\Enums\GoogleAuthenticationIntent;
use App\Contexts\Accounts\Authentication\Services\AccountSignInMethodPolicy;
use App\Contexts\Accounts\Authentication\Services\GoogleAuthenticationOperation;
use App\Contexts\Accounts\Authentication\Services\RecentAuthentication;
use App\Contexts\Accounts\Identity\Actions\CreateAccountIdentity;
use App\Contexts\Accounts\Identity\Actions\RecordAccountIdentityUse;
use App\Contexts\Accounts\Identity\Actions\RemoveAccountIdentity;
use App\Contexts\Accounts\Identity\Contracts\AuthenticatedAccount;
use App\Contexts\Accounts\Identity\Queries\AccountIdentityQuery;
use App\Contexts\Accounts\Identity\Queries\ProviderIdentityQuery;
use App\Contexts\Accounts\Registration\Data\RegistrationProviderIdentity;
use App\Contexts\Accounts\Security\Services\SecurityNotificationService;
use App\Contexts\Alliance\Membership\Queries\FindPendingInvitation;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Http\Controller;
use App\Workflows\AccountOnboarding\Actions\RegisterAccount;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\QueryException;
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
    public function redirect(Request $request, GoogleAuthenticationOperation $operations): RedirectResponse
    {
        $this->ensureConfigured();
        abort_if(
            $request->user() instanceof Authenticatable,
            409,
            'Use Security settings to connect Google to an existing account.',
        );

        $intent = GoogleAuthenticationIntent::tryFrom((string) $request->query('intent', 'login'));
        abort_unless(
            in_array($intent, [GoogleAuthenticationIntent::Login, GoogleAuthenticationIntent::Register], true),
            422,
        );

        $invitationToken = trim((string) $request->query('invitation', ''));
        $operations->start(
            request: $request,
            intent: $intent,
            invitationToken: $invitationToken === '' ? null : $invitationToken,
        );

        return Socialite::driver('google')->redirect();
    }

    public function reauthenticate(
        Request $request,
        AccountSignInMethodPolicy $methods,
        GoogleAuthenticationOperation $operations,
    ): RedirectResponse {
        $this->ensureConfigured();
        $user = $request->user();
        abort_unless($user instanceof AuthenticatedAccount, 401);
        $userId = (int) $user->getAuthIdentifier();
        abort_unless($methods->hasGoogle($userId), 403);

        $operations->start($request, GoogleAuthenticationIntent::Reauthenticate, $userId);

        return Socialite::driver('google')->redirect();
    }

    public function connect(
        Request $request,
        AccountSignInMethodPolicy $methods,
        GoogleAuthenticationOperation $operations,
    ): RedirectResponse {
        $this->ensureConfigured();
        $user = $request->user();
        abort_unless($user instanceof AuthenticatedAccount, 401);
        $userId = (int) $user->getAuthIdentifier();

        if ($methods->hasGoogle($userId)) {
            throw ValidationException::withMessages([
                'google' => 'Google is already connected to this Kingshot Alliance account.',
            ]);
        }

        $operations->start($request, GoogleAuthenticationIntent::Connect, $userId);

        return Socialite::driver('google')->redirect();
    }

    public function disconnect(
        Request $request,
        RemoveAccountIdentity $removeIdentity,
        RevokeOtherAccountSessions $revokeOtherSessions,
        RecentAuthentication $recentAuthentication,
        SecurityNotificationService $securityNotifications,
    ): RedirectResponse {
        $user = $request->user();
        abort_unless($user instanceof AuthenticatedAccount, 401);
        $userId = (int) $user->getAuthIdentifier();

        $removeIdentity->handle($userId, 'google');
        $revokeOtherSessions->handle($userId, $request->session()->getId());
        $recentAuthentication->clear($request);
        $securityNotifications->publish(
            userId: $userId,
            event: 'account.google.disconnected',
            title: (string) __('accounts.security.google_disconnected.title'),
            body: (string) __('accounts.security.google_disconnected.body'),
            idempotencyKey: 'account.google.disconnected:'.$userId.':'.now()->format('Uu'),
        );

        return redirect()->route('profile.show')->with(
            'actionReceipt',
            $this->receipt('google-disconnected'),
        );
    }

    public function callback(
        Request $request,
        FindPendingInvitation $invitations,
        RegisterAccount $registerAccount,
        AccountIdentityQuery $accounts,
        ProviderIdentityQuery $providerIdentities,
        CreateAccountIdentity $createIdentity,
        RecordAccountIdentityUse $recordIdentityUse,
        GoogleAuthenticationOperation $operations,
        RecentAuthentication $recentAuthentication,
        AccountSignInMethodPolicy $methods,
        SecurityNotificationService $securityNotifications,
        AuditRecorder $audit,
        RecordAuthenticationAuditEvent $authenticationAudit,
    ): RedirectResponse {
        $this->ensureConfigured();
        $operation = $operations->consume($request);

        try {
            $googleUser = Socialite::driver('google')->user();
        } catch (Throwable) {
            throw ValidationException::withMessages([
                'google' => 'Google sign-in could not be completed. Please try again.',
            ]);
        }

        [$email, $subject] = $this->verifiedGoogleIdentity($googleUser);
        $intent = $operation['intent'];

        if ($intent === GoogleAuthenticationIntent::Connect) {
            return $this->completeConnection(
                request: $request,
                expectedUserId: $operation['user_id'],
                subject: $subject,
                email: $email,
                providerIdentities: $providerIdentities,
                createIdentity: $createIdentity,
                recordIdentityUse: $recordIdentityUse,
                recentAuthentication: $recentAuthentication,
                methods: $methods,
                securityNotifications: $securityNotifications,
                authenticationAudit: $authenticationAudit,
            );
        }

        if ($intent === GoogleAuthenticationIntent::Reauthenticate) {
            return $this->completeReauthentication(
                request: $request,
                expectedUserId: $operation['user_id'],
                subject: $subject,
                email: $email,
                providerIdentities: $providerIdentities,
                recordIdentityUse: $recordIdentityUse,
                recentAuthentication: $recentAuthentication,
                authenticationAudit: $authenticationAudit,
            );
        }

        abort_if(
            $request->user() instanceof Authenticatable,
            409,
            'An authenticated account cannot start a new Google sign-in.',
        );

        $invitationToken = $operation['invitation_token'];
        $invitation = $invitationToken === null ? null : $invitations->byToken($invitationToken);
        $providerIdentity = $providerIdentities->findByProviderSubject('google', $subject);

        if ($providerIdentity !== null) {
            $account = $accounts->require($providerIdentity->userId);
            abort_unless(! $account->anonymized, 403);
            $recordIdentityUse->handle($providerIdentity->identityId, $email, true);

            return $this->completeLogin(
                request: $request,
                userId: $account->userId,
                invitationToken: $invitationToken,
                accounts: $accounts,
                recentAuthentication: $recentAuthentication,
                authenticationAudit: $authenticationAudit,
            );
        }

        if ($accounts->findIdByEmail($email) !== null) {
            $audit->record(
                event: 'auth.google.identity_failed',
                metadata: ['reason' => 'email_collision'],
            );

            throw ValidationException::withMessages([
                'google' => 'A Kingshot Alliance account already uses this email. Sign in to that account first, then connect Google from Security settings.',
            ]);
        }

        if ($intent !== GoogleAuthenticationIntent::Register) {
            throw ValidationException::withMessages([
                'google' => 'No Kingshot Alliance account is connected to this Google account. Create an account with Google, or sign in another way and connect Google from Security settings.',
            ]);
        }

        $registrationMode = (string) config('accounts.registration_mode', 'open');
        if ($registrationMode !== 'open' && $invitation === null) {
            abort(403, 'A valid invitation is required to register.');
        }
        if ($invitationToken !== null && $invitation === null) {
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

        try {
            $result = $registerAccount->handle(
                name: Str::limit($name, 100, ''),
                email: $email,
                password: null,
                timezone: (string) config('app.timezone', 'UTC'),
                invitationToken: $invitationToken,
                emailVerified: true,
                providerIdentity: new RegistrationProviderIdentity(
                    provider: 'google',
                    subject: $subject,
                    email: $email,
                    emailVerified: true,
                ),
            );
        } catch (QueryException) {
            throw ValidationException::withMessages([
                'google' => 'This Google account could not be attached safely. Sign in to the existing account if one already owns it.',
            ]);
        }

        abort_unless(Auth::loginUsingId($result->userId) instanceof Authenticatable, 401);
        $request->session()->regenerate();

        $identity = $providerIdentities->findForUser($result->userId, 'google');
        $recentAuthentication->mark(
            $request,
            'google',
            $identity === null ? null : (string) $identity->identityId,
        );
        $authenticationAudit->handle(
            userId: $result->userId,
            event: 'auth.login',
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

    private function completeConnection(
        Request $request,
        ?int $expectedUserId,
        string $subject,
        string $email,
        ProviderIdentityQuery $providerIdentities,
        CreateAccountIdentity $createIdentity,
        RecordAccountIdentityUse $recordIdentityUse,
        RecentAuthentication $recentAuthentication,
        AccountSignInMethodPolicy $methods,
        SecurityNotificationService $securityNotifications,
        RecordAuthenticationAuditEvent $authenticationAudit,
    ): RedirectResponse {
        $user = $request->user();
        abort_unless($user instanceof AuthenticatedAccount, 403);
        $userId = (int) $user->getAuthIdentifier();
        abort_unless($expectedUserId === $userId, 403);

        $ownedElsewhere = $providerIdentities->findByProviderSubject('google', $subject);
        if ($ownedElsewhere !== null && $ownedElsewhere->userId !== $userId) {
            $authenticationAudit->handle(
                userId: $userId,
                event: 'account.google.connection_rejected',
                metadata: ['reason' => 'subject_owned_elsewhere'],
            );

            throw ValidationException::withMessages([
                'google' => 'This Google account is already connected to another Kingshot Alliance account.',
            ]);
        }

        $existing = $providerIdentities->findForUser($userId, 'google');
        if ($existing !== null) {
            if (! hash_equals($existing->providerSubject, $subject)) {
                throw ValidationException::withMessages([
                    'google' => 'A different Google account is already connected. Disconnect it before connecting another one.',
                ]);
            }

            $recordIdentityUse->handle($existing->identityId, $email, true);
            $recentAuthentication->mark($request, 'google', (string) $existing->identityId);

            return redirect()->route('profile.show')->with(
                'actionReceipt',
                $this->receipt('google-connected'),
            );
        }

        try {
            $createIdentity->handle(
                userId: $userId,
                provider: 'google',
                providerSubject: $subject,
                providerEmail: $email,
                providerEmailVerified: true,
            );
        } catch (QueryException) {
            throw ValidationException::withMessages([
                'google' => 'This Google account could not be connected safely because it is already in use.',
            ]);
        }

        abort_unless($methods->hasGoogle($userId), 500);
        $identity = $providerIdentities->findForUser($userId, 'google');
        $recentAuthentication->mark(
            $request,
            'google',
            $identity === null ? null : (string) $identity->identityId,
        );

        $authenticationAudit->handle(userId: $userId, event: 'account.google.connected');
        $securityNotifications->publish(
            userId: $userId,
            event: 'account.google.connected',
            title: (string) __('accounts.security.google_connected.title'),
            body: (string) __('accounts.security.google_connected.body'),
            idempotencyKey: 'account.google.connected:'.$userId.':'.now()->format('Uu'),
        );

        return redirect()->route('profile.show')->with(
            'actionReceipt',
            $this->receipt('google-connected'),
        );
    }

    private function completeReauthentication(
        Request $request,
        ?int $expectedUserId,
        string $subject,
        string $email,
        ProviderIdentityQuery $providerIdentities,
        RecordAccountIdentityUse $recordIdentityUse,
        RecentAuthentication $recentAuthentication,
        RecordAuthenticationAuditEvent $authenticationAudit,
    ): RedirectResponse {
        $user = $request->user();
        abort_unless($user instanceof AuthenticatedAccount, 403);
        $userId = (int) $user->getAuthIdentifier();
        abort_unless($expectedUserId === $userId, 403);

        $identity = $providerIdentities->findForUser($userId, 'google');
        abort_unless($identity !== null, 403);

        if (! hash_equals($identity->providerSubject, $subject)) {
            $authenticationAudit->handle(
                userId: $userId,
                event: 'auth.google.identity_failed',
                metadata: ['reason' => 'reauthentication_subject_mismatch'],
            );

            abort(403, 'Google reauthentication did not match this Kingshot Alliance account.');
        }

        $recordIdentityUse->handle($identity->identityId, $email, true);
        $recentAuthentication->mark($request, 'google', (string) $identity->identityId);

        $authenticationAudit->handle(
            userId: $userId,
            event: 'auth.reauthenticated',
            metadata: ['provider' => 'google'],
        );

        return redirect()->intended(route('dashboard'));
    }

    private function completeLogin(
        Request $request,
        int $userId,
        ?string $invitationToken,
        AccountIdentityQuery $accounts,
        RecentAuthentication $recentAuthentication,
        RecordAuthenticationAuditEvent $authenticationAudit,
    ): RedirectResponse {
        if ($accounts->requiresMultiFactor($userId)) {
            $request->session()->put([
                'accounts.two_factor_challenge_user_id' => $userId,
                'accounts.two_factor_remember' => false,
                'accounts.two_factor_invitation_token' => $invitationToken ?? '',
                'accounts.two_factor_primary_method' => 'google',
            ]);

            return redirect()->route('two-factor.login');
        }

        abort_unless(Auth::loginUsingId($userId) instanceof Authenticatable, 401);
        $request->session()->regenerate();
        $recentAuthentication->mark($request, 'google');

        $authenticationAudit->handle(
            userId: $userId,
            event: 'auth.login',
            metadata: ['provider' => 'google', 'mfa_method' => null],
        );

        if ($invitationToken !== null) {
            return redirect()->route('invitations.show', ['token' => $invitationToken]);
        }

        return redirect()->intended(route('dashboard'));
    }

    /** @return array{0:string,1:string} */
    private function verifiedGoogleIdentity(mixed $googleUser): array
    {
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

        return [$email, $subject];
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
