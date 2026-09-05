<?php

declare(strict_types=1);

namespace Tests\v3\Contexts\Alliance\Content;

use App\Contexts\Alliance\Content\Actions\CancelAnnouncementBroadcastSchedule;
use App\Contexts\Alliance\Content\Actions\QueuePublishedAnnouncementBroadcasts;
use App\Contexts\Alliance\Content\Actions\RetryAnnouncementBroadcastFailures;
use App\Contexts\Alliance\Content\Actions\SaveAnnouncementBroadcastSchedule;
use App\Contexts\Alliance\Content\Actions\TestAnnouncementBroadcast;
use App\Contexts\Alliance\Content\Enums\BroadcastRunStatus;
use App\Contexts\Alliance\Content\Enums\BroadcastScheduleStatus;
use App\Contexts\Alliance\Content\Enums\ContentStatus;
use App\Contexts\Alliance\Content\Enums\ContentType;
use App\Contexts\Alliance\Content\Enums\ContentVisibility;
use App\Contexts\Alliance\Content\Models\AnnouncementBroadcastRun;
use App\Contexts\Alliance\Content\Models\AnnouncementBroadcastSchedule;
use App\Contexts\Alliance\Content\Models\ContentItem;
use App\Contexts\Alliance\Content\Services\NextBroadcastOccurrence;
use App\Contexts\Alliance\Membership\Enums\MembershipStatus;
use App\Contexts\Alliance\Membership\Models\AllianceMembership;
use App\Contexts\Communications\Delivery\Enums\DeliveryChannel;
use App\Contexts\Communications\Delivery\Enums\DeliveryStatus;
use App\Contexts\Communications\Delivery\Models\NotificationDelivery;
use App\Contexts\Communications\Delivery\Models\NotificationMessage;
use App\Contexts\GameWorld\Players\Actions\ClaimPlayerAccount;
use App\Contexts\GameWorld\Players\Actions\PersistPlayerIdentity;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\v3\Support\ScenarioFactory;
use Tests\v3\TestCase;

final class AllianceAnnouncementBroadcastBehaviorV3Test extends TestCase
{
    use RefreshDatabase;

    public function test_published_announcement_is_delivered_once_to_each_claimed_active_member(): void
    {
        $scenarios = app(ScenarioFactory::class);
        $ownerAccount = $scenarios->account();
        $owner = $scenarios->player($ownerAccount->userId);
        $alliance = $scenarios->alliance($owner);
        $memberAccount = $scenarios->account();
        $unclaimedMember = app(PersistPlayerIdentity::class)->handle(
            $owner->kingdomId,
            'V3 Broadcast Member',
            'v3-broadcast-member',
        );
        $member = app(ClaimPlayerAccount::class)->handle(
            $unclaimedMember->playerId,
            $memberAccount->userId,
        );
        AllianceMembership::query()->create([
            'alliance_id' => $alliance->allianceId,
            'player_id' => $member->playerId,
            'status' => MembershipStatus::Active,
            'joined_at' => now(),
        ]);

        $announcement = ContentItem::query()->create([
            'alliance_id' => $alliance->allianceId,
            'type' => ContentType::Announcement,
            'visibility' => ContentVisibility::Members,
            'status' => ContentStatus::Published,
            'title' => 'Bear rally at reset',
            'slug' => 'bear-rally-at-reset',
            'summary' => 'Open the noticeboard for the rally plan.',
            'body' => 'Use formation one and wait for the rally lead.',
            'locale' => 'en',
            'sort_order' => 0,
            'current_revision_number' => 1,
            'notify_members' => true,
            'published_at' => now(),
            'created_by_player_id' => $owner->playerId,
            'updated_by_player_id' => $owner->playerId,
        ]);

        $queue = app(QueuePublishedAnnouncementBroadcasts::class);
        self::assertSame(1, $queue->handle());
        self::assertSame(0, $queue->handle());

        $messages = NotificationMessage::query()
            ->where('notification_type', 'alliance.announcement')
            ->orderBy('recipient_user_id')
            ->get();
        self::assertCount(2, $messages);
        self::assertSame(
            [$ownerAccount->userId, $memberAccount->userId],
            $messages->pluck('recipient_user_id')->map(static fn (mixed $id): int => (int) $id)->all(),
        );
        self::assertTrue($messages->every(
            static fn (NotificationMessage $message): bool => $message->action_url === '/alliance/content/bear-rally-at-reset',
        ));

        $deliveries = NotificationDelivery::query()
            ->whereIn('notification_message_id', $messages->pluck('id'))
            ->get();
        self::assertCount(2, $deliveries);
        self::assertTrue($deliveries->every(
            static fn (NotificationDelivery $delivery): bool => $delivery->channel === DeliveryChannel::InApp
                && $delivery->status === DeliveryStatus::Sent
                && $delivery->sent_at !== null,
        ));
        self::assertNotNull($announcement->fresh()?->broadcasted_at);
    }

