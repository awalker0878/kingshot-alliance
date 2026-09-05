<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\Actions;

use App\Contexts\GameWorld\GiftCodes\Enums\GiftCodeRedemptionSessionItemState;
use App\Contexts\GameWorld\GiftCodes\Enums\GiftCodeRedemptionSessionStatus;
use App\Contexts\GameWorld\GiftCodes\Enums\GiftCodeRedemptionStatus;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeRedemptionSession;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeRedemptionSessionItem;
use App\Contexts\GameWorld\GiftCodes\Services\GiftCodeRedemptionSessionProgressor;
use App\Shared\Infrastructure\AuditTrail\Contracts\AuditActor;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class RecordGiftCodeRedemptionSessionItemResult
{
    public function __construct(
        private RecordObservedGiftCodeRedemptionResult $record,
        private GiftCodeRedemptionSessionProgressor $progress,
    ) {}

    public function handle(
        AuditActor $actor,
        string $sessionId,
        string $itemId,
        string $result,
    ): void {
        $userId = $actor->auditUserId();
        if ($userId === null) {
            throw new AuthorizationException('An authenticated account is required for a Gift Code session.');
        }
        $item = GiftCodeRedemptionSessionItem::query()
            ->whereKey($itemId)
            ->where('session_id', $sessionId)
            ->with('session')
            ->firstOrFail();
        if ($item->session->user_id !== $userId) {
            throw new AuthorizationException('This Gift Code session belongs to another account.');
        }
        if ($item->session->status !== GiftCodeRedemptionSessionStatus::Active) {
            throw ValidationException::withMessages(['session' => 'This Gift Code session is no longer active.']);
        }
        if ($item->state !== GiftCodeRedemptionSessionItemState::AwaitingConfirmation) {
            throw ValidationException::withMessages(['item' => 'Prepare this Gift Code handoff before recording an outcome.']);
        }

        $redemption = $this->record->handle($actor, $item->gift_code_id, $item->player_id, $result);
        $state = match (true) {
            $redemption->status->successful() => GiftCodeRedemptionSessionItemState::Completed,
            $redemption->status->retryable() => GiftCodeRedemptionSessionItemState::RetryWait,
            in_array($redemption->status, [
                GiftCodeRedemptionStatus::InvalidCode,
                GiftCodeRedemptionStatus::Expired,
                GiftCodeRedemptionStatus::WrongKingdom,
                GiftCodeRedemptionStatus::PermanentFailure,
            ], true) => GiftCodeRedemptionSessionItemState::Unavailable,
            default => GiftCodeRedemptionSessionItemState::Ready,
        };

        DB::transaction(function () use ($item, $state, $redemption): void {
            $locked = GiftCodeRedemptionSessionItem::query()->whereKey($item->id)->lockForUpdate()->firstOrFail();
            $locked->state = $state;
            $locked->unavailable_reason = $state === GiftCodeRedemptionSessionItemState::Unavailable
                ? 'governor_result_'.$redemption->status->value
                : null;
            $locked->completed_at = $state->terminal() ? CarbonImmutable::now('UTC') : null;
            $locked->save();
            $this->progress->refresh(GiftCodeRedemptionSession::query()->findOrFail($locked->session_id));
        });
    }
}
