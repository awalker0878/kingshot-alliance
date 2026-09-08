<?php

declare(strict_types=1);

namespace App\Workflows\AccountOnboarding\Http\Controllers;

use App\Contexts\Accounts\Authentication\Actions\AuthenticateWithGoogle;
use App\Contexts\Accounts\Authentication\Actions\ConfirmGoogleAccount;
use App\Contexts\Accounts\Authentication\Actions\ConnectGoogleAccount;
use App\Contexts\Accounts\Authentication\Enums\GoogleAuthenticationIntent;
use App\Contexts\Accounts\Authentication\Services\AccountSignInMethodPolicy;
use App\Contexts\Accounts\Authentication\Services\GoogleAuthenticationOperation;
use App\Contexts\Accounts\Authentication\Services\RecentAuthentication;
use App\Contexts\Accounts\Identity\Actions\RemoveAccountIdentity;
use App\Contexts\Accounts\Identity\Contracts\AuthenticatedAccount;
use App\Contexts\Accounts\Identity\Queries\AccountIdentityQuery;
use App\Contexts\Accounts\Identity\Queries\ProviderIdentityQuery;
use App\Contexts\Accounts\Registration\Data\RegistrationProviderIdentity;
use App\Contexts\Alliance\Membership\Queries\FindPendingInvitation;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Http\Controller;
use App\Workflows\AccountOnboarding\Actions\RegisterAccount;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
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
        RecentAuthentication $recentAuthentication,
    ): RedirectResponse {
        $user = $request->user();
        abort_unless($user instanceof AuthenticatedAccount, 401);
        $userId = (int) $user->getAuthIdentifier();

        $removeIdentity->handle($userId, 'google', $request->session()->getId());
        $recentAuthentication->clear($request);

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
        ConnectGoogleAccount $connectGoogle,
        ConfirmGoogleAccount $confirmGoogle,
        AuthenticateWithGoogle $authenticateGoogle,
        GoogleAuthenticationOperation $operations,
        AuditRecorder $audit,
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
                connectGoogle: $connectGoogle,
                confirmGoogle: $confirmGoogle,
            );
        }

        if ($intent === GoogleAuthenticationIntent::Reauthenticate) {
            return $this->completeReauthentication(
                request: $request,
                expectedUserId: $operation['user_id'],
                subject: $subject,
                email: $email,
                confirmGoogle: $confirmGoogle,
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
            if ($authenticateGoogle->handle($request, $providerIdentity->userId, $providerIdentity->identityId,
                $subject, $email, $invitationToken)) {
                return redirect()->route('two-factor.login');
            }

            return $invitationToken === null
                ? redirect()->intended(route('dashboard'))
                : redirect()->route('invitations.show', ['token' => $invitationToken]);
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

        $identity = $providerIdentities->findForUser($result->userId, 'google');
        abort_unless($identity !== null, 403);
        if ($authenticateGoogle->handle($request, $result->userId, $identity->identityId, $subject, $email, null)) {
            return redirect()->route('two-factor.login');
        }

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
        ConnectGoogleAccount $connectGoogle,
        ConfirmGoogleAccount $confirmGoogle,
    ): RedirectResponse {
        $user = $request->user();
        abort_unless($user instanceof AuthenticatedAccount, 403);
        $userId = (int) $user->getAuthIdentifier();
        abort_unless($expectedUserId === $userId, 403);

        $connectGoogle->handle($userId, $subject, $email);
        $confirmGoogle->handle($request, $userId, $subject, $email);

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
        ConfirmGoogleAccount $confirmGoogle,
    ): RedirectResponse {
        $confirmGoogle->handle($request, $expectedUserId, $subject, $email);

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