    public function test_opted_out_and_non_announcement_content_are_not_broadcast(): void
    {
        $scenarios = app(ScenarioFactory::class);
        $account = $scenarios->account();
        $owner = $scenarios->player($account->userId);
        $alliance = $scenarios->alliance($owner);

        foreach ([
            [ContentType::Announcement, false, 'quiet-announcement'],
            [ContentType::Guide, true, 'broadcast-guide'],
        ] as [$type, $notifyMembers, $slug]) {
            ContentItem::query()->create([
                'alliance_id' => $alliance->allianceId,
                'type' => $type,
                'visibility' => ContentVisibility::Members,
                'status' => ContentStatus::Published,
                'title' => 'Content without a broadcast',
                'slug' => $slug,
                'body' => 'Reference content.',
                'locale' => 'en',
                'sort_order' => 0,
                'current_revision_number' => 1,
                'notify_members' => $notifyMembers,
                'published_at' => now(),
                'created_by_player_id' => $owner->playerId,
                'updated_by_player_id' => $owner->playerId,
            ]);
        }

        self::assertSame(0, app(QueuePublishedAnnouncementBroadcasts::class)->handle());
        self::assertSame(0, NotificationMessage::query()->count());
        self::assertSame(0, NotificationDelivery::query()->count());
    }

    public function test_next_occurrence_uses_the_rule_timezone_across_daylight_saving_time(): void
    {
        $next = app(NextBroadcastOccurrence::class)->calculate(
            [7],
            '09:00',
            'America/Toronto',
            CarbonImmutable::parse('2026-03-07 15:00:00 UTC'),
        );

        self::assertSame('2026-03-08T13:00:00+00:00', $next?->toIso8601String());
    }

    public function test_recurring_rule_materializes_each_due_run_once_and_advances(): void
    {
        [$owner, $alliance, $announcement] = $this->announcementScenario('recurring-rally');
        $tomorrow = CarbonImmutable::now('UTC')->addDay();
        $scheduleId = app(SaveAnnouncementBroadcastSchedule::class)->handle(
            $alliance,
            $owner,
            (string) $announcement->id,
            [$tomorrow->dayOfWeekIso],
            $tomorrow->format('H:i'),
            'UTC',
        );
        AnnouncementBroadcastSchedule::query()->whereKey($scheduleId)->update([
            'next_run_at' => now()->subMinute(),
        ]);
        $announcement->forceFill(['broadcasted_at' => now()])->save();

        $queue = app(QueuePublishedAnnouncementBroadcasts::class);
        self::assertSame(1, $queue->handle());
        self::assertSame(0, $queue->handle());

        $schedule = AnnouncementBroadcastSchedule::query()->findOrFail($scheduleId);
        self::assertSame(BroadcastScheduleStatus::Active, $schedule->status);
        self::assertNotNull($schedule->last_run_at);
        self::assertTrue($schedule->next_run_at?->isFuture() ?? false);
        self::assertSame(1, AnnouncementBroadcastRun::query()->where('schedule_id', $scheduleId)->count());
    }

