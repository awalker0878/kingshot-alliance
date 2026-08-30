<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\Actions;

use App\Contexts\GameWorld\GiftCodes\Enums\GiftCodeRedemptionStatus;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeRedemption;
use App\Contexts\GameWorld\Players\Queries\PlayerReferenceQuery;
use App\Shared\Infrastructure\AuditTrail\Contracts\AuditActor;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Http\BulkActionResult;
use App\Shared\Infrastructure\Http\BulkItemResult;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;

final readonly class PrepareGiftCodeRedemptions
{
    public function __construct(
        private PlayerReferenceQuery $players,
        private BeginGiftCodeRedemption $begin,
        private AuditRecorder $audit,
    ) {}

    /** @param non-empty-list<string> $targetPlayerIds */
    public function handle(
        AuditActor $actor,
        string $giftCodeId,
        array $targetPlayerIds,
    ): BulkActionResult {
        $ownerUserId = $actor->auditUserId();
        if ($ownerUserId === null) {
            throw new AuthorizationException('An account-owned Governor is required for Gift Code redemption.');
        }

        $items = [];

        foreach ($targetPlayerIds as $playerId) {
            $player = $this->players->findOwnedByUser($ownerUserId, $playerId);
            if ($player === null) {
                $items[] = BulkItemResult::failed($playerId, $playerId, 'governor-unavailable');

                continue;
            }

            $existing = GiftCodeRedemption::query()
                ->where('gift_code_id', $giftCodeId)
                ->where('player_id', $playerId)
                ->first();
            if ($existing instanceof GiftCodeRedemption && $existing->status->successful()) {
                $items[] = BulkItemResult::skipped($playerId, $player->currentName, 'already-redeemed');

                continue;
            }
            if ($existing instanceof GiftCodeRedemption
                && $existing->status->retryable()
                && $existing->next_attempt_at?->isFuture()) {
                $items[] = BulkItemResult::skipped($playerId, $player->currentName, 'retry-not-due');

                continue;
            }

            try {
                $result = $this->begin->handle($giftCodeId, $player);
                $items[] = match ($result->status) {
                    GiftCodeRedemptionStatus::AwaitingConfirmation => BulkItemResult::succeeded(
                        $playerId,
                        $player->currentName,
                        'handoff-prepared',
                    ),
                    GiftCodeRedemptionStatus::Redeemed,
                    GiftCodeRedemptionStatus::AlreadyRedeemed => BulkItemResult::skipped(
                        $playerId,
                        $player->currentName,
                        'already-redeemed',
                    ),
                    GiftCodeRedemptionStatus::RateLimited,
                    GiftCodeRedemptionStatus::TransientFailure => BulkItemResult::failed(
                        $playerId,
                        $player->currentName,
                        'retry-scheduled',
                    ),
                    GiftCodeRedemptionStatus::PermanentFailure => BulkItemResult::failed(
                        $playerId,
                        $player->currentName,
                        'governor-not-ready',
                    ),
                    default => BulkItemResult::failed(
                        $playerId,
                        $player->currentName,
                        'code-unavailable',
                    ),
                };
            } catch (ModelNotFoundException) {
                $items[] = BulkItemResult::failed($playerId, $player->currentName, 'code-unavailable');
            }
        }

        /** @var non-empty-list<BulkItemResult> $items */
        $result = new BulkActionResult('gift-code-redemption-prepare', $items);
        $payload = $result->toArray();
        $this->audit->record(
            'game_world.gift_code_redemptions.bulk_prepared',
            $actor,
            null,
            null,
            [
                'gift_code_id' => $giftCodeId,
                'target_player_ids' => $targetPlayerIds,
                'succeeded' => $payload['succeeded'],
                'failed' => $payload['failed'],
                'skipped' => $payload['skipped'],
            ],
        );

        return $result;
    }
}
