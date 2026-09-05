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
use Illuminate\Validation\ValidationException;

final readonly class PrepareGiftCodeRedemptionSessionItem
{
    public function __construct(
        private PlayerReferenceQuery $players,
        private GiftCodeActionablePairResolver $pairs,
        private PrepareGiftCodeRedemptions $prepare,
        private GiftCodeRedemptionSessionProgressor $progress,
    ) {}

    public function handle(AuditActor $actor, string $sessionId, string $itemId): void
    {
        $userId = $actor->auditUserId();
        if ($userId === null) {
            throw new AuthorizationException('An authenticated account is required for a Gift Code session.');
        }
        $item = GiftCodeRedemptionSessionItem::query()
            ->whereKey($itemId)
            ->where('session_id', $sessionId)
            ->with(['session', 'giftCode.factProjections'])
            ->firstOrFail();
        if ($item->session->user_id !== $userId) {
            throw new AuthorizationException('This Gift Code session belongs to another account.');
        }
        if ($item->session->status !== GiftCodeRedemptionSessionStatus::Active) {
            throw ValidationException::withMessages(['session' => 'This Gift Code session is no longer active.']);
        }
        if ($item->state->terminal()) {
            return;
        }

        $player = $this->players->findOwnedByUser($userId, $item->player_id);
        if ($player === null) {
            $this->mark($item, GiftCodeRedemptionSessionItemState::Unavailable, 'governor_unavailable');

            return;
        }
        $decision = $this->pairs->resolve($item->giftCode, $player);
        if (! $decision->actionable) {
            if ($decision->reason === 'retry_not_due') {
                $this->mark($item, GiftCodeRedemptionSessionItemState::RetryWait, null);
            } else {
                $this->mark($item, GiftCodeRedemptionSessionItemState::Unavailable, $decision->reason);
            }

            return;
        }

        $result = $this->prepare->handle($actor, $item->gift_code_id, [$item->player_id]);
        $first = $result->items[0];
        $state = match ($first->code) {
            'handoff-prepared' => GiftCodeRedemptionSessionItemState::AwaitingConfirmation,
            'already-redeemed' => GiftCodeRedemptionSessionItemState::Completed,
            'retry-scheduled', 'retry-not-due' => GiftCodeRedemptionSessionItemState::RetryWait,
            default => GiftCodeRedemptionSessionItemState::Unavailable,
        };

        $this->mark(
            $item,
            $state,
            $state === GiftCodeRedemptionSessionItemState::Unavailable ? $first->code : null,
        );
    }

    private function mark(
        GiftCodeRedemptionSessionItem $item,
        GiftCodeRedemptionSessionItemState $state,
        ?string $reason,
    ): void {
        DB::transaction(function () use ($item, $state, $reason): void {
            $locked = GiftCodeRedemptionSessionItem::query()->whereKey($item->id)->lockForUpdate()->firstOrFail();
            $locked->state = $state;
            $locked->unavailable_reason = $reason;
            $locked->completed_at = $state->terminal() ? CarbonImmutable::now('UTC') : null;
            $locked->save();
            $this->progress->refresh(GiftCodeRedemptionSession::query()->findOrFail($locked->session_id));
        });
    }
}
