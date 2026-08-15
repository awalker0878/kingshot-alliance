<?php

declare(strict_types=1);

namespace Tests\Feature\Events;

use App\Contexts\Accounts\Models\User;
use App\Contexts\Alliance\Core\Actions\CreateAlliance;
use App\Contexts\Alliance\Membership\Actions\AcceptInvitation;
use App\Contexts\Alliance\Membership\Actions\CreateInvitation;
use App\Contexts\GameWorld\Models\Kingdom;
use App\Contexts\GameWorld\Models\Player;
use App\Contexts\GameWorld\Services\PlayerContext;
use App\Domain\Authorization\Actions\BootstrapKingdomAdministrator;
use App\Domain\Events\Actions\CancelEventRegistration;
use App\Domain\Events\Actions\CreateEvent;
use App\Domain\Events\Actions\RecordEventAttendance;
use App\Domain\Events\Actions\RegisterForEvent;
use App\Domain\Events\Actions\RespondToEvent;
use App\Domain\Events\Actions\UpdateEvent;
use App\Domain\Events\Enums\EventAttendanceStatus;
use App\Domain\Events\Enums\EventOccurrenceStatus;
use App\Domain\Events\Enums\EventRegistrationStatus;
use App\Domain\Events\Enums\EventReminderAudience;
use App\Domain\Events\Enums\EventResponseChoice;
use App\Domain\Events\Enums\EventScope;
use App\Domain\Events\Models\EventAttendance;
use App\Domain\Events\Models\EventRegistration;
use App\Domain\Events\Models\EventResponse;
use App\Domain\Events\Models\EventType;
use App\Domain\Events\Queries\EventAttentionQuery;
use App\Domain\Events\Services\EventTypeRegistry;
use App\Domain\Kingdoms\Actions\SaveRosterEntry;
use App\Domain\Notifications\Actions\CreateEventReminderRule;
use App\Domain\Notifications\Actions\DisableEventReminderRule;
use App\Domain\Notifications\Actions\MarkEventReminderSent;
use App\Domain\Notifications\Actions\QueueDueEventReminders;
use App\Domain\Notifications\Models\EventReminderDelivery;
use App\Domain\Notifications\Queries\EventReminderInboxQuery;
use App\Shared\Messaging\Events\OutboxPublished;
use App\Shared\Messaging\Models\OutboxMessage;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class EventParticipationTest extends TestCase
{
    use RefreshDatabase;

    public function test_response_is_upserted_without_changing_registration_or_attendance(): void
    {
        $user = User::factory()->create();
        $kingdom = Kingdom::query()->create(['number' => 8101, 'status' => 'active']);
        $player = Player::query()->create([
            'user_id' => $user->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => 'response-player',
            'current_name' => 'Response Player',
        ]);
        $alliance = $this->app->make(CreateAlliance::class)->handle($player, 'Response', 'response');
        $this->app->make(SaveRosterEntry::class)->handle($alliance, $player, [
            'name' => 'Response Player',
            'game_player_id' => 'response-player',
        ]);
        $type = EventType::query()->where('slug', 'custom')->sole();
        $configuration = $this->app->make(EventTypeRegistry::class)->scope($type, EventScope::Alliance);
        $event = $this->app->make(CreateEvent::class)->handle(
            actor: $player,
            configuration: $configuration,
            target: $alliance,
            firstLocalStart: CarbonImmutable::now('UTC')->addDay(),
            durationMinutes: 60,
            capacity: 20,
        );
        $occurrence = $event->occurrences->firstOrFail();

        $respond = $this->app->make(RespondToEvent::class);
        $respond->handle($player, $occurrence, $player, EventResponseChoice::Going);
        $respond->handle($player, $occurrence, $player, EventResponseChoice::Maybe);

        self::assertSame(1, EventResponse::query()->where('occurrence_id', $occurrence->id)->where('player_id', $player->id)->count());
        self::assertSame(EventResponseChoice::Maybe, EventResponse::query()->sole()->response);
        self::assertSame(0, EventRegistration::query()->count());
        self::assertSame(0, EventAttendance::query()->count());
    }

    public function test_rescheduling_preserves_old_occurrence_participation_history(): void
    {
        $user = User::factory()->create();
        $kingdom = Kingdom::query()->create(['number' => 8110, 'status' => 'active']);
        $player = Player::query()->create([
            'user_id' => $user->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => 'reschedule-player',
            'current_name' => 'Reschedule Player',
        ]);
        $alliance = $this->app->make(CreateAlliance::class)->handle($player, 'Reschedule', 'reschedule');
        $this->app->make(SaveRosterEntry::class)->handle($alliance, $player, [
            'name' => 'Reschedule Player',
            'game_player_id' => 'reschedule-player',
        ]);
        $type = EventType::query()->where('slug', 'custom')->sole();
        $configuration = $this->app->make(EventTypeRegistry::class)->scope($type, EventScope::Alliance);
        $event = $this->app->make(CreateEvent::class)->handle(
            actor: $player,
            configuration: $configuration,
            target: $alliance,
            firstLocalStart: CarbonImmutable::now('UTC')->addDay(),
            durationMinutes: 60,
            capacity: 20,
        );
        $oldOccurrence = $event->occurrences->firstOrFail();
        $this->app->make(RespondToEvent::class)->handle($player, $oldOccurrence, $player, EventResponseChoice::Going);

        $updated = $this->app->make(UpdateEvent::class)->handle(
            actor: $player,
            event: $event,
            firstLocalStart: CarbonImmutable::now('UTC')->addDays(2),
        );

        self::assertSame(EventOccurrenceStatus::Cancelled, $oldOccurrence->refresh()->status);
        self::assertTrue(EventResponse::query()->where('occurrence_id', $oldOccurrence->id)->where('player_id', $player->id)->exists());
        $scheduled = $updated->occurrences->where('status', EventOccurrenceStatus::Scheduled)->values();
        self::assertCount(1, $scheduled);
        self::assertNotSame((string) $oldOccurrence->id, (string) $scheduled->first()->id);
    }

    public function test_capacity_waitlist_and_cancellation_promotion_are_atomic_facts(): void
    {
        $owner = User::factory()->create();
        $firstUser = User::factory()->create(['email' => 'seat-one@example.com']);
        $secondUser = User::factory()->create(['email' => 'seat-two@example.com']);
        $kingdom = Kingdom::query()->create(['number' => 8102, 'status' => 'active']);
        $ownerPlayer = Player::query()->create([
            'user_id' => $owner->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => 'capacity-r5',
            'current_name' => 'Capacity R5',
        ]);
        $firstPlayer = Player::query()->create([
            'user_id' => $firstUser->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => 'seat-one',
            'current_name' => 'Seat One',
        ]);
        $secondPlayer = Player::query()->create([
            'user_id' => $secondUser->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => 'seat-two',
            'current_name' => 'Seat Two',
        ]);
        $alliance = $this->app->make(CreateAlliance::class)->handle($ownerPlayer, 'Capacity', 'capacity');
        $saveRoster = $this->app->make(SaveRosterEntry::class);
        $saveRoster->handle($alliance, $ownerPlayer, ['name' => 'Capacity R5', 'game_player_id' => 'capacity-r5']);
        $saveRoster->handle($alliance, $ownerPlayer, ['name' => 'Seat One', 'game_player_id' => 'seat-one']);
        $saveRoster->handle($alliance, $ownerPlayer, ['name' => 'Seat Two', 'game_player_id' => 'seat-two']);
        $invite = $this->app->make(CreateInvitation::class);
        $accept = $this->app->make(AcceptInvitation::class);
        $firstInvitation = $invite->handle($alliance, $ownerPlayer, $firstPlayer, (string) $firstUser->email);
        $accept->handle($firstUser, $firstInvitation->token);
        $secondInvitation = $invite->handle($alliance, $ownerPlayer, $secondPlayer, (string) $secondUser->email);
        $accept->handle($secondUser, $secondInvitation->token);
        $type = EventType::query()->where('slug', 'custom')->sole();
        $configuration = $this->app->make(EventTypeRegistry::class)->scope($type, EventScope::Alliance);
        $event = $this->app->make(CreateEvent::class)->handle(
            actor: $ownerPlayer,
            configuration: $configuration,
            target: $alliance,
            firstLocalStart: CarbonImmutable::now('UTC')->addDay(),
            durationMinutes: 60,
            capacity: 1,
        );
        $occurrence = $event->occurrences->firstOrFail();
        $register = $this->app->make(RegisterForEvent::class);

        self::assertSame(EventRegistrationStatus::Registered, $register->handle($firstPlayer, $occurrence, $firstPlayer)->status);
        $waitlisted = $register->handle($secondPlayer, $occurrence, $secondPlayer);
        self::assertSame(EventRegistrationStatus::Waitlisted, $waitlisted->status);
        self::assertSame(1, $waitlisted->waitlist_position);

        $this->app->make(CancelEventRegistration::class)->handle($firstPlayer, $occurrence, $firstPlayer);

        self::assertSame(EventRegistrationStatus::Cancelled, EventRegistration::query()->where('player_id', $firstPlayer->id)->sole()->status);
        $promoted = EventRegistration::query()->where('player_id', $secondPlayer->id)->sole();
        self::assertSame(EventRegistrationStatus::Registered, $promoted->status);
        self::assertNull($promoted->waitlist_position);
    }

    public function test_manager_records_attendance_without_mutating_registration(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create(['email' => 'attendance@example.com']);
        $kingdom = Kingdom::query()->create(['number' => 8103, 'status' => 'active']);
        $ownerPlayer = Player::query()->create([
            'user_id' => $owner->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => 'attendance-r5',
            'current_name' => 'Attendance R5',
        ]);
        $memberPlayer = Player::query()->create([
            'user_id' => $member->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => 'attendance-player',
            'current_name' => 'Attendance Player',
        ]);
        $alliance = $this->app->make(CreateAlliance::class)->handle($ownerPlayer, 'Attendance', 'attendance');
        $saveRoster = $this->app->make(SaveRosterEntry::class);
        $saveRoster->handle($alliance, $ownerPlayer, ['name' => 'Attendance R5', 'game_player_id' => 'attendance-r5']);
        $saveRoster->handle($alliance, $ownerPlayer, ['name' => 'Attendance Player', 'game_player_id' => 'attendance-player']);
        $issued = $this->app->make(CreateInvitation::class)->handle($alliance, $ownerPlayer, $memberPlayer, (string) $member->email);
        $this->app->make(AcceptInvitation::class)->handle($member, $issued->token);
        $type = EventType::query()->where('slug', 'custom')->sole();
        $configuration = $this->app->make(EventTypeRegistry::class)->scope($type, EventScope::Alliance);
        $event = $this->app->make(CreateEvent::class)->handle(
            actor: $ownerPlayer,
            configuration: $configuration,
            target: $alliance,
            firstLocalStart: CarbonImmutable::now('UTC')->addDay(),
            durationMinutes: 60,
            capacity: 10,
        );
        $occurrence = $event->occurrences->firstOrFail();
        $registration = $this->app->make(RegisterForEvent::class)->handle($memberPlayer, $occurrence, $memberPlayer);

        $attendance = $this->app->make(RecordEventAttendance::class)->handle(
            $ownerPlayer,
            $occurrence,
            $memberPlayer,
            EventAttendanceStatus::Present,
        );

        self::assertSame(EventAttendanceStatus::Present, $attendance->status);
        self::assertSame(EventRegistrationStatus::Registered, $registration->refresh()->status);
    }

    public function test_reminder_rules_are_idempotent_and_can_be_disabled_and_reenabled(): void
    {
        $owner = User::factory()->create();
        $kingdom = Kingdom::query()->create(['number' => 8108, 'status' => 'active']);
        $player = Player::query()->create([
            'user_id' => $owner->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => 'reminder-rule-player',
            'current_name' => 'Reminder Rule Player',
        ]);
        $alliance = $this->app->make(CreateAlliance::class)->handle($player, 'Reminder Rules', 'reminder-rules');
        $this->app->make(SaveRosterEntry::class)->handle($alliance, $player, [
            'name' => 'Reminder Rule Player',
            'game_player_id' => 'reminder-rule-player',
        ]);
        $type = EventType::query()->where('slug', 'custom')->sole();
        $configuration = $this->app->make(EventTypeRegistry::class)->scope($type, EventScope::Alliance);
        $event = $this->app->make(CreateEvent::class)->handle(
            actor: $player,
            configuration: $configuration,
            target: $alliance,
            firstLocalStart: CarbonImmutable::now('UTC')->addDay(),
            durationMinutes: 60,
        );
        $create = $this->app->make(CreateEventReminderRule::class);

        $rule = $create->handle($player, $event, 60, EventReminderAudience::AllScopePlayers);
        $same = $create->handle($player, $event, 60, EventReminderAudience::AllScopePlayers);

        self::assertSame((string) $rule->id, (string) $same->id);
        self::assertSame(1, OutboxMessage::query()->where('event_type', 'event.reminder.rule.created')->count());

        $disabled = $this->app->make(DisableEventReminderRule::class)->handle($player, $event, $rule);
        self::assertFalse($disabled->is_enabled);

        $reenabled = $create->handle($player, $event, 60, EventReminderAudience::AllScopePlayers);
        self::assertTrue($reenabled->is_enabled);
        self::assertSame(1, OutboxMessage::query()->where('event_type', 'event.reminder.rule.enabled')->count());
        self::assertSame(1, OutboxMessage::query()->where('event_type', 'event.reminder.rule.disabled')->count());
    }

    public function test_alliance_reminders_do_not_deliver_to_rostered_players_without_alliance_authority(): void
    {
        $owner = User::factory()->create();
        $outsider = User::factory()->create();
        $kingdom = Kingdom::query()->create(['number' => 8109, 'status' => 'active']);
        $ownerPlayer = Player::query()->create([
            'user_id' => $owner->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => 'reminder-authorized',
            'current_name' => 'Authorized Player',
        ]);
        $rosteredOnlyPlayer = Player::query()->create([
            'user_id' => $outsider->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => 'reminder-rostered-only',
            'current_name' => 'Rostered Only',
        ]);
        $alliance = $this->app->make(CreateAlliance::class)->handle($ownerPlayer, 'Reminder Authority', 'reminder-authority');
        $saveRoster = $this->app->make(SaveRosterEntry::class);
        $saveRoster->handle($alliance, $ownerPlayer, ['name' => 'Authorized Player', 'game_player_id' => 'reminder-authorized']);
        $saveRoster->handle($alliance, $ownerPlayer, ['name' => 'Rostered Only', 'game_player_id' => 'reminder-rostered-only']);
        $type = EventType::query()->where('slug', 'custom')->sole();
        $configuration = $this->app->make(EventTypeRegistry::class)->scope($type, EventScope::Alliance);
        $event = $this->app->make(CreateEvent::class)->handle(
            actor: $ownerPlayer,
            configuration: $configuration,
            target: $alliance,
            firstLocalStart: CarbonImmutable::now('UTC')->addMinutes(30),
            durationMinutes: 60,
        );
        $rule = $this->app->make(CreateEventReminderRule::class)->handle(
            $ownerPlayer,
            $event,
            60,
            EventReminderAudience::AllScopePlayers,
        );

        self::assertSame(1, $this->app->make(QueueDueEventReminders::class)->handle());
        self::assertSame(
            [(string) $ownerPlayer->id],
            EventReminderDelivery::query()
                ->where('rule_id', $rule->id)
                ->pluck('player_id')
                ->map(static fn ($id): string => (string) $id)
                ->all(),
        );
        self::assertFalse(EventReminderDelivery::query()->where('player_id', $rosteredOnlyPlayer->id)->exists());
    }

    public function test_kingdom_reminders_do_not_deliver_to_players_without_kingdom_view_authority(): void
    {
        $adminUser = User::factory()->create();
        $ordinaryUser = User::factory()->create();
        $kingdom = Kingdom::query()->create(['number' => 8111, 'status' => 'active']);
        $adminPlayer = Player::query()->create([
            'user_id' => $adminUser->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => '8111-admin',
            'current_name' => 'Kingdom Admin',
        ]);
        $ordinaryPlayer = Player::query()->create([
            'user_id' => $ordinaryUser->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => '8111-ordinary',
            'current_name' => 'Ordinary Player',
        ]);
        $this->app->make(BootstrapKingdomAdministrator::class)->handle($kingdom, $adminPlayer);
        $type = EventType::query()->where('slug', 'custom')->sole();
        $configuration = $this->app->make(EventTypeRegistry::class)->scope($type, EventScope::Kingdom);
        $event = $this->app->make(CreateEvent::class)->handle(
            actor: $adminPlayer,
            configuration: $configuration,
            target: $kingdom,
            firstLocalStart: CarbonImmutable::now('UTC')->addMinutes(30),
            durationMinutes: 60,
        );
        $rule = $this->app->make(CreateEventReminderRule::class)->handle(
            $adminPlayer,
            $event,
            60,
            EventReminderAudience::AllScopePlayers,
        );

        self::assertSame(1, $this->app->make(QueueDueEventReminders::class)->handle());
        self::assertSame(
            [(string) $adminPlayer->id],
            EventReminderDelivery::query()
                ->where('rule_id', $rule->id)
                ->pluck('player_id')
                ->map(static fn ($id): string => (string) $id)
                ->all(),
        );
        self::assertFalse(EventReminderDelivery::query()->where('player_id', $ordinaryPlayer->id)->exists());
    }

    public function test_one_user_with_multiple_member_players_receives_separate_player_deliveries(): void
    {
        $owner = User::factory()->create();
        $kingdom = Kingdom::query()->create(['number' => 8104, 'status' => 'active']);
        $firstPlayer = Player::query()->create([
            'user_id' => $owner->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => 'reminder-one',
            'current_name' => 'Reminder One',
        ]);
        $secondPlayer = Player::query()->create([
            'user_id' => $owner->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => 'reminder-two',
            'current_name' => 'Reminder Two',
        ]);
        $alliance = $this->app->make(CreateAlliance::class)->handle($firstPlayer, 'Reminders', 'reminders');
        $saveRoster = $this->app->make(SaveRosterEntry::class);
        $saveRoster->handle($alliance, $firstPlayer, ['name' => 'Reminder One', 'game_player_id' => 'reminder-one']);
        $saveRoster->handle($alliance, $firstPlayer, ['name' => 'Reminder Two', 'game_player_id' => 'reminder-two']);
        $issued = $this->app->make(CreateInvitation::class)->handle($alliance, $firstPlayer, $secondPlayer, (string) $owner->email);
        $this->app->make(AcceptInvitation::class)->handle($owner, $issued->token);
        $type = EventType::query()->where('slug', 'custom')->sole();
        $configuration = $this->app->make(EventTypeRegistry::class)->scope($type, EventScope::Alliance);
        $event = $this->app->make(CreateEvent::class)->handle(
            actor: $firstPlayer,
            configuration: $configuration,
            target: $alliance,
            firstLocalStart: CarbonImmutable::now('UTC')->addMinutes(30),
            durationMinutes: 60,
        );
        $this->app->make(CreateEventReminderRule::class)->handle(
            $firstPlayer,
            $event,
            60,
            EventReminderAudience::AllScopePlayers,
        );

        self::assertSame(2, $this->app->make(QueueDueEventReminders::class)->handle());
        self::assertSame(2, EventReminderDelivery::query()->count());
        self::assertSame(2, EventReminderDelivery::query()->where('recipient_user_id', $owner->id)->count());
        self::assertSame(
            collect([$firstPlayer->id, $secondPlayer->id])->sort()->values()->all(),
            EventReminderDelivery::query()->pluck('player_id')->sort()->values()->all(),
        );
        self::assertSame(2, OutboxMessage::query()->where('event_type', 'event.reminder.requested')->count());
        self::assertSame(0, $this->app->make(QueueDueEventReminders::class)->handle());

        $markSent = $this->app->make(MarkEventReminderSent::class);
        foreach (OutboxMessage::query()->where('event_type', 'event.reminder.requested')->get() as $message) {
            $markSent->handle(new OutboxPublished(
                messageId: (string) $message->id,
                allianceId: $message->alliance_id,
                eventType: (string) $message->event_type,
                aggregateType: (string) $message->aggregate_type,
                aggregateId: (string) $message->aggregate_id,
                idempotencyKey: (string) $message->idempotency_key,
                payload: $message->payload,
                occurredAt: $message->occurred_at->toIso8601String(),
            ));
        }

        $context = $this->app->make(PlayerContext::class);
        $inbox = $this->app->make(EventReminderInboxQuery::class);
        $context->activate($firstPlayer, $owner);
        $firstInbox = $inbox->for($owner);
        self::assertCount(1, $firstInbox);
        self::assertSame((string) $firstPlayer->id, $firstInbox[0]['playerId']);

        $context->clear();
        $context->activate($secondPlayer, $owner);
        $secondInbox = $inbox->for($owner);
        self::assertCount(1, $secondInbox);
        self::assertSame((string) $secondPlayer->id, $secondInbox[0]['playerId']);
    }

    public function test_http_response_action_cannot_act_as_a_different_owned_player_than_active_context(): void
    {
        $user = User::factory()->create();
        $kingdom = Kingdom::query()->create(['number' => 8106, 'status' => 'active']);
        $firstPlayer = Player::query()->create([
            'user_id' => $user->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => 'context-first',
            'current_name' => 'Context First',
        ]);
        $secondPlayer = Player::query()->create([
            'user_id' => $user->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => 'context-second',
            'current_name' => 'Context Second',
        ]);
        $alliance = $this->app->make(CreateAlliance::class)->handle($firstPlayer, 'Context Guard', 'context-guard');
        $saveRoster = $this->app->make(SaveRosterEntry::class);
        $saveRoster->handle($alliance, $firstPlayer, ['name' => 'Context First', 'game_player_id' => 'context-first']);
        $saveRoster->handle($alliance, $firstPlayer, ['name' => 'Context Second', 'game_player_id' => 'context-second']);
        $issued = $this->app->make(CreateInvitation::class)->handle($alliance, $firstPlayer, $secondPlayer, (string) $user->email);
        $this->app->make(AcceptInvitation::class)->handle($user, $issued->token);
        $type = EventType::query()->where('slug', 'custom')->sole();
        $configuration = $this->app->make(EventTypeRegistry::class)->scope($type, EventScope::Player);
        $event = $this->app->make(CreateEvent::class)->handle(
            actor: $secondPlayer,
            configuration: $configuration,
            target: $secondPlayer,
            firstLocalStart: CarbonImmutable::now('UTC')->addDay(),
            durationMinutes: 60,
        );
        $occurrence = $event->occurrences->firstOrFail();

        $this->actingAs($user)
            ->withSession([(string) config('game_world.active_player_session_key') => (string) $firstPlayer->id])
            ->post('/events/'.$occurrence->id.'/responses', ['response' => EventResponseChoice::Going->value])
            ->assertForbidden();

        self::assertSame(0, EventResponse::query()->count());
    }

    public function test_attention_query_surfaces_response_and_registration_for_active_player(): void
    {
        $owner = User::factory()->create();
        $kingdom = Kingdom::query()->create(['number' => 8105, 'status' => 'active']);
        $player = Player::query()->create([
            'user_id' => $owner->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => 'attention-player',
            'current_name' => 'Attention Player',
        ]);
        $alliance = $this->app->make(CreateAlliance::class)->handle($player, 'Attention', 'attention');
        $this->app->make(SaveRosterEntry::class)->handle($alliance, $player, [
            'name' => 'Attention Player',
            'game_player_id' => 'attention-player',
        ]);
        $type = EventType::query()->where('slug', 'custom')->sole();
        $configuration = $this->app->make(EventTypeRegistry::class)->scope($type, EventScope::Alliance);
        $this->app->make(CreateEvent::class)->handle(
            actor: $player,
            configuration: $configuration,
            target: $alliance,
            firstLocalStart: CarbonImmutable::now('UTC')->addDay(),
            durationMinutes: 60,
            capacity: 10,
        );

        $actions = array_column($this->app->make(EventAttentionQuery::class)->for($player), 'action');
        self::assertContains('response', $actions);
        self::assertContains('registration', $actions);
    }
}
