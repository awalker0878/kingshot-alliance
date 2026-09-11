<?php

declare(strict_types=1);

namespace Tests\Workflows\NotificationDelivery\Feature;

use App\Contexts\Accounts\Identity\Models\User;
use App\Contexts\Accounts\Security\Services\SecurityNotificationService;
use App\Contexts\Alliance\Content\Actions\PublishContentItem;
use App\Contexts\Alliance\Content\Actions\QueueAnnouncementBroadcastRun;
use App\Contexts\Alliance\Content\Actions\SaveContentItem;
use App\Contexts\Alliance\Content\Actions\TestAnnouncementBroadcast;
use App\Contexts\Alliance\Content\Enums\ContentStatus;
use App\Contexts\Alliance\Content\Enums\ContentType;
use App\Contexts\Alliance\Content\Enums\ContentVisibility;
use App\Contexts\Alliance\Content\Models\ContentItem;
use App\Contexts\Alliance\Membership\Enums\AllianceRank;
use App\Contexts\Alliance\Membership\Enums\MembershipStatus;
use App\Contexts\Alliance\Membership\Models\AllianceMembership;
use App\Contexts\Communications\Delivery\Actions\ProcessNotificationDeliveries;
use App\Contexts\Communications\Delivery\Actions\QueueNotificationEndpointTest;
use App\Contexts\Communications\Delivery\Contracts\NotificationSourceAuthorization;
use App\Contexts\Communications\Delivery\Enums\DeliveryChannel;
use App\Contexts\Communications\Delivery\Enums\DeliveryStatus;
use App\Contexts\Communications\Delivery\Models\NotificationDelivery;
use App\Contexts\Communications\Delivery\Models\NotificationEndpoint;
use App\Contexts\Communications\Delivery\Models\NotificationMessage;
use App\Contexts\Communications\Delivery\Services\NotificationDeliveryService;
use App\Contexts\Communications\Delivery\ValueObjects\NotificationIntent;
use App\Contexts\Communications\Delivery\ValueObjects\NotificationSource;
use App\Contexts\GameWorld\GiftCodes\Enums\GiftCodeStatus;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCode;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeSourceRegistry;
use App\Contexts\GameWorld\Governance\Models\KingdomRoleAssignment;
use App\Contexts\GameWorld\Players\Models\Player;
use App\Contexts\Operations\Events\Enums\EventOccurrenceStatus;
use App\Contexts\Operations\Events\Enums\EventStatus;
use App\Contexts\Operations\Events\Models\Event;
use App\Contexts\Operations\Events\Models\EventOccurrence;
use App\Contexts\Operations\KingPerks\Enums\KingPerkAppointmentStatus;
use App\Contexts\Operations\KingPerks\Enums\KingPerkPlanStatus;
use App\Contexts\Operations\KingPerks\Enums\KingPerkReminderKind;
use App\Contexts\Operations\KingPerks\Enums\KingSkill;
use App\Contexts\Operations\KingPerks\Enums\KingSkillStatus;
use App\Contexts\Operations\KingPerks\Models\KingPerkAppointment;
use App\Contexts\Operations\KingPerks\Models\KingPerkPlan;
use App\Contexts\Operations\KingPerks\Models\KingSkillPlan;
use App\Contexts\Operations\KingPerks\Services\KingPerkScheduler;
use App\Contexts\Operations\Participation\Enums\EventResponseChoice;
use App\Contexts\Operations\Participation\Models\EventResponse;
use App\Contexts\Operations\Participation\Reminders\Enums\EventReminderAudience;
use App\Contexts\Operations\Participation\Reminders\Enums\EventReminderTrigger;
use App\Contexts\Operations\Participation\Reminders\Models\EventReminderRule;
use App\Contexts\Operations\Polls\Enums\EventPollStatus;
use App\Contexts\Operations\Polls\Models\EventPoll;
use App\Contexts\Platform\Administration\Models\PlatformAdministrator;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Contexts\Operations\KingPerks\Support\KingPerkReminderSourceFixture;
use Tests\Contexts\Operations\Participation\Support\EventReminderSourceFixture;
use Tests\Support\ScenarioFactory;
use Tests\TestCase;

