<?php

declare(strict_types=1);

namespace Tests\v3\ReadModels\CommandOverview;

use App\Contexts\Communications\Delivery\Services\NotificationDeliveryService;
use App\Contexts\Communications\Delivery\ValueObjects\NotificationIntent;
use App\Contexts\GameWorld\GiftCodes\Enums\GiftCodeStatus;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCode;
use App\ReadModels\CommandOverview\Queries\CommandOverviewQuery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\v3\Support\ScenarioFactory;
use Tests\v3\TestCase;

final class CommandOverviewBehaviorV3Test extends TestCase
{
    use RefreshDatabase;

    public function test_overview_surfaces_only_the_active_governors_actionable_work(): void
    {
        $scenarios = app(ScenarioFactory::class);
        $account = $scenarios->account();
        $player = $scenarios->player($account->userId);
        $other = $scenarios->account();
        $otherPlayer = $scenarios->player($other->userId);

        GiftCode::query()->create([
            'code' => 'COMMAND-CENTER',
            'normalized_code' => 'COMMAND-CENTER',
            'status' => GiftCodeStatus::Valid,
            'status_revision' => 1,
            'status_reason_code' => 'qualified_positive_evidence',
            'status_evidence_ids' => [],
            'status_changed_at' => now(),
            'status_derived_at' => now(),
            'discovered_at' => now(),
            'expires_revision' => 0,
        ]);

        $deliveries = app(NotificationDeliveryService::class);
        $deliveries->queue(NotificationIntent::fromScalars(
            notificationType: 'event.reminder',
            recipientUserId: $account->userId,
            playerId: $player->playerId,
            availableAt: now()->subMinute(),
            idempotencyKey: 'command-overview-notification',
            title: 'Alliance event',
        ));

        $deliveries->queue(NotificationIntent::fromScalars(
            notificationType: 'event.reminder',
            recipientUserId: $other->userId,
            playerId: $otherPlayer->playerId,
            availableAt: now()->subMinute(),
            idempotencyKey: 'other-command-overview-notification',
            title: 'Other alliance event',
        ));

        $overview = app(CommandOverviewQuery::class)->for(
            $account->userId,
            $player,
            null,
            false,
        );

        self::assertSame(1, $overview['unreadNotifications']);
        self::assertSame(1, $overview['pendingGiftCodes']);
        self::assertSame(2, $overview['actionCount']);
        self::assertSame(1, $overview['giftCodeLifecycle']['newRedeemable']);
        self::assertSame([], $overview['intelligenceSignals']);
        self::assertSame('COMMAND-CENTER', $overview['giftCodes'][0]['code']);
        self::assertSame([], $overview['upcomingEvents']);
        self::assertNull($overview['recruitment']);
    }
}
