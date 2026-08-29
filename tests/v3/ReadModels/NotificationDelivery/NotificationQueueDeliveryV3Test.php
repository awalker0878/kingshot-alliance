<?php

declare(strict_types=1);

namespace Tests\v3\ReadModels\NotificationDelivery;

use App\Contexts\Accounts\Identity\Models\User;
use App\Contexts\Alliance\Membership\Enums\AllianceRank;
use App\Contexts\Alliance\Membership\Enums\MembershipStatus;
use App\Contexts\Alliance\Membership\Models\AllianceMembership;
use App\Contexts\Communications\Delivery\Actions\ProcessNotificationDeliveries;
use App\Contexts\Communications\Delivery\Actions\SaveNotificationEndpoint;
use App\Contexts\Communications\Delivery\Actions\SetNotificationPreference;
use App\Contexts\Communications\Delivery\Enums\DeliveryChannel;
use App\Contexts\Communications\Delivery\Enums\DeliveryStatus;
use App\Contexts\Communications\Delivery\Models\NotificationDelivery;
use App\Contexts\Communications\Delivery\Models\NotificationEndpoint;
use App\Contexts\GameWorld\Kingdoms\Enums\KingdomAllianceStatus;
use App\Contexts\GameWorld\Kingdoms\Models\KingdomAlliance;
use App\Contexts\Intelligence\Observations\Enums\TrackedKingdomAllianceState;
use App\Contexts\Intelligence\Observations\Models\KingdomAllianceObservation;
use App\Contexts\Intelligence\Observations\Models\TrackedKingdomAlliance;
use App\ReadModels\CommandOverview\Actions\QueueOfficerBriefNotifications;
use App\ReadModels\CommandOverview\Services\OfficerBriefNotificationPublisher;
use App\ReadModels\IntelligenceSignals\Actions\QueueIntelligenceChangeNotifications;
use App\ReadModels\IntelligenceSignals\Services\IntelligenceSignalNotificationPublisher;
use App\ReadModels\NotificationDelivery\Queries\AllianceNotificationRecipientQuery;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use Tests\v3\Support\ScenarioFactory;
use Tests\v3\TestCase;