    public function test_cancelled_rule_does_not_create_future_runs(): void
    {
        [$owner, $alliance, $announcement] = $this->announcementScenario('cancelled-rally');
        $tomorrow = CarbonImmutable::now('UTC')->addDay();
        $scheduleId = app(SaveAnnouncementBroadcastSchedule::class)->handle(
            $alliance,
            $owner,
            (string) $announcement->id,
            [$tomorrow->dayOfWeekIso],
            $tomorrow->format('H:i'),
            'UTC',
        );

        app(CancelAnnouncementBroadcastSchedule::class)->handle($alliance, $owner, $scheduleId);
        AnnouncementBroadcastSchedule::query()->whereKey($scheduleId)->update([
            'next_run_at' => now()->subMinute(),
        ]);
        $announcement->forceFill(['broadcasted_at' => now()])->save();

        self::assertSame(0, app(QueuePublishedAnnouncementBroadcasts::class)->handle());
        self::assertSame(0, AnnouncementBroadcastRun::query()->count());
    }

    public function test_test_delivery_targets_only_the_requesting_manager(): void
    {
        [$owner, $alliance, $announcement, $ownerUserId] = $this->announcementScenario('test-rally');

        $channels = app(TestAnnouncementBroadcast::class)->handle(
            $alliance,
            $owner,
            (string) $announcement->id,
        );

        self::assertSame([DeliveryChannel::InApp->value], $channels);
        $message = NotificationMessage::query()->sole();
        self::assertSame($ownerUserId, (int) $message->recipient_user_id);
        self::assertTrue((bool) ($message->metadata['test_delivery'] ?? false));
        self::assertStringStartsWith('[Test] ', $message->title);
        $delivery = NotificationDelivery::query()->sole();
        self::assertSame((string) $message->id, (string) $delivery->notification_message_id);
        self::assertSame(DeliveryChannel::InApp, $delivery->channel);
    }

    public function test_failed_run_deliveries_can_be_selected_for_bounded_retry(): void
    {
        [$owner, $alliance, $announcement] = $this->announcementScenario('retry-rally');
        self::assertSame(1, app(QueuePublishedAnnouncementBroadcasts::class)->handle());
        $run = AnnouncementBroadcastRun::query()->sole();
        self::assertSame(BroadcastRunStatus::Queued, $run->status);
        $delivery = NotificationDelivery::query()->sole();
        $delivery->forceFill([
            'status' => DeliveryStatus::Failed,
            'attempt_count' => 1,
            'max_attempts' => 5,
            'failed_at' => now(),
            'last_error' => 'Temporary provider error.',
        ])->save();

        $retried = app(RetryAnnouncementBroadcastFailures::class)->handle(
            $alliance,
            $owner,
            (string) $run->id,
            [(string) $delivery->id],
        );

        self::assertSame(1, $retried);
        self::assertSame(DeliveryStatus::Queued, $delivery->fresh()?->status);
        self::assertNull($delivery->fresh()?->last_error);
    }

    /** @return array{0: string, 1: string, 2: ContentItem, 3: int} */
    private function announcementScenario(string $slug): array
    {
        $scenarios = app(ScenarioFactory::class);
        $account = $scenarios->account();
        $owner = $scenarios->player($account->userId);
        $alliance = $scenarios->alliance($owner);
        $announcement = ContentItem::query()->create([
            'alliance_id' => $alliance->allianceId,
            'type' => ContentType::Announcement,
            'visibility' => ContentVisibility::Members,
            'status' => ContentStatus::Published,
            'title' => 'Rally operations',
            'slug' => $slug,
            'summary' => 'Use the current rally plan.',
            'body' => 'Open the noticeboard for the current plan.',
            'locale' => 'en',
            'sort_order' => 0,
            'current_revision_number' => 1,
            'notify_members' => true,
            'published_at' => now(),
            'created_by_player_id' => $owner->playerId,
            'updated_by_player_id' => $owner->playerId,
        ]);

        return [$owner->playerId, $alliance->allianceId, $announcement, $account->userId];
    }
}
