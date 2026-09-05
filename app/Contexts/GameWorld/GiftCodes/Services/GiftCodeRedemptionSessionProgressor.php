<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\Services;

use App\Contexts\GameWorld\GiftCodes\Enums\GiftCodeRedemptionSessionItemState;
use App\Contexts\GameWorld\GiftCodes\Enums\GiftCodeRedemptionSessionStatus;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeRedemptionSession;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeRedemptionSessionItem;
use Carbon\CarbonImmutable;

final class GiftCodeRedemptionSessionProgressor
{
    public function refresh(GiftCodeRedemptionSession $session): GiftCodeRedemptionSession
    {
        $locked = GiftCodeRedemptionSession::query()->whereKey($session->id)->lockForUpdate()->firstOrFail();
        if ($locked->status === GiftCodeRedemptionSessionStatus::Abandoned) {
            return $locked;
        }

        $counts = GiftCodeRedemptionSessionItem::query()
            ->where('session_id', $locked->id)
            ->selectRaw('state, COUNT(*) AS aggregate_count')
            ->groupBy('state')
            ->pluck('aggregate_count', 'state');
        $count = static fn (GiftCodeRedemptionSessionItemState $state): int => (int) ($counts[$state->value] ?? 0);

        $locked->total_items = (int) $counts->sum();
        $locked->completed_items = $count(GiftCodeRedemptionSessionItemState::Completed);
        $locked->skipped_items = $count(GiftCodeRedemptionSessionItemState::Skipped);
        $locked->failed_items = $count(GiftCodeRedemptionSessionItemState::RetryWait)
            + $count(GiftCodeRedemptionSessionItemState::Unavailable);
        $locked->last_activity_at = CarbonImmutable::now('UTC');

        $open = $count(GiftCodeRedemptionSessionItemState::Pending)
            + $count(GiftCodeRedemptionSessionItemState::Ready)
            + $count(GiftCodeRedemptionSessionItemState::AwaitingConfirmation)
            + $count(GiftCodeRedemptionSessionItemState::RetryWait);
        if ($locked->total_items > 0 && $open === 0) {
            $locked->status = GiftCodeRedemptionSessionStatus::Completed;
            $locked->completed_at ??= CarbonImmutable::now('UTC');
        }
        $locked->save();

        return $locked->refresh();
    }
}
