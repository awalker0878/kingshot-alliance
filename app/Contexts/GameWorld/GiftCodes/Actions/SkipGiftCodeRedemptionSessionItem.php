<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\Actions;

use App\Contexts\GameWorld\GiftCodes\Enums\GiftCodeRedemptionSessionItemState;
use App\Contexts\GameWorld\GiftCodes\Enums\GiftCodeRedemptionSessionStatus;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeRedemptionSession;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeRedemptionSessionItem;
use App\Contexts\GameWorld\GiftCodes\Services\GiftCodeRedemptionSessionProgressor;
use App\Shared\Infrastructure\AuditTrail\Contracts\AuditActor;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class SkipGiftCodeRedemptionSessionItem
{
    public function __construct(private GiftCodeRedemptionSessionProgressor $progress) {}

    public function handle(AuditActor $actor, string $sessionId, string $itemId, string $reason = 'user_skipped'): void
    {
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
        if ($item->state->terminal()) {
            return;
        }

        DB::transaction(function () use ($item, $reason): void {
            $locked = GiftCodeRedemptionSessionItem::query()->whereKey($item->id)->lockForUpdate()->firstOrFail();
            $locked->state = GiftCodeRedemptionSessionItemState::Skipped;
            $locked->skip_reason = mb_substr(trim($reason), 0, 120) ?: 'user_skipped';
            $locked->completed_at = CarbonImmutable::now('UTC');
            $locked->save();
            $this->progress->refresh(GiftCodeRedemptionSession::query()->findOrFail($locked->session_id));
        });
    }
}
