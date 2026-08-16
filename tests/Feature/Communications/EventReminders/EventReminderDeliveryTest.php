<?php

declare(strict_types=1);

namespace Tests\Feature\Communications\EventReminders;

use App\Contexts\Accounts\Models\User;
use App\Contexts\Alliance\Core\Actions\CreateAlliance;
use App\Contexts\Alliance\Membership\Actions\AcceptInvitation;
use App\Contexts\Alliance\Membership\Actions\CreateInvitation;
use App\Contexts\Communications\Reminders\Actions\MarkEventReminderSent;
use App\Contexts\Communications\Reminders\Actions\QueueDueEventReminders;
use App\Contexts\Communications\Reminders\Models\EventReminderDelivery;
use App\Contexts\GameWorld\Models\Kingdom;
use App\Contexts\GameWorld\Models\Player;
use App\Contexts\GameWorld\Services\PlayerContext;
use App\Contexts\Intelligence\Roster\Actions\SaveRosterEntry;
use App\Contexts\Operations\EventCore\Actions\CreateEvent;
use App\Contexts\Operations\EventCore\Enums\EventScope;
use App\Contexts\Operations\EventCore\Models\EventType;
use App\Contexts\Operations\EventCore\Services\EventTypeRegistry;
use App\Contexts\Operations\Reminders\Actions\CreateEventReminderRule;
use App\Contexts\Operations\Reminders\Enums\EventReminderAudience;
use App\Contexts\Operations\Rosters\Actions\AssignEventRosterPlayer;
use App\Contexts\Operations\Rosters\Actions\RespondToEventRosterAssignment;
use App\Contexts\Operations\Rosters\Enums\EventRosterMemberStatus;
use App\ReadModels\EventCalendar\Queries\EventReminderInboxQuery;
use App\Shared\Infrastructure\Messaging\Outbox\Events\OutboxPublished;
use App\Shared\Infrastructure\Messaging\Outbox\Models\OutboxMessage;
use App\Workflows\KingdomGovernance\Actions\BootstrapKingdomAdministrator;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class EventReminderDeliveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_alliance_reminders_exclude_rostered_players_without_alliance_authority(): void
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

    public function test_kingdom_reminders_exclude_players_without_kingdom_view_authority(): void
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

    public function test_rostered_reminders_only_target_active_assignments(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create(['email' => 'roster-reminder@example.com']);
        $kingdom = Kingdom::query()->create(['number' => 8306, 'status' => 'active']);
        $firstPlayer = Player::query()->create([
            'user_id' => $owner->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => '8306-first',
            'current_name' => 'Reminder One',
        ]);
        $secondPlayer = Player::query()->create([
            'user_id' => $member->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => '8306-second',
            'current_name' => 'Reminder Two',
        ]);
        $alliance = $this->app->make(CreateAlliance::class)->handle($firstPlayer, 'Roster Reminder', 'roster-reminder');
        $saveRoster = $this->app->make(SaveRosterEntry::class);
        $saveRoster->handle($alliance, $firstPlayer, ['name' => 'Reminder One', 'game_player_id' => '8306-first']);
        $saveRoster->handle($alliance, $firstPlayer, ['name' => 'Reminder Two', 'game_player_id' => '8306-second']);
        $issued = $this->app->make(CreateInvitation::class)->handle($alliance, $firstPlayer, $secondPlayer, (string) $member->email);
        $this->app->make(AcceptInvitation::class)->handle($member, $issued->token);
        $type = EventType::query()->where('slug', 'swordland-showdown')->sole();
        $configuration = $this->app->make(EventTypeRegistry::class)->scope($type, EventScope::Alliance);
        $event = $this->app->make(CreateEvent::class)->handle(
            actor: $firstPlayer,
            configuration: $configuration,
            target: $alliance,
            firstLocalStart: CarbonImmutable::now('UTC')->addMinutes(30),
            durationMinutes: 60,
        );
        $occurrence = $event->occurrences->firstOrFail();
        $roster = $occurrence->rosters()->where('key', 'combatants')->sole();
        $assign = $this->app->make(AssignEventRosterPlayer::class);
        $firstAssignment = $assign->handle($firstPlayer, $roster, $firstPlayer);
        $secondAssignment = $assign->handle($firstPlayer, $roster, $secondPlayer);

        $this->app->make(RespondToEventRosterAssignment::class)->handle(
            $firstPlayer,
            $firstAssignment,
            $firstPlayer,
            EventRosterMemberStatus::Confirmed,
        );
        $this->app->make(RespondToEventRosterAssignment::class)->handle(
            $secondPlayer,
            $secondAssignment,
            $secondPlayer,
            EventRosterMemberStatus::Declined,
        );
        $rule = $this->app->make(CreateEventReminderRule::class)->handle(
            $firstPlayer,
            $event,
            60,
            EventReminderAudience::Rostered,
        );

        self::assertSame(1, $this->app->make(QueueDueEventReminders::class)->handle());
        self::assertSame(
            [(string) $firstPlayer->id],
            EventReminderDelivery::query()
                ->where('rule_id', $rule->id)
                ->pluck('player_id')
                ->map(static fn ($id): string => (string) $id)
                ->all(),
        );
    }
}
