<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\Actions;

use App\Contexts\GameWorld\GiftCodes\Enums\GiftCodeRedemptionSessionItemState;
use App\Contexts\GameWorld\GiftCodes\Enums\GiftCodeRedemptionSessionStatus;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeRedemption;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeRedemptionSession;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeRedemptionSessionItem;
use App\Contexts\GameWorld\GiftCodes\Services\GiftCodeActionablePairResolver;
use App\Contexts\GameWorld\GiftCodes\Services\GiftCodeRedemptionSessionProgressor;
use App\Contexts\GameWorld\Players\Queries\PlayerReferenceQuery;
use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;
use App\Shared\Infrastructure\AuditTrail\Contracts\AuditActor;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;

final readonly class ReconcileGiftCodeRedemptionSession
{
    public function __construct(
        private PlayerReferenceQuery $players,
        private GiftCodeActionablePairResolver $pairs,
        private GiftCodeRedemptionSessionProgressor $progress,
    ) {}

    public function handle(AuditActor $actor, string $sessionId): void
    {
        $userId = $actor->auditUserId();
        if ($userId === null) {
            throw new AuthorizationException('An authenticated account is required for a Gift Code session.');
        }

        $session = GiftCodeRedemptionSession::query()
            ->whereKey($sessionId)
            ->where('user_id', $userId)
            ->with(['items.giftCode.factProjections'])
            ->firstOrFail();
        if ($session->status !== GiftCodeRedemptionSessionStatus::Active) {
            return;
        }

        $openItems = $session->items->filter(static fn (GiftCodeRedemptionSessionItem $item): bool => ! $item->state->terminal());
        $playerIds = $openItems->pluck('player_id')->unique()->values()->all();
        $playerMap = array_filter(
            $this->players->byIds($playerIds),
            static fn (PlayerReference $player): bool => $player->userId === $userId,
        );
        $codeIds = $openItems->pluck('gift_code_id')->unique()->values()->all();
        $redemptionMap = GiftCodeRedemption::query()
            ->whereIn('gift_code_id', $codeIds)
            ->whereIn('player_id', $playerIds)
            ->get()
            ->keyBy(static fn (GiftCodeRedemption $redemption): string => $redemption->gift_code_id.'|'.$redemption->player_id);

        DB::transaction(function () use ($session, $openItems, $playerMap, $redemptionMap): void {
            foreach ($openItems as $item) {
                $player = $playerMap[$item->player_id] ?? null;
                $redemption = $redemptionMap->get($item->gift_code_id.'|'.$item->player_id);
                $decision = $player instanceof PlayerReference
                    ? $this->pairs->resolveWithRedemption(
                        $item->giftCode,
                        $player,
                        $redemption instanceof GiftCodeRedemption ? $redemption : null,
                    )
                    : null;
                $nextState = $item->state;
                $reason = null;
                $completedAt = null;

                if (! $player instanceof PlayerReference) {
                    $nextState = GiftCodeRedemptionSessionItemState::Unavailable;
                    $reason = 'governor_unavailable';
                    $completedAt = CarbonImmutable::now('UTC');
                } elseif ($decision !== null && $decision->actionable) {
                    if ($item->state === GiftCodeRedemptionSessionItemState::RetryWait) {
                        $nextState = GiftCodeRedemptionSessionItemState::Ready;
                    }
                } elseif ($decision !== null && $decision->reason === 'retry_not_due') {
                    $nextState = GiftCodeRedemptionSessionItemState::RetryWait;
                } elseif ($decision !== null) {
                    $nextState = GiftCodeRedemptionSessionItemState::Unavailable;
                    $reason = $decision->reason;
                    $completedAt = CarbonImmutable::now('UTC');
                }

                if ($nextState === $item->state
                    && $reason === $item->unavailable_reason
                    && $completedAt === null) {
                    continue;
                }

                $locked = GiftCodeRedemptionSessionItem::query()
                    ->whereKey($item->id)
                    ->where('session_id', $session->id)
                    ->lockForUpdate()
                    ->firstOrFail();
                if ($locked->state->terminal()) {
                    continue;
                }
                $locked->state = $nextState;
                $locked->unavailable_reason = $reason;
                $locked->completed_at = $completedAt;
                $locked->status_revision_snapshot = $item->giftCode->status_revision;
                $locked->expires_revision_snapshot = $item->giftCode->expires_revision;
                $locked->save();
            }

            $this->progress->refresh($session);
        });
    }
}
