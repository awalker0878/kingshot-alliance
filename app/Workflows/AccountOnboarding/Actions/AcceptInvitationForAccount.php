<?php

declare(strict_types=1);

namespace App\Workflows\AccountOnboarding\Actions;

use App\Contexts\Accounts\Identity\Queries\AccountIdentityQuery;
use App\Contexts\Alliance\Membership\Actions\AcceptInvitation;
use App\Contexts\Alliance\Membership\Services\InvitationAcceptanceScope;
use App\Contexts\GameWorld\Players\Actions\ClaimPlayerAccount;
use App\Workflows\AccountOnboarding\Data\InvitationAcceptanceResult;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

final readonly class AcceptInvitationForAccount
{
    public function __construct(
        private AccountIdentityQuery $accounts,
        private InvitationAcceptanceScope $invitationScope,
        private ClaimPlayerAccount $claimPlayerAccount,
        private AcceptInvitation $acceptInvitation,
    ) {}

    public function handle(int $userId, string $token): InvitationAcceptanceResult
    {
        return DB::transaction(function () use ($userId, $token): InvitationAcceptanceResult {
            $account = $this->accounts->lockCurrent($userId);
            if ($account->anonymized) {
                throw new AuthorizationException;
            }

            $invitation = $this->invitationScope->lock($token);

            $player = $this->claimPlayerAccount->handle($invitation->playerId, $account->userId);
            $membership = $this->acceptInvitation->handle(
                userId: $account->userId,
                token: $token,
                playerId: $player->playerId,
            );

            return new InvitationAcceptanceResult(
                playerId: $membership->playerId,
                allianceId: $membership->allianceId,
                membershipId: $membership->membershipId,
            );
        });
    }
}
