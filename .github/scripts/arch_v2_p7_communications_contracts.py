from pathlib import Path
import shutil

root = Path('.')
staged = root / 'tests/RewriteInput/Communications'
if not staged.is_dir():
    raise RuntimeError('Expected staged Communications rewrite inputs before P7 promotion.')

expected_staged_php = {
    'EventReminders/EventParticipationTest.php',
    'EventReminders/EventPhasePollTest.php',
    'EventReminders/EventRosterTest.php',
    'KingPerks/KingPerkReminderDeliveryRewriteInputTest.php',
}
actual_staged_php = {
    str(path.relative_to(staged))
    for path in staged.rglob('*.php')
}
if actual_staged_php != expected_staged_php:
    raise RuntimeError(
        'Communications rewrite-input inventory changed; classify new contracts before deletion.\n'
        f'Expected: {sorted(expected_staged_php)}\nActual: {sorted(actual_staged_php)}'
    )

feature_root = root / 'tests/Feature/Communications'
if feature_root.exists():
    raise RuntimeError(f'P7 Communications feature destination already exists: {feature_root}')
(feature_root / 'EventReminders').mkdir(parents=True)
(feature_root / 'KingPerks').mkdir(parents=True)

(feature_root / 'EventReminders/EventReminderDeliveryTest.php').write_text(r'''<?php

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
''', encoding='utf-8')

(feature_root / 'EventReminders/PollDeadlineReminderDeliveryTest.php').write_text(r'''<?php

declare(strict_types=1);

namespace Tests\Feature\Communications\EventReminders;

use App\Contexts\Accounts\Models\User;
use App\Contexts\Alliance\Core\Actions\CreateAlliance;
use App\Contexts\Alliance\Membership\Actions\AcceptInvitation;
use App\Contexts\Alliance\Membership\Actions\CreateInvitation;
use App\Contexts\Communications\Reminders\Actions\QueueDueEventReminders;
use App\Contexts\Communications\Reminders\Models\EventReminderDelivery;
use App\Contexts\GameWorld\Models\Kingdom;
use App\Contexts\GameWorld\Models\Player;
use App\Contexts\Intelligence\Roster\Actions\SaveRosterEntry;
use App\Contexts\Operations\EventCore\Actions\CreateEvent;
use App\Contexts\Operations\EventCore\Enums\EventScope;
use App\Contexts\Operations\EventCore\Models\EventOccurrence;
use App\Contexts\Operations\EventCore\Models\EventType;
use App\Contexts\Operations\EventCore\Services\EventTypeRegistry;
use App\Contexts\Operations\Polls\Actions\SaveEventPoll;
use App\Contexts\Operations\Polls\Enums\EventPollStatus;
use App\Contexts\Operations\Polls\Enums\EventPollType;
use App\Contexts\Operations\Polls\Models\EventPoll;
use App\Contexts\Operations\Reminders\Actions\CreateEventReminderRule;
use App\Contexts\Operations\Reminders\Enums\EventReminderAudience;
use App\Contexts\Operations\Reminders\Enums\EventReminderTrigger;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class PollDeadlineReminderDeliveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_poll_deadline_reminders_are_player_specific_and_idempotent(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create(['email' => 'member-8204@example.com']);
        $kingdom = Kingdom::query()->create(['number' => 8204, 'status' => 'active']);
        $ownerPlayer = Player::query()->create([
            'user_id' => $owner->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => '8204-owner',
            'current_name' => 'Owner 8204',
        ]);
        $memberPlayer = Player::query()->create([
            'user_id' => $member->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => '8204-member',
            'current_name' => 'Member 8204',
        ]);
        $alliance = $this->app->make(CreateAlliance::class)->handle($ownerPlayer, 'Swordland 8204', 'swordland-8204');
        $saveRoster = $this->app->make(SaveRosterEntry::class);
        $saveRoster->handle($alliance, $ownerPlayer, ['name' => 'Owner 8204', 'game_player_id' => '8204-owner']);
        $saveRoster->handle($alliance, $ownerPlayer, ['name' => 'Member 8204', 'game_player_id' => '8204-member']);
        $issued = $this->app->make(CreateInvitation::class)->handle($alliance, $ownerPlayer, $memberPlayer, (string) $member->email);
        $this->app->make(AcceptInvitation::class)->handle($member, $issued->token);
        $type = EventType::query()->where('slug', 'swordland-showdown')->sole();
        $configuration = $this->app->make(EventTypeRegistry::class)->scope($type, EventScope::Alliance);
        $event = $this->app->make(CreateEvent::class)->handle(
            actor: $ownerPlayer,
            configuration: $configuration,
            target: $alliance,
            firstLocalStart: CarbonImmutable::now('UTC')->addDays(8)->startOfMinute(),
        );
        $occurrence = $event->occurrences->firstOrFail();
        $poll = $this->openTimeVote(
            $ownerPlayer,
            $occurrence,
            $occurrence->polls()->where('key', 'battle-time')->sole(),
            closeMinutes: 30,
        );

        $this->app->make(CreateEventReminderRule::class)->handle(
            actor: $ownerPlayer,
            event: $event,
            minutesBefore: 60,
            audience: EventReminderAudience::AllScopePlayers,
            trigger: EventReminderTrigger::BeforePollClose,
            poll: $poll,
        );

        self::assertSame(2, $this->app->make(QueueDueEventReminders::class)->handle());
        self::assertSame(2, EventReminderDelivery::query()->where('rule_id', $event->reminderRules()->sole()->id)->count());
        self::assertSame(
            collect([$ownerPlayer->id, $memberPlayer->id])->sort()->values()->all(),
            EventReminderDelivery::query()->pluck('player_id')->sort()->values()->all(),
        );
        self::assertSame(0, $this->app->make(QueueDueEventReminders::class)->handle());
    }

    private function openTimeVote(Player $actor, EventOccurrence $occurrence, EventPoll $poll, int $closeMinutes = 60): EventPoll
    {
        return $this->app->make(SaveEventPoll::class)->handle(
            actor: $actor,
            occurrence: $occurrence,
            key: 'battle-time',
            type: EventPollType::TimeVote,
            questionKey: 'events.polls.swordland_battle_time.question',
            opensAt: CarbonImmutable::now('UTC')->subMinutes(5),
            closesAt: CarbonImmutable::now('UTC')->addMinutes($closeMinutes),
            status: EventPollStatus::Open,
            maxChoices: 1,
            options: $this->timeOptions(),
            settings: $poll->settings,
            poll: $poll,
        );
    }

    /** @return list<array{label:string,value:string}> */
    private function timeOptions(): array
    {
        return [
            ['label' => '19:00 UTC', 'value' => CarbonImmutable::now('UTC')->addDay()->setTime(19, 0)->toIso8601String()],
            ['label' => '21:00 UTC', 'value' => CarbonImmutable::now('UTC')->addDay()->setTime(21, 0)->toIso8601String()],
        ];
    }
}
''', encoding='utf-8')

