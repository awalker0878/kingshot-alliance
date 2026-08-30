<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\Actions;

use App\Contexts\GameWorld\GiftCodes\Enums\GiftCodeRedemptionStatus;
use App\Contexts\GameWorld\GiftCodes\ValueObjects\GiftCodeRedemptionOutcome;
use App\Contexts\GameWorld\GiftCodes\ValueObjects\GiftCodeRedemptionReference;
use App\Contexts\GameWorld\Players\Queries\PlayerReferenceQuery;
use App\Shared\Infrastructure\AuditTrail\Contracts\AuditActor;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

final readonly class RecordObservedGiftCodeRedemptionResult
{
    public function __construct(
        private PlayerReferenceQuery $players,
        private RecordGiftCodeRedemptionOutcome $record,
    ) {}

    public function handle(
        AuditActor $actor,
        string $giftCodeId,
        string $playerId,
        string $result,
    ): GiftCodeRedemptionReference {
        $ownerUserId = $actor->auditUserId();
        if ($ownerUserId === null) {
            throw new AuthorizationException('An account-owned Governor is required to record a Gift Code result.');
        }
        $player = $this->players->findOwnedByUser($ownerUserId, $playerId);
        if ($player === null) {
            throw ValidationException::withMessages([
                'player_id' => 'That Governor is no longer owned by this account.',
            ]);
        }

        $outcome = match ($result) {
            'redeemed' => new GiftCodeRedemptionOutcome(
                GiftCodeRedemptionStatus::Redeemed,
                'governor_observed_redeemed',
                'The Governor observed that the reward was delivered in-game.',
            ),
            'already_redeemed' => new GiftCodeRedemptionOutcome(
                GiftCodeRedemptionStatus::AlreadyRedeemed,
                'governor_observed_already_redeemed',
                'The official center reported that this Governor already redeemed the code.',
            ),
            'invalid' => new GiftCodeRedemptionOutcome(
                GiftCodeRedemptionStatus::InvalidCode,
                'governor_observed_invalid',
                'The official center rejected this Gift Code for this Governor.',
            ),
            'expired' => new GiftCodeRedemptionOutcome(
                GiftCodeRedemptionStatus::Expired,
                'governor_observed_expired',
                'The official center reported that this Gift Code is expired for this Governor.',
            ),
            'wrong_kingdom' => new GiftCodeRedemptionOutcome(
                GiftCodeRedemptionStatus::WrongKingdom,
                'governor_observed_wrong_kingdom',
                'The official center reported a Kingdom applicability issue for this Governor.',
            ),
            'rate_limited' => new GiftCodeRedemptionOutcome(
                GiftCodeRedemptionStatus::RateLimited,
                'governor_observed_rate_limited',
                'The official center asked this Governor to retry later.',
            ),
            'temporarily_unavailable' => new GiftCodeRedemptionOutcome(
                GiftCodeRedemptionStatus::TransientFailure,
                'governor_observed_temporarily_unavailable',
                'The official center was temporarily unavailable for this Governor.',
            ),
            'permanent_failure' => new GiftCodeRedemptionOutcome(
                GiftCodeRedemptionStatus::PermanentFailure,
                'governor_observed_permanent_failure',
                'The redemption cannot continue automatically for this Governor.',
            ),
            default => throw new InvalidArgumentException('Gift Code redemption result is unsupported.'),
        };

        return $this->record->handle($giftCodeId, $player, 'governor_observation', $outcome);
    }
}
