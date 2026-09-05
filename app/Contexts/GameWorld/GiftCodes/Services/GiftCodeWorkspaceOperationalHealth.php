<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\Services;

use App\Contexts\GameWorld\GiftCodes\Enums\GiftCodeRedemptionSessionItemState;
use App\Contexts\GameWorld\GiftCodes\Enums\GiftCodeRedemptionSessionStatus;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeAccountState;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeContributorProjection;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeRedemptionSession;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeRedemptionSessionItem;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeSourceRegistry;

final class GiftCodeWorkspaceOperationalHealth
{
    /** @return array<string,int|bool> */
    public function snapshot(): array
    {
        $activeSessions = GiftCodeRedemptionSession::query()
            ->where('status', GiftCodeRedemptionSessionStatus::Active->value)
            ->count();
        $staleSessions = GiftCodeRedemptionSession::query()
            ->where('status', GiftCodeRedemptionSessionStatus::Active->value)
            ->where('last_activity_at', '<', now()->subDay())
            ->count();

        return [
            'workspaceEnabled' => (bool) config('game_world.gift_codes.redemption_workspace', false),
            'intelligenceEnabled' => (bool) config('game_world.gift_codes.redemption_intelligence', false),
            'allianceCoverageEnabled' => (bool) config('game_world.gift_codes.alliance_coverage', false),
            'contributorReputationEnabled' => (bool) config('game_world.gift_codes.contributor_reputation', false),
            'sourceWebhookEnabled' => (bool) config('game_world.gift_codes.source_webhook_ingestion', false),
            'activeSessions' => $activeSessions,
            'staleSessions' => $staleSessions,
            'readyItems' => GiftCodeRedemptionSessionItem::query()->where('state', GiftCodeRedemptionSessionItemState::Ready->value)->count(),
            'awaitingConfirmationItems' => GiftCodeRedemptionSessionItem::query()->where('state', GiftCodeRedemptionSessionItemState::AwaitingConfirmation->value)->count(),
            'retryWaitItems' => GiftCodeRedemptionSessionItem::query()->where('state', GiftCodeRedemptionSessionItemState::RetryWait->value)->count(),
            'unavailableItems' => GiftCodeRedemptionSessionItem::query()->where('state', GiftCodeRedemptionSessionItemState::Unavailable->value)->count(),
            'dueReminders' => GiftCodeAccountState::query()->whereNotNull('remind_at')->where('remind_at', '<=', now())->count(),
            'contributorProjections' => GiftCodeContributorProjection::query()->count(),
            'pushEligibleSources' => GiftCodeSourceRegistry::query()
                ->where('is_active', true)
                ->where('ingestion_enabled', true)
                ->whereNull('revoked_at')
                ->count(),
        ];
    }
}
