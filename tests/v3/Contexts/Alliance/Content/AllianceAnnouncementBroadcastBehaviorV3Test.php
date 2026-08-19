<?php

declare(strict_types=1);

namespace Tests\v3\Contexts\Alliance\Content;

use App\Contexts\Alliance\Content\Actions\QueuePublishedAnnouncementBroadcasts;
use App\Contexts\Alliance\Content\Enums\ContentStatus;
use App\Contexts\Alliance\Content\Enums\ContentType;
use App\Contexts\Alliance\Content\Enums\ContentVisibility;
use App\Contexts\Alliance\Content\Models\ContentItem;
use App\Contexts\Alliance\Membership\Enums\MembershipStatus;
use App\Contexts\Alliance\Membership\Models\AllianceMembership;
use App\Contexts\Communications\Delivery\Enums\DeliveryChannel;
use App\Contexts\Communications\Delivery\Enums\DeliveryStatus;
use App\Contexts\Communications\Delivery\Models\NotificationDelivery;
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
        $member = $scenarios->player($memberAccount->userId, $owner->kingdomNumber);
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

        $deliveries = NotificationDelivery::query()
            ->where('notification_type', 'alliance.announcement')
            ->orderBy('recipient_user_id')
            ->get();
        self::assertCount(2, $deliveries);
        self::assertSame(
            [$ownerAccount->userId, $memberAccount->userId],
            $deliveries->pluck('recipient_user_id')->map(static fn (mixed $id): int => (int) $id)->all(),
        );
        self::assertTrue($deliveries->every(
            static fn (NotificationDelivery $delivery): bool => $delivery->channel === DeliveryChannel::InApp->value
                && $delivery->status === DeliveryStatus::Sent
                && $delivery->sent_at !== null,
        ));
        self::assertSame('/alliance/content/bear-rally-at-reset', $deliveries->first()?->metadata['action_url']);
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
        self::assertSame(0, NotificationDelivery::query()->count());
    }
}
