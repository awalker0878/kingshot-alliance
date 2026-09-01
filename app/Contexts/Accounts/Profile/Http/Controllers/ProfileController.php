<?php

declare(strict_types=1);

namespace App\Contexts\Accounts\Profile\Http\Controllers;

use App\Contexts\Accounts\Authentication\Actions\RevokeOtherAccountSessions;
use App\Contexts\Accounts\Authentication\Models\AccountSession;
use App\Contexts\Accounts\Identity\Enums\AuthenticationType;
use App\Contexts\Accounts\Identity\Models\User;
use App\Contexts\Accounts\Profile\Actions\ChangePassword;
use App\Contexts\Accounts\Profile\Actions\UpdateProfile;
use App\Contexts\Accounts\Security\Queries\AccountSecurityActivityQuery;
use App\Shared\Infrastructure\Http\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Inertia\Inertia;
use Inertia\Response;

final class ProfileController extends Controller
{
    public function show(Request $request, AccountSecurityActivityQuery $securityActivity): Response
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $currentSessionHash = hash('sha256', $request->session()->getId());
        $sessions = AccountSession::query()
            ->where('user_id', $user->id)
            ->whereNull('revoked_at')
            ->latest('last_seen_at')
            ->limit(25)
            ->get()
            ->map(static fn (AccountSession $session): array => [
                'id' => (string) $session->public_id,
                'browser' => $session->browser_family,
                'platform' => $session->platform_family,
                'device' => $session->device_family,
                'firstSeenAt' => $session->first_seen_at->toIso8601String(),
                'lastSeenAt' => $session->last_seen_at->toIso8601String(),
                'current' => hash_equals($session->session_id_hash, $currentSessionHash),
            ])
            ->values()
            ->all();

        $googleIdentity = $user->authentication_type === AuthenticationType::Google
            ? $user->accountIdentities()->where('provider', AuthenticationType::Google->value)->first()
            : null;
        $recoveryCodes = $user->two_factor_recovery_codes;

        return Inertia::render('Accounts/Governor/Profile', [
            'user' => [
                'name' => $user->name,
                'email' => $user->email,
                'emailVerified' => $user->hasVerifiedEmail(),
                'pendingEmail' => $user->pending_email,
                'pendingEmailRequestedAt' => $user->pending_email_requested_at?->toIso8601String(),
                'timezone' => $user->timezone,
                'authenticationType' => $user->authentication_type->value,
                'passwordAuthentication' => $user->supportsPasswordAuthentication(),
                'googleAuthentication' => $user->supportsGoogleAuthentication(),
                'providerEmail' => $googleIdentity?->provider_email,
                'twoFactorEnabled' => $user->two_factor_confirmed_at !== null,
                'twoFactorPending' => $user->two_factor_secret !== null && $user->two_factor_confirmed_at === null,
                'recoveryCodeCount' => is_array($recoveryCodes) ? count($recoveryCodes) : 0,
            ],
            'sessions' => $sessions,
            'securityActivity' => $securityActivity->forUser((int) $user->id),
            'twoFactorSetup' => $request->session()->get('two_factor_setup'),
            'twoFactorRecoveryCodes' => $request->session()->pull('two_factor_recovery_codes'),
        ]);
    }

    public function update(Request $request, UpdateProfile $updateProfile): RedirectResponse
    {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'timezone' => ['required', 'timezone:all'],
        ]);

        $updateProfile->handle(
            (int) $user->id,
            (string) $validated['name'],
            (string) $validated['timezone'],
        );

        return redirect()->route('profile.show')->with('actionReceipt', $this->receipt('profile-updated'));
    }

    public function updatePassword(
        Request $request,
        ChangePassword $changePassword,
        RevokeOtherAccountSessions $revokeOtherSessions,
    ): RedirectResponse {
        $user = $request->user();
        abort_unless($user instanceof User, 401);

        $validated = $request->validate([
            'current_password' => ['required', 'string'],
            'password' => ['required', 'confirmed', Password::min(12)->letters()->mixedCase()->numbers()],
        ]);

        $changePassword->handle(
            (int) $user->id,
            (string) $validated['current_password'],
            (string) $validated['password'],
        );
        $revokeOtherSessions->handle((int) $user->id, $request->session()->getId());

        return redirect()->route('profile.show')->with('actionReceipt', $this->receipt('password-updated'));
    }

    public function destroyOtherSessions(): never
    {
        abort(404);
    }
}