final class NotificationQueueDeliveryV3Test extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_daily_officer_sweep_uses_recipient_local_date_and_is_idempotent(): void
    {
        $scenario = new ScenarioFactory;
        $account = $scenario->account();
        User::query()->whereKey($account->userId)->update(['timezone' => 'America/New_York']);
        $actor = $scenario->player($account->userId, 78201);
        $alliance = $scenario->alliance($actor);
        $scenario->roster($actor, $alliance);
        $queue = app(QueueOfficerBriefNotifications::class);

        $before = $queue->handle(
            group: QueueOfficerBriefNotifications::GROUP_DAILY,
            asOf: Carbon::parse('2026-08-29T12:59:00Z'),
        );
        self::assertSame(0, $before->factCount);
        self::assertSame(0, NotificationDelivery::query()->count());

        $first = $queue->handle(
            group: QueueOfficerBriefNotifications::GROUP_DAILY,
            asOf: Carbon::parse('2026-08-29T13:00:00Z'),
        );
        $replay = $queue->handle(
            group: QueueOfficerBriefNotifications::GROUP_DAILY,
            asOf: Carbon::parse('2026-08-29T16:00:00Z'),
        );

        self::assertSame(1, $first->authorizedRecipients);
        self::assertSame(1, $first->createdDeliveryCount);
        self::assertSame(0, $replay->createdDeliveryCount);
        self::assertSame(1, $replay->replayedDeliveryCount);
        self::assertSame(1, NotificationDelivery::query()
            ->where('notification_type', OfficerBriefNotificationPublisher::NOTIFICATION_TYPE)
            ->count());
        $metadata = NotificationDelivery::query()->firstOrFail()->metadata;
        self::assertSame(
            'daily:2026-08-29',
            is_array($metadata) ? ($metadata['policyKey'] ?? null) : null,
        );
    }

    public function test_disabled_channels_and_revoked_officer_authority_create_no_delivery(): void
    {
        $scenario = new ScenarioFactory;
        $account = $scenario->account();
        $actor = $scenario->player($account->userId, 78202);
        $alliance = $scenario->alliance($actor);
        $scenario->roster($actor, $alliance);
        app(SetNotificationPreference::class)->handle(
            $account->userId,
            $actor->playerId,
            OfficerBriefNotificationPublisher::NOTIFICATION_TYPE,
            DeliveryChannel::InApp,
            false,
        );
        app(SetNotificationPreference::class)->handle(
            $account->userId,
            $actor->playerId,
            IntelligenceSignalNotificationPublisher::NOTIFICATION_TYPE,
            DeliveryChannel::InApp,
            false,
        );

        $disabled = app(QueueOfficerBriefNotifications::class)->handle(
            group: QueueOfficerBriefNotifications::GROUP_DAILY,
            asOf: Carbon::parse('2026-08-29T10:00:00Z'),
        );
        self::assertSame(1, $disabled->factCount);
        self::assertSame(0, $disabled->deliveryCount);
        self::assertSame(0, app(IntelligenceSignalNotificationPublisher::class)->publish(
            $account->userId,
            $actor->playerId,
            $alliance->allianceId,
            $this->signal('disabled-intelligence'),
        )->count());

        AllianceMembership::query()
            ->where('alliance_id', $alliance->allianceId)
            ->where('player_id', $actor->playerId)
            ->update(['rank' => AllianceRank::R1->value]);
        $revoked = app(QueueOfficerBriefNotifications::class)->handle(
            group: QueueOfficerBriefNotifications::GROUP_DAILY,
            asOf: Carbon::parse('2026-08-30T10:00:00Z'),
        );
        self::assertSame(0, $revoked->authorizedRecipients);
        self::assertSame(0, NotificationDelivery::query()->count());
    }

    public function test_recipient_sweeps_are_cursor_bounded_and_publishers_reject_cross_alliance_scope(): void
    {
        $scenario = new ScenarioFactory;
        $firstAccount = $scenario->account();
        $first = $scenario->player($firstAccount->userId, 78205);
        $firstAlliance = $scenario->alliance($first);
        $secondAccount = $scenario->account();
        $second = $scenario->player($secondAccount->userId, 78205);
        $secondAlliance = $scenario->alliance($second);

        $recipients = app(AllianceNotificationRecipientQuery::class);
        $page = $recipients->officers(1);
        self::assertCount(1, $page->recipients);
        self::assertTrue($page->truncated);
        self::assertNotNull($page->nextCursor);
        $next = $recipients->officers(1, $page->nextCursor);
        self::assertCount(1, $next->recipients);
        self::assertNotSame(
            $page->recipients[0]->membershipId,
            $next->recipients[0]->membershipId,
        );

        $officerRejected = false;
        try {
            app(OfficerBriefNotificationPublisher::class)->publish(
                $firstAccount->userId,
                $first->playerId,
                $secondAlliance->allianceId,
                $this->brief('cross-alliance'),
            );
        } catch (AuthorizationException) {
            $officerRejected = true;
        }
        self::assertTrue($officerRejected, 'Officer Brief delivery must reject a different Alliance scope.');

        $intelligenceRejected = false;
        try {
            app(IntelligenceSignalNotificationPublisher::class)->publish(
                $secondAccount->userId,
                $second->playerId,
                $firstAlliance->allianceId,
                $this->signal('cross-alliance-intelligence'),
            );
        } catch (AuthorizationException) {
            $intelligenceRejected = true;
        }
        self::assertTrue($intelligenceRejected, 'Intelligence delivery must reject a different Alliance scope.');
    }

    public function test_intelligence_sweep_queues_changed_fingerprints_only_for_current_alliance_members(): void
    {
        config()->set('intelligence.change_detection.alliance_power_absolute', 100_000_000);
        config()->set('intelligence.change_detection.alliance_power_percent', 5.0);
        $scenario = new ScenarioFactory;
        $account = $scenario->account();
        $actor = $scenario->player($account->userId, 78203);
        $alliance = $scenario->alliance($actor);
        $tracking = $this->tracking($alliance->allianceId, $actor->kingdomId);
        $this->observation($tracking, $alliance->allianceId, 3_900_000_000, '2026-08-20T12:00:00Z', 'old');
        $this->observation($tracking, $alliance->allianceId, 4_200_000_000, '2026-08-28T12:00:00Z', 'current');
        $queue = app(QueueIntelligenceChangeNotifications::class);

        $first = $queue->handle(asOf: Carbon::parse('2026-08-29T12:00:00Z'));
        $replay = $queue->handle(asOf: Carbon::parse('2026-08-29T12:15:00Z'));
        self::assertSame(1, $first->authorizedRecipients);
        self::assertSame(1, $first->createdDeliveryCount);
        self::assertSame(0, $replay->createdDeliveryCount);

        $this->observation($tracking, $alliance->allianceId, 4_500_000_000, '2026-08-29T12:30:00Z', 'changed');
        $changed = $queue->handle(asOf: Carbon::parse('2026-08-29T12:30:00Z'));
        self::assertSame(1, $changed->createdDeliveryCount);
        self::assertSame(2, NotificationDelivery::query()
            ->where('notification_type', IntelligenceSignalNotificationPublisher::NOTIFICATION_TYPE)
            ->where('recipient_user_id', $account->userId)
            ->count());

        AllianceMembership::query()
            ->where('alliance_id', $alliance->allianceId)
            ->where('player_id', $actor->playerId)
            ->update(['status' => MembershipStatus::Suspended->value]);
        $this->observation($tracking, $alliance->allianceId, 4_800_000_000, '2026-08-29T13:00:00Z', 'revoked');
        $revoked = $queue->handle(asOf: Carbon::parse('2026-08-29T13:00:00Z'));
        self::assertSame(0, $revoked->authorizedRecipients);
        self::assertSame(2, NotificationDelivery::query()->count());
    }

    public function test_officer_and_intelligence_external_failures_retry_to_delivery_receipts(): void
    {
        Carbon::setTestNow('2026-08-29T14:00:00Z');
        $scenario = new ScenarioFactory;
        $account = $scenario->account();
        $actor = $scenario->player($account->userId, 78204);
        $alliance = $scenario->alliance($actor);
        app(SaveNotificationEndpoint::class)->handle(
            $account->userId,
            $actor->playerId,
            DeliveryChannel::Discord,
            'Officer alerts',
            ['webhook_url' => 'https://discord.com/api/webhooks/123456789/secret-token_value'],
        );

        app(OfficerBriefNotificationPublisher::class)->publish(
            $account->userId,
            $actor->playerId,
            $alliance->allianceId,
            $this->brief('retry-brief'),
        );
        app(IntelligenceSignalNotificationPublisher::class)->publish(
            $account->userId,
            $actor->playerId,
            $alliance->allianceId,
            $this->signal('retry-signal'),
        );

        Http::fake(['discord.com/*' => Http::response(['message' => 'rate limited'], 429)]);
        self::assertSame(2, app(ProcessNotificationDeliveries::class)->handle());
        self::assertSame(2, NotificationDelivery::query()
            ->where('channel', DeliveryChannel::Discord->value)
            ->where('status', DeliveryStatus::Failed->value)
            ->whereNotNull('next_attempt_at')
            ->whereNotNull('last_error')
            ->count());
        self::assertNotNull(NotificationEndpoint::query()->firstOrFail()->last_error);

        Carbon::setTestNow('2026-08-29T14:10:00Z');
        Http::fake(['discord.com/*' => Http::response(null, 204)]);
        self::assertSame(2, app(ProcessNotificationDeliveries::class)->handle());
        self::assertSame(2, NotificationDelivery::query()
            ->where('channel', DeliveryChannel::Discord->value)
            ->where('status', DeliveryStatus::Sent->value)
            ->where('attempt_count', 2)
            ->count());
        self::assertNull(NotificationEndpoint::query()->firstOrFail()->last_error);
    }

    public function test_queue_commands_and_scheduler_are_registered_with_bounded_cursors(): void
    {
        $source = (string) file_get_contents(base_path('routes/console.php'));

        self::assertStringContainsString('notifications:queue-officer-briefs {--group=all} {--limit=1000} {--after=} {--cycle}', $source);
        self::assertStringContainsString('notifications:queue-intelligence-changes {--limit=1000} {--after=} {--cycle}', $source);
        self::assertStringContainsString("notifications:queue-officer-briefs --group=daily --limit=1000 --cycle", $source);
        self::assertStringContainsString("notifications:queue-officer-briefs --group=event --limit=1000 --cycle", $source);
        self::assertStringContainsString("notifications:queue-intelligence-changes --limit=1000 --cycle", $source);
        self::assertStringContainsString('->everyFifteenMinutes()', $source);
    }

    private function tracking(string $allianceId, string $kingdomId): TrackedKingdomAlliance
    {
        $reference = KingdomAlliance::query()->create([
            'kingdom_id' => $kingdomId,
            'game_alliance_id' => 'observed-abc',
            'current_name' => 'ABC Alliance',
            'current_tag' => 'ABC',
            'status' => KingdomAllianceStatus::Active,
        ]);

        return TrackedKingdomAlliance::query()->create([
            'alliance_id' => $allianceId,
            'kingdom_alliance_id' => $reference->id,
            'kingdom_id' => $kingdomId,
            'state' => TrackedKingdomAllianceState::Active,
        ]);
    }

    private function observation(
        TrackedKingdomAlliance $tracking,
        string $allianceId,
        int $power,
        string $capturedAt,
        string $key,
    ): void {
        KingdomAllianceObservation::query()->create([
            'alliance_id' => $allianceId,
            'tracked_kingdom_alliance_id' => $tracking->id,
            'kingdom_alliance_id' => $tracking->kingdom_alliance_id,
            'observed_name' => 'ABC Alliance',
            'observed_tag' => 'ABC',
            'power' => $power,
            'member_count' => 90,
            'captured_at' => Carbon::parse($capturedAt),
            'source' => 'manual',
            'idempotency_key' => hash('sha256', $key),
        ]);
    }

    /** @return array<string,mixed> */
    private function brief(string $fingerprint): array
    {
        return [
            'group' => 'daily_officer',
            'fingerprint' => hash('sha256', $fingerprint),
            'canonicalUrl' => '/',
            'count' => 1,
            'state' => 'needs_attention',
            'owner' => 'read_models.alliance_command',
            'facts' => [['code' => 'test']],
        ];
    }

    /** @return array<string,mixed> */
    private function signal(string $fingerprint): array
    {
        return [
            'type' => 'observation_change',
            'subjectType' => 'tracked_alliance',
            'subjectId' => 'tracked-abc',
            'summary' => 'ABC Alliance power changed between accepted observations.',
            'observedAt' => '2026-08-29T13:00:00Z',
            'sourceClassification' => 'observation',
            'sourceOwner' => 'Intelligence/Observations',
            'canonicalUrl' => '/alliance/kingdom-alliances/intelligence',
            'fingerprint' => hash('sha256', $fingerprint),
            'ruleVersion' => '1',
        ];
    }
}
