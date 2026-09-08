<?php

declare(strict_types=1);

namespace App\Workflows\AccountOnboarding\Actions;

use App\Contexts\Accounts\Registration\Actions\RegisterUser;
use App\Contexts\Accounts\Registration\Data\RegistrationProviderIdentity;
use App\Contexts\Alliance\Membership\Queries\FindPendingInvitation;
use App\Workflows\AccountOnboarding\Data\RegistrationResult;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final readonly class RegisterAccount
{
    public function __construct(
        private RegisterUser $registerUser,
        private FindPendingInvitation $invitations,
        private AcceptInvitationForAccount $acceptInvitation,
    ) {}

    public function handle(
        string $name,
        string $email,
        ?string $password,
        string $timezone,
        ?string $invitationToken,
        bool $emailVerified = false,
        ?RegistrationProviderIdentity $providerIdentity = null,
    ): RegistrationResult {
        return DB::transaction(function () use (
            $name,
            $email,
            $password,
            $timezone,
            $invitationToken,
            $emailVerified,
            $providerIdentity,
        ): RegistrationResult {
            $invitation = $invitationToken === null
                ? null
                : $this->invitations->byToken($invitationToken);

            if ($invitationToken !== null && $invitation === null) {
                throw ValidationException::withMessages([
                    'invitation_token' => 'This invitation is no longer available.',
                ]);
            }

            if ($invitation !== null && ! hash_equals(
                Str::lower($invitation->email),
                Str::lower(trim($email)),
            )) {
                throw ValidationException::withMessages([
                    'email' => 'Use the email address that received this invitation.',
                ]);
            }

            $account = $this->registerUser->handle(
                name: $name,
                email: $email,
                password: $password,
                timezone: $timezone,
                emailVerified: $emailVerified,
                providerIdentity: $providerIdentity,
            );

            if ($invitation === null || $invitationToken === null) {
                return new RegistrationResult(userId: $account->userId);
            }

            $membership = $this->acceptInvitation->handle(
                userId: $account->userId,
                token: $invitationToken,
            );

            return new RegistrationResult(
                userId: $account->userId,
                playerId: $membership->playerId,
                allianceId: $membership->allianceId,
                membershipId: $membership->membershipId,
            );
        });
    }
}
