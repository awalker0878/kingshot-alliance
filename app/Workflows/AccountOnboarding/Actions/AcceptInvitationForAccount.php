<?php

declare(strict_types=1);

namespace App\Workflows\AccountOnboarding\Actions;

use App\Contexts\Accounts\Identity\Queries\AccountIdentityQuery;
use App\Contexts\Alliance\Membership\Actions\AcceptInvitation;
use App\Contexts\Alliance\Membership\Queries\FindPendingInvitation;
use App\Contexts\GameWorld\Players\Actions\ClaimPlayerAccount;
use App\Workflows\AccountOnboarding\Data\InvitationAcceptanceResult;
use Illuminate\Validation\ValidationException;

final readonly class AcceptInvitationForAccount
{
    public function __construct(
        private AccountIdentityQuery $accounts,
        private FindPendingInvitation $invitations,
        private ClaimPlayerAccount $claimPlayerAccount,
        private AcceptInvitation $acceptInvitation,
    ) {}

    public function handle(int $userId, string $token): InvitationAcceptanceResult
    {
        $account = $this->accounts->require($userId);
        $invitation = $this->invitations->byToken($token);

        if ($invitation === null) {
            throw ValidationException::withMessages([
                'invitation' => 'This invitation is no longer available.',
            ]);
        }

        $player = $this->claimPlayerAccount->handle($invitation->playerId, $account->userId);
        $membership = $this->acceptInvitation->handle(
            userId: $account->userId,
            userEmail: $account->email,
            token: $token,
            playerId: $player->playerId,
            playerKingdomId: $player->kingdomId,
        );

        return new InvitationAcceptanceResult(
            playerId: $membership->playerId,
            allianceId: $membership->allianceId,
            membershipId: $membership->membershipId,
        );
    }
}
