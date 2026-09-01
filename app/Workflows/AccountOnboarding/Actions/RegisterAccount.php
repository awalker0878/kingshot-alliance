<?php

declare(strict_types=1);

namespace App\Workflows\AccountOnboarding\Actions;

use App\Contexts\Accounts\Registration\Actions\RegisterUser;
use App\Contexts\Alliance\Membership\Actions\AcceptInvitation;
use App\Contexts\Alliance\Membership\Queries\FindPendingInvitation;
use App\Contexts\GameWorld\Players\Actions\ClaimPlayerAccount;
use App\Workflows\AccountOnboarding\Data\RegistrationResult;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final readonly class RegisterAccount
{
    public function __construct(
        private RegisterUser $registerUser,
        private FindPendingInvitation $invitations,
        private ClaimPlayerAccount $claimPlayerAccount,
        private AcceptInvitation $acceptInvitation,
    ) {}

    public function handle(
        string $name,
        string $email,
        string $password,
        string $timezone,
        ?string $invitationToken,
        bool $emailVerified = false,
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
        );

        if ($invitation === null || $invitationToken === null) {
            return new RegistrationResult(userId: $account->userId);
        }

        $player = $this->claimPlayerAccount->handle($invitation->playerId, $account->userId);
        $membership = $this->acceptInvitation->handle(
            userId: $account->userId,
            userEmail: $account->email,
            token: $invitationToken,
            playerId: $player->playerId,
            playerKingdomId: $player->kingdomId,
        );

        return new RegistrationResult(
            userId: $account->userId,
            playerId: $membership->playerId,
            allianceId: $membership->allianceId,
            membershipId: $membership->membershipId,
        );
    }
}
