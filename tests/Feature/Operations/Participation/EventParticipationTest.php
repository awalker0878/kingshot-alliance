<?php

declare(strict_types=1);

namespace Tests\Feature\Operations\Participation;

use App\Contexts\Accounts\Models\User;
use App\Contexts\Alliance\Core\Actions\CreateAlliance;
use App\Contexts\Alliance\Membership\Actions\AcceptInvitation;
use App\Contexts\Alliance\Membership\Actions\CreateInvitation;
use App\Contexts\Alliance\Membership\Enums\RosterState;
use App\Contexts\Alliance\Membership\Models\AllianceRosterEntry;
use App\Contexts\GameWorld\Models\Kingdom;
use App\Contexts\GameWorld\Models\Player;
use App\Contexts\Operations\EventCore\Actions\CreateEvent;
use App\Contexts\Operations\EventCore\Actions\UpdateEvent;
use App\Contexts\Operations\EventCore\Enums\EventOccurrenceStatus;
use App\Contexts\Operations\EventCore\Enums\EventScope;
use App\Contexts\Operations\EventCore\Models\EventType;
use App\Contexts\Operations\EventCore\Queries\EventAttentionQuery;
use App\Contexts\Operations\EventCore\Services\EventTypeRegistry;
use App\Contexts\Operations\Participation\Actions\CancelEventRegistration;
use App\Contexts\Operations\Participation\Actions\RecordEventAttendance;
use App\Contexts\Operations\Participation\Actions\RegisterForEvent;
use App\Contexts\Operations\Participation\Actions\RespondToEvent;
use App\Contexts\Operations\Participation\Enums\EventAttendanceStatus;
use App\Contexts\Operations\Participation\Enums\EventRegistrationStatus;
use App\Contexts\Operations\Participation\Enums\EventResponseChoice;
use App\Contexts\Operations\Participation\Models\EventAttendance;
use App\Contexts\Operations\Participation\Models\EventRegistration;
use App\Contexts\Operations\Participation\Models\EventResponse;
use App\Contexts\Operations\Reminders\Actions\CreateEventReminderRule;
use App\Contexts\Operations\Reminders\Actions\DisableEventReminderRule;
use App\Contexts\Operations\Reminders\Enums\EventReminderAudience;
use App\Shared\Infrastructure\Messaging\Outbox\Models\OutboxMessage;
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
        AllianceRosterEntry::query()->create([
            'alliance_id' => $alliance->id,
            'player_id' => Player::query()->where('game_player_id', 'response-player')->sole()->id,
            'observed_name' => 'Response Player',
            'state' => RosterState::Active,
            'joined_at' => now(),
            'last_observed_at' => now(),
            'source' => 'manual',
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
        AllianceRosterEntry::query()->create([
            'alliance_id' => $alliance->id,
            'player_id' => Player::query()->where('game_player_id', 'reschedule-player')->sole()->id,
            'observed_name' => 'Reschedule Player',
            'state' => RosterState::Active,
            'joined_at' => now(),
            'last_observed_at' => now(),
            'source' => 'manual',
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
        AllianceRosterEntry::query()->create([
            'alliance_id' => $alliance->id,
            'player_id' => Player::query()->where('game_player_id', 'capacity-r5')->sole()->id,
            'observed_name' => 'Capacity R5',
            'state' => RosterState::Active,
            'joined_at' => now(),
            'last_observed_at' => now(),
            'source' => 'manual',
        ]);
        AllianceRosterEntry::query()->create([
            'alliance_id' => $alliance->id,
            'player_id' => Player::query()->where('game_player_id', 'seat-one')->sole()->id,
            'observed_name' => 'Seat One',
            'state' => RosterState::Active,
            'joined_at' => now(),
            'last_observed_at' => now(),
            'source' => 'manual',
        ]);
        AllianceRosterEntry::query()->create([
            'alliance_id' => $alliance->id,
            'player_id' => Player::query()->where('game_player_id', 'seat-two')->sole()->id,
            'observed_name' => 'Seat Two',
            'state' => RosterState::Active,
            'joined_at' => now(),
            'last_observed_at' => now(),
            'source' => 'manual',
        ]);
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
        AllianceRosterEntry::query()->create([
            'alliance_id' => $alliance->id,
            'player_id' => Player::query()->where('game_player_id', 'attendance-r5')->sole()->id,
            'observed_name' => 'Attendance R5',
            'state' => RosterState::Active,
            'joined_at' => now(),
            'last_observed_at' => now(),
            'source' => 'manual',
        ]);
        AllianceRosterEntry::query()->create([
            'alliance_id' => $alliance->id,
            'player_id' => Player::query()->where('game_player_id', 'attendance-player')->sole()->id,
            'observed_name' => 'Attendance Player',
            'state' => RosterState::Active,
            'joined_at' => now(),
            'last_observed_at' => now(),
            'source' => 'manual',
        ]);
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
        AllianceRosterEntry::query()->create([
            'alliance_id' => $alliance->id,
            'player_id' => Player::query()->where('game_player_id', 'reminder-rule-player')->sole()->id,
            'observed_name' => 'Reminder Rule Player',
            'state' => RosterState::Active,
            'joined_at' => now(),
            'last_observed_at' => now(),
            'source' => 'manual',
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
        AllianceRosterEntry::query()->create([
            'alliance_id' => $alliance->id,
            'player_id' => Player::query()->where('game_player_id', 'context-first')->sole()->id,
            'observed_name' => 'Context First',
            'state' => RosterState::Active,
            'joined_at' => now(),
            'last_observed_at' => now(),
            'source' => 'manual',
        ]);
        AllianceRosterEntry::query()->create([
            'alliance_id' => $alliance->id,
            'player_id' => Player::query()->where('game_player_id', 'context-second')->sole()->id,
            'observed_name' => 'Context Second',
            'state' => RosterState::Active,
            'joined_at' => now(),
            'last_observed_at' => now(),
            'source' => 'manual',
        ]);
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
        AllianceRosterEntry::query()->create([
            'alliance_id' => $alliance->id,
            'player_id' => Player::query()->where('game_player_id', 'attention-player')->sole()->id,
            'observed_name' => 'Attention Player',
            'state' => RosterState::Active,
            'joined_at' => now(),
            'last_observed_at' => now(),
            'source' => 'manual',
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