final class NotificationSourceEligibilityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-09-10T09:15:00Z');
        Http::preventStrayRequests();
        Http::fake(['discord.com/*' => Http::response(null, 204)]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_account_security_does_not_need_a_governor_or_alliance_but_rejects_anonymized_accounts(): void
    {
        $account = (new ScenarioFactory)->account();
        $this->endpoint($account->userId);
        app(SecurityNotificationService::class)->publish($account->userId, 'password.changed', 'Password changed', 'Account security fact.', 'security-fixture');
        $message = NotificationMessage::query()->sole();
        self::assertNull($message->player_id);
        self::assertTrue($this->allows($message->source()));
        self::assertSame(1, app(ProcessNotificationDeliveries::class)->handle());
        Http::assertSentCount(1);
        User::query()->whereKey($account->userId)->update(['anonymized_at' => now()]);
        self::assertFalse($this->allows($message->source()));
    }

    public function test_unknown_types_and_malformed_source_descriptors_fail_closed(): void
    {
        $factory = new ScenarioFactory;
        $account = $factory->account();
        $player = $factory->player($account->userId);
        foreach ([
            new NotificationSource('not.registered', $account->userId, null, null, null, []),
            new NotificationSource('account.security', $account->userId, null, 'account_security_event', 'password.changed', []),
            new NotificationSource('officer.brief', $account->userId, $player->playerId, 'officer_brief', 'brief', ['alliance_id' => []]),
            new NotificationSource('intelligence.change', $account->userId, $player->playerId, 'tracked_alliance', 'subject', ['alliance_id' => str_repeat('x', 129)]),
            new NotificationSource('account.security', $account->userId + 9999, null, 'account_security_event', 'event', ['event' => 'event']),
        ] as $source) {
            self::assertFalse($this->allows($source));
        }
    }

    public function test_unknown_persisted_type_is_cancelled_without_provider_io(): void
    {
        $account = (new ScenarioFactory)->account();
        $this->endpoint($account->userId);
        $receipt = app(NotificationDeliveryService::class)->queue(NotificationIntent::fromScalars(
            notificationType: 'not.registered', recipientUserId: $account->userId, playerId: null,
            availableAt: now(), idempotencyKey: 'unregistered-source', title: 'Must not be delivered',
        ));
        self::assertSame(0, app(ProcessNotificationDeliveries::class)->handle());
        self::assertSame(DeliveryStatus::Cancelled, NotificationDelivery::query()->where('notification_message_id', $receipt->messageId)
            ->where('channel', DeliveryChannel::Discord)->sole()->status);
        Http::assertNothingSent();
    }

    public function test_endpoint_test_requires_the_original_governor_and_current_endpoint(): void
    {
        $factory = new ScenarioFactory;
        $account = $factory->account();
        $player = $factory->player($account->userId);
        $endpoint = $this->endpoint($account->userId, $player->playerId);
        $id = app(QueueNotificationEndpointTest::class)->handle($account->userId, $player->playerId, (string) $endpoint->id);
        $delivery = NotificationDelivery::query()->findOrFail($id);
        $source = NotificationMessage::query()->findOrFail($delivery->notification_message_id)->source();
        self::assertTrue($this->allows($source));
        $endpoint->forceFill(['enabled' => false])->save();
        self::assertFalse($this->allows($source));
        $endpoint->forceFill(['enabled' => true])->save();
        Player::query()->whereKey($player->playerId)->update(['user_id' => $factory->account()->userId]);
        self::assertFalse($this->allows($source));
    }

    public static function announcementRevocations(): iterable
    {
        yield 'membership revoked' => ['membership'];
        yield 'published source archived' => ['archived'];
        yield 'different source owner' => ['foreign_owner'];
        yield 'source changed to draft' => ['draft'];
    }

    #[DataProvider('announcementRevocations')]
    public function test_member_announcements_recheck_current_source_access(string $change): void
    {
        $factory = new ScenarioFactory;
        $owner = $factory->player($factory->account()->userId, 786001);
        $alliance = $factory->alliance($owner);
        $recipient = $factory->player($factory->account()->userId, 786001);
        AllianceMembership::query()->create(['alliance_id' => $alliance->allianceId, 'player_id' => $recipient->playerId,
            'rank' => AllianceRank::R1, 'status' => MembershipStatus::Active, 'joined_at' => now()]);
        $contentId = $this->announcement($alliance->allianceId, $owner->playerId);
        app(PublishContentItem::class)->handle($alliance->allianceId, $owner->playerId, $contentId);
        app(QueueAnnouncementBroadcastRun::class)->handle($alliance->allianceId, $contentId, CarbonImmutable::now(), 'announcement-source-fixture');
        $message = NotificationMessage::query()->where('recipient_user_id', $recipient->userId)->sole();
        self::assertTrue($this->allows($message->source()));
        match ($change) {
            'membership' => AllianceMembership::query()->where('player_id', $recipient->playerId)->update(['status' => MembershipStatus::Suspended->value]),
            'archived' => ContentItem::query()->whereKey($contentId)->update(['status' => ContentStatus::Archived->value, 'archived_at' => now()]),
            'foreign_owner' => $message->forceFill(['metadata' => [...$message->metadata, 'alliance_id' => $factory->alliance($factory->player($factory->account()->userId))->allianceId]])->save(),
            'draft' => ContentItem::query()->whereKey($contentId)->update(['status' => ContentStatus::Draft->value]),
        };
        self::assertFalse($this->allows($message->source()));
    }

    public function test_draft_announcement_preview_keeps_current_manager_authority(): void
    {
        $factory = new ScenarioFactory;
        $player = $factory->player($factory->account()->userId);
        $alliance = $factory->alliance($player);
        $contentId = $this->announcement($alliance->allianceId, $player->playerId);
        app(TestAnnouncementBroadcast::class)->handle($alliance->allianceId, $player->playerId, $contentId);
        $source = NotificationMessage::query()->sole()->source();
        self::assertTrue($this->allows($source));
        AllianceMembership::query()->where('player_id', $player->playerId)->update(['rank' => AllianceRank::R1->value]);
        self::assertFalse($this->allows($source));
    }

    public static function eventChanges(): iterable
    {
        yield 'rule disabled' => ['rule'];
        yield 'occurrence cancelled' => ['occurrence'];
        yield 'event cancelled' => ['event'];
        yield 'recipient no longer responded' => ['response'];
        yield 'different source rule' => ['foreign_rule'];
    }

    #[DataProvider('eventChanges')]
    public function test_event_reminders_keep_current_source_and_audience(string $change): void
    {
        $factory = new ScenarioFactory;
        $player = $factory->player($factory->account()->userId);
        $data = (new EventReminderSourceFixture)->forPlayer($player->playerId);
        $source = new NotificationSource('event.reminder', $player->userId, $player->playerId, 'event_occurrence', $data['occurrence'], ['event_id' => $data['event'], 'rule_id' => $data['rule']]);
        if ($change === 'response') {
            EventReminderRule::query()->whereKey($data['rule'])->update(['audience' => EventReminderAudience::Responded->value]);
            EventResponse::query()->create(['occurrence_id' => $data['occurrence'], 'player_id' => $player->playerId, 'response' => EventResponseChoice::Going, 'responded_by_player_id' => $player->playerId, 'responded_at' => now()]);
        }
        self::assertTrue($this->allows($source));
        match ($change) {
            'rule' => EventReminderRule::query()->whereKey($data['rule'])->update(['is_enabled' => false]),
            'occurrence' => EventOccurrence::query()->whereKey($data['occurrence'])->update(['status' => EventOccurrenceStatus::Cancelled->value]),
            'event' => Event::query()->whereKey($data['event'])->update(['status' => EventStatus::Cancelled->value]),
            'response' => EventResponse::query()->where('occurrence_id', $data['occurrence'])->update(['response' => EventResponseChoice::Unavailable->value]),
            'foreign_rule' => EventReminderRule::query()->whereKey($data['rule'])->update(['minutes_before' => 30, 'event_id' => (new EventReminderSourceFixture)->forPlayer($factory->player($factory->account()->userId)->playerId)['event']]),
        };
        self::assertFalse($this->allows($source));
    }

    public static function pollChanges(): iterable
    {
        yield 'closed' => ['closed'];
        yield 'at closing instant' => ['expired'];
        yield 'different occurrence' => ['foreign'];
        yield 'deleted' => ['deleted'];
    }

    #[DataProvider('pollChanges')]
    public function test_poll_close_reminders_require_the_current_open_scoped_poll(string $change): void
    {
        $factory = new ScenarioFactory;
        $player = $factory->player($factory->account()->userId);
        $data = (new EventReminderSourceFixture)->forPlayer($player->playerId);
        $poll = EventPoll::query()->create([
            'occurrence_id' => $data['occurrence'], 'key' => 'source-poll', 'question' => 'Choose a time',
            'status' => EventPollStatus::Open, 'created_by_player_id' => $player->playerId,
            'opens_at' => now()->subHour(), 'closes_at' => now()->addHour(),
        ]);
        EventReminderRule::query()->whereKey($data['rule'])->update(['trigger_type' => EventReminderTrigger::BeforePollClose->value, 'poll_id' => $poll->id]);
        $source = new NotificationSource('event.reminder', $player->userId, $player->playerId, 'event_occurrence', $data['occurrence'], ['event_id' => $data['event'], 'rule_id' => $data['rule']]);
        self::assertTrue($this->allows($source));
        match ($change) {
            'closed' => $poll->forceFill(['status' => EventPollStatus::Closed])->save(),
            'expired' => $poll->forceFill(['closes_at' => now()])->save(),
            'foreign' => $poll->forceFill(['occurrence_id' => (new EventReminderSourceFixture)->forPlayer($factory->player($factory->account()->userId)->playerId)['occurrence']])->save(),
            'deleted' => $poll->delete(),
        };
        self::assertFalse($this->allows($source));
    }

    public static function appointmentKinds(): iterable
    {
        foreach ([KingPerkReminderKind::Appointment24Hours, KingPerkReminderKind::Appointment1Hour, KingPerkReminderKind::Appointment10Minutes] as $kind) {
            yield $kind->value => [$kind];
        }
    }

    #[DataProvider('appointmentKinds')]
    public function test_appointment_reminders_recheck_the_current_assignee(KingPerkReminderKind $kind): void
    {
        $factory = new ScenarioFactory;
        $player = $factory->player($factory->account()->userId);
        $data = (new KingPerkReminderSourceFixture)->forPlayer($player);
        $source = new NotificationSource('king_perks.reminder', $player->userId, $player->playerId, 'king_perk_appointment', $data['appointment'], ['plan_id' => $data['plan'], 'kind' => $kind->value]);
        self::assertTrue($this->allows($source));
        $other = $factory->player($factory->account()->userId, $player->kingdomNumber);
        KingPerkAppointment::query()->whereKey($data['appointment'])->update(['assigned_player_id' => $other->playerId]);
        self::assertFalse($this->allows($source));
    }

    public function test_unconfirmed_appointment_notice_needs_current_manager_and_unconfirmed_source(): void
    {
        $factory = new ScenarioFactory;
        $player = $factory->player($factory->account()->userId);
        $data = (new KingPerkReminderSourceFixture)->forPlayer($player);
        $source = new NotificationSource('king_perks.reminder', $player->userId, $player->playerId, 'king_perk_appointment', $data['appointment'], ['plan_id' => $data['plan'], 'kind' => KingPerkReminderKind::AppointmentUnconfirmed10Minutes->value]);
        self::assertTrue($this->allows($source));
        KingPerkAppointment::query()->whereKey($data['appointment'])->update(['status' => KingPerkAppointmentStatus::Confirmed->value]);
        self::assertFalse($this->allows($source));
        KingPerkAppointment::query()->whereKey($data['appointment'])->update(['status' => KingPerkAppointmentStatus::Scheduled->value]);
        KingdomRoleAssignment::query()->where('player_id', $player->playerId)->update(['revoked_at' => now()]);
        self::assertFalse($this->allows($source));
    }

    public static function skillKinds(): iterable
    {
        yield 'scheduling available' => [KingPerkReminderKind::SkillSchedulingAvailable];
        yield 'one hour' => [KingPerkReminderKind::Skill1Hour];
    }

    #[DataProvider('skillKinds')]
    public function test_skill_reminders_recheck_current_manager_and_source_state(KingPerkReminderKind $kind): void
    {
        $factory = new ScenarioFactory;
        $player = $factory->player($factory->account()->userId);
        $data = (new KingPerkReminderSourceFixture)->forPlayer($player);
        app(KingPerkScheduler::class)->planSkill($player->playerId, $data['plan'], KingSkill::Groundworks, CarbonImmutable::now()->addHours(2), 60);
        $skill = KingSkillPlan::query()->where('plan_id', $data['plan'])->sole();
        $source = new NotificationSource('king_perks.reminder', $player->userId, $player->playerId, 'king_skill_plan', (string) $skill->id, ['plan_id' => $data['plan'], 'kind' => $kind->value]);
        self::assertTrue($this->allows($source));
        $skill->forceFill(['status' => KingSkillStatus::Skipped])->save();
        self::assertFalse($this->allows($source));
        $skill->forceFill(['status' => KingSkillStatus::Planned])->save();
        KingdomRoleAssignment::query()->where('player_id', $player->playerId)->update(['revoked_at' => now()]);
        self::assertFalse($this->allows($source));
    }

    public function test_closed_kingdom_plan_cannot_authorize_old_appointment_content(): void
    {
        $factory = new ScenarioFactory;
        $player = $factory->player($factory->account()->userId);
        $data = (new KingPerkReminderSourceFixture)->forPlayer($player);
        $source = new NotificationSource('king_perks.reminder', $player->userId, $player->playerId, 'king_perk_appointment', $data['appointment'], ['plan_id' => $data['plan'], 'kind' => KingPerkReminderKind::Appointment1Hour->value]);
        self::assertTrue($this->allows($source));
        KingPerkPlan::query()->whereKey($data['plan'])->update(['status' => KingPerkPlanStatus::Closed->value]);
        self::assertFalse($this->allows($source));
    }

    public static function giftTypes(): iterable
    {
        foreach (['gift_code.expiring', 'gift_code.available', 'gift_code.trust_changed', 'gift_code.reminder', 'gift_code.redemption_ready'] as $type) {
            yield $type => [$type];
        }
    }

    #[DataProvider('giftTypes')]
    public function test_gift_notifications_need_no_alliance_but_reject_lost_governor_ownership(string $type): void
    {
        $factory = new ScenarioFactory;
        $player = $factory->player($factory->account()->userId);
        $code = $this->gift();
        $source = new NotificationSource($type, $player->userId, $type === 'gift_code.expiring' ? $player->playerId : null,
            $type === 'gift_code.redemption_ready' ? 'gift_code_workspace' : 'gift_code',
            $type === 'gift_code.redemption_ready' ? (string) $player->userId : (string) $code->id,
            ['governors' => [['id' => $player->playerId, 'name' => $player->currentName, 'kingdom' => $player->kingdomNumber]]]);
        self::assertTrue($this->allows($source));
        Player::query()->whereKey($player->playerId)->update(['user_id' => $factory->account()->userId]);
        self::assertFalse($this->allows($source));
    }

    public function test_gift_summary_cannot_include_a_foreign_governor_even_with_one_owned_governor(): void
    {
        $factory = new ScenarioFactory;
        $owner = $factory->player($factory->account()->userId);
        $other = $factory->player($factory->account()->userId);
        $source = new NotificationSource('gift_code.available', $owner->userId, null, 'gift_code', (string) $this->gift()->id,
            ['governors' => [['id' => $owner->playerId], ['id' => $other->playerId]]]);
        self::assertFalse($this->allows($source));
    }

    public function test_operational_source_alerts_require_current_platform_grants_and_source(): void
    {
        $account = (new ScenarioFactory)->account();
        $grant = PlatformAdministrator::query()->create(['user_id' => $account->userId, 'granted_at' => now()]);
        $registry = GiftCodeSourceRegistry::query()->create([
            'source_key' => 'authorization-fixture', 'name' => 'Authorization fixture', 'classification' => 'official',
            'canonical_domain' => 'example.test', 'verification_method' => 'fixture', 'is_active' => true,
        ]);
        $source = new NotificationSource('gift_code.source_alert', $account->userId, null, 'gift_code_source', (string) $registry->id, ['source_id' => (string) $registry->id]);
        self::assertTrue($this->allows($source));
        $grant->forceFill(['revoked_at' => now()])->save();
        self::assertFalse($this->allows($source));
        $grant->forceFill(['revoked_at' => null])->save();
        $registry->delete();
        self::assertFalse($this->allows($source));
    }

    private function allows(NotificationSource $source): bool
    {
        return app(NotificationSourceAuthorization::class)->allows($source);
    }

    private function endpoint(int $userId, ?string $playerId = null): NotificationEndpoint
    {
        return NotificationEndpoint::query()->create([
            'recipient_user_id' => $userId, 'player_id' => $playerId, 'channel' => DeliveryChannel::Discord,
            'label' => 'Source contract destination', 'enabled' => true,
            'configuration' => ['webhook_url' => 'https://discord.com/api/webhooks/123456789/secret-token_value'],
        ]);
    }

    private function gift(): GiftCode
    {
        return GiftCode::query()->create(['code' => 'SOURCE26', 'normalized_code' => 'SOURCE26', 'status' => GiftCodeStatus::Valid, 'discovered_at' => now(), 'expires_at' => now()->addDay()]);
    }

    private function announcement(string $allianceId, string $playerId): string
    {
        return app(SaveContentItem::class)->handle($allianceId, $playerId, [
            'type' => ContentType::Announcement, 'visibility' => ContentVisibility::Members,
            'title' => 'Private member announcement', 'slug' => 'source-authorization',
            'body' => 'Members only fixture.', 'locale' => 'en',
        ]);
    }
}