(feature_root / 'KingPerks/KingPerkReminderDeliveryTest.php').write_text(r'''<?php

declare(strict_types=1);

namespace Tests\Feature\Communications\KingPerks;

use App\Contexts\Accounts\Models\User;
use App\Contexts\Communications\Reminders\Actions\QueueDueKingPerkReminders;
use App\Contexts\Communications\Reminders\Models\KingPerkReminderDelivery;
use App\Contexts\GameWorld\Governance\Enums\DefaultKingdomRole;
use App\Contexts\GameWorld\Governance\Models\KingdomRoleAssignment;
use App\Contexts\GameWorld\Governance\Services\KingdomRoleProvisioner;
use App\Contexts\GameWorld\Models\Kingdom;
use App\Contexts\GameWorld\Models\Player;
use App\Contexts\Operations\Access\Services\KingdomOperationsRoleProvisioner;
use App\Contexts\Operations\EventCore\Enums\EventScope;
use App\Contexts\Operations\EventCore\Enums\EventStatus;
use App\Contexts\Operations\EventCore\Models\Event;
use App\Contexts\Operations\EventCore\Models\EventOccurrence;
use App\Contexts\Operations\EventCore\Models\EventType;
use App\Contexts\Operations\EventCore\Services\EventTypeRegistry;
use App\Contexts\Operations\KingPerks\Enums\KingAppointmentType;
use App\Contexts\Operations\KingPerks\Enums\KingPerkPlanStatus;
use App\Contexts\Operations\KingPerks\Enums\KingPerkReminderKind;
use App\Contexts\Operations\KingPerks\Models\KingPerkPlan;
use App\Contexts\Operations\KingPerks\Services\KingPerkScheduler;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Tests\TestCase;

final class KingPerkReminderDeliveryTest extends TestCase
{
    use RefreshDatabase;

    public function test_reminders_are_idempotent_and_resolve_current_kingdom_managers(): void
    {
        $now = CarbonImmutable::parse('2026-09-01 00:00', 'UTC');
        CarbonImmutable::setTestNow($now);
        Carbon::setTestNow($now);

        try {
            $kingdom = $this->kingdom(2641);
            $otherKingdom = $this->kingdom(2642);
            $currentManager = $this->manager($kingdom, 'Current Manager');
            $formerManager = $this->manager($kingdom, 'Former Manager');
            $target = $this->player($kingdom, 'Reminder Target');
            $plan = $this->plan($kingdom, $currentManager);

            $this->app->make(KingPerkScheduler::class)->assignAppointment(
                actor: $currentManager,
                plan: $plan,
                type: KingAppointmentType::NobleAdvisor,
                target: $target,
                startsAt: $now->addMinutes(5),
            );

            KingdomRoleAssignment::query()
                ->where('kingdom_id', $kingdom->id)
                ->where('player_id', $formerManager->id)
                ->delete();
            $formerManager->forceFill(['current_kingdom_id' => $otherKingdom->id])->save();

            $queue = $this->app->make(QueueDueKingPerkReminders::class);
            self::assertSame(4, $queue->handle());
            self::assertSame(0, $queue->handle());
            self::assertSame(4, KingPerkReminderDelivery::query()->count());
            self::assertFalse(KingPerkReminderDelivery::query()->where('player_id', $formerManager->id)->exists());
            self::assertTrue(KingPerkReminderDelivery::query()
                ->where('player_id', $currentManager->id)
                ->where('kind', KingPerkReminderKind::AppointmentUnconfirmed10Minutes->value)
                ->exists());
            self::assertSame(3, KingPerkReminderDelivery::query()->where('player_id', $target->id)->count());
        } finally {
            CarbonImmutable::setTestNow();
            Carbon::setTestNow();
        }
    }

    private function kingdom(int $number): Kingdom
    {
        return Kingdom::query()->create([
            'number' => $number,
            'status' => 'active',
        ]);
    }

    private function player(Kingdom $kingdom, string $name): Player
    {
        $user = User::factory()->create();

        return Player::query()->create([
            'user_id' => $user->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => 'king-perks-'.Str::ulid(),
            'current_name' => $name,
        ]);
    }

    private function manager(Kingdom $kingdom, string $name): Player
    {
        $player = $this->player($kingdom, $name);
        $roles = $this->app->make(KingdomRoleProvisioner::class)->provision($kingdom);
        $this->app->make(KingdomOperationsRoleProvisioner::class)->provision($kingdom);
        $administrator = $roles[DefaultKingdomRole::Administrator->value];

        KingdomRoleAssignment::query()->create([
            'kingdom_id' => $kingdom->id,
            'player_id' => $player->id,
            'kingdom_role_id' => $administrator->id,
        ]);

        return $player;
    }

    private function plan(Kingdom $kingdom, Player $actor): KingPerkPlan
    {
        $type = EventType::query()->where('slug', 'kingdom-of-power')->sole();
        $scope = $this->app->make(EventTypeRegistry::class)->scope($type, EventScope::Kingdom);
        $startsAt = CarbonImmutable::parse('2026-09-06 10:00', 'UTC');
        $event = Event::query()->create([
            'event_type_scope_id' => $scope->id,
            'event_type_id' => $type->id,
            'scope' => EventScope::Kingdom,
            'kingdom_id' => $kingdom->id,
            'target_display_name' => 'Kingdom #'.$kingdom->number,
            'timezone' => 'UTC',
            'schedule_source' => $scope->schedule_source,
            'recurrence_policy' => $scope->recurrence_policy,
            'starts_at' => $startsAt,
            'duration_minutes' => 300,
            'status' => EventStatus::Published,
            'created_by_player_id' => $actor->id,
            'updated_by_player_id' => $actor->id,
        ]);
        $occurrence = EventOccurrence::query()->create([
            'event_id' => $event->id,
            'starts_at' => $startsAt,
            'ends_at' => $startsAt->addMinutes(300),
            'status' => 'scheduled',
        ]);

        return KingPerkPlan::query()->create([
            'event_id' => $event->id,
            'occurrence_id' => $occurrence->id,
            'kingdom_id' => $kingdom->id,
            'status' => KingPerkPlanStatus::Published,
            'window_starts_at' => CarbonImmutable::parse('2026-09-01 00:00', 'UTC'),
            'window_ends_at' => $startsAt,
            'created_by_player_id' => $actor->id,
            'published_by_player_id' => $actor->id,
            'published_at' => now(),
        ]);
    }
}
''', encoding='utf-8')

# The staged files were migration inputs, not a permanent test owner. Their
# Operations behavior is already covered in tests/Feature/Operations; P7 keeps
# only the extracted Communications delivery contracts above.
shutil.rmtree(staged)

architecture = root / 'tests/Architecture/ArchitectureV2PlatformTest.php'
source = architecture.read_text(encoding='utf-8')
needle = "        self::assertDirectoryDoesNotExist($this->root().'/app/Shared/Messaging');\n"
if source.count(needle) != 1:
    raise RuntimeError('ArchitectureV2PlatformTest shape changed before Communications promotion.')
source = source.replace(
    needle,
    needle
    + "        self::assertDirectoryExists($this->root().'/tests/Feature/Communications');\n"
    + "        self::assertDirectoryDoesNotExist($this->root().'/tests/RewriteInput/Communications');\n",
    1,
)
architecture.write_text(source, encoding='utf-8')

if staged.exists():
    raise RuntimeError('P7 left tests/RewriteInput/Communications behind.')

print('Promoted Communications delivery contracts and removed rewrite-input staging.')
