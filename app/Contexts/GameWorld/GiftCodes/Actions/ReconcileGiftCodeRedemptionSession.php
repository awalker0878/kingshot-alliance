<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\Actions;

use App\Contexts\GameWorld\GiftCodes\Enums\GiftCodeRedemptionSessionItemState;
use App\Contexts\GameWorld\GiftCodes\Enums\GiftCodeRedemptionSessionStatus;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeRedemptionSession;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeRedemptionSessionItem;
use App\Contexts\GameWorld\GiftCodes\Services\GiftCodeActionablePairResolver;
use App\Contexts\GameWorld\GiftCodes\Services\GiftCodeRedemptionSessionProgressor;
use App\Contexts\GameWorld\Players\Queries\PlayerReferenceQuery;
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

    public function handle(AuditActor $actor, string $sessionId): GiftCodeRedemptionSession
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
            return $session;
        }

        DB::transaction(function () use ($session, $userId): void {
            foreach ($session->items as $item) {
                if ($item->state->terminal()) {
                    continue;
                }

                $player = $this->players->findOwnedByUser($userId, $item->player_id);
                $decision = $player === null
                    ? null
                    : $this->pairs->resolve($item->giftCode, $player);
                $nextState = $item->state;
                $reason = null;
                $completedAt = null;

                if ($player === null) {
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

        return GiftCodeRedemptionSession::query()
            ->with(['items.giftCode.factProjections'])
            ->findOrFail($session->id);
    }
}
