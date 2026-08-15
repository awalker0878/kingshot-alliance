<?php

declare(strict_types=1);

namespace Tests\Feature\Events;

use App\Domain\Alliances\Actions\CreateAlliance;
use App\Domain\Events\Actions\CastEventPollVote;
use App\Domain\Events\Actions\CreateEvent;
use App\Domain\Events\Actions\SaveEventPoll;
use App\Domain\Events\Enums\EventPollStatus;
use App\Domain\Events\Enums\EventPollType;
use App\Domain\Events\Enums\EventReminderAudience;
use App\Domain\Events\Enums\EventReminderTrigger;
use App\Domain\Events\Enums\EventScope;
use App\Domain\Events\Models\EventOccurrence;
use App\Domain\Events\Models\EventPoll;
use App\Domain\Events\Models\EventPollVote;
use App\Domain\Events\Models\EventType;
use App\Domain\Events\Queries\EventAttentionQuery;
use App\Domain\Events\Services\EventTypeRegistry;
use App\Contexts\Accounts\Models\User;
use App\Domain\Kingdoms\Actions\SaveRosterEntry;
use App\Contexts\GameWorld\Models\Kingdom;
use App\Contexts\GameWorld\Models\Player;
use App\Domain\Memberships\Actions\AcceptInvitation;
use App\Domain\Memberships\Actions\CreateInvitation;
use App\Domain\Notifications\Actions\CreateEventReminderRule;
use App\Domain\Notifications\Actions\QueueDueEventReminders;
use App\Domain\Notifications\Actions\SyncEventPollDeadlineReminder;
use App\Domain\Notifications\Models\EventReminderDelivery;
use App\Domain\Notifications\Models\EventReminderRule;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class EventPhasePollTest extends TestCase
{
    use RefreshDatabase;

    public function test_swordland_materializes_default_phases_and_time_poll(): void
    {
        $owner = User::factory()->create();
        $kingdom = Kingdom::query()->create(['number' => 8201, 'status' => 'active']);
        $ownerPlayer = Player::query()->create([
            'user_id' => $owner->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => '8201-owner',
            'current_name' => 'Swordland Owner',
        ]);
        $alliance = $this->app->make(CreateAlliance::class)->handle($ownerPlayer, 'Swordland Ops', 'swordland-ops');
        $this->app->make(SaveRosterEntry::class)->handle($alliance, $ownerPlayer, [
            'name' => 'Swordland Owner',
            'game_player_id' => '8201-owner',
        ]);
        $battleStart = CarbonImmutable::now('UTC')->addDays(8)->startOfMinute();
        $type = EventType::query()->where('slug', 'swordland-showdown')->sole();
        $configuration = $this->app->make(EventTypeRegistry::class)->scope($type, EventScope::Alliance);
        $event = $this->app->make(CreateEvent::class)->handle(
            actor: $ownerPlayer,
            configuration: $configuration,
            target: $alliance,
            firstLocalStart: $battleStart,
        );
        $occurrence = $event->occurrences->firstOrFail();
        $occurrence->load(['phases', 'polls.options']);

        self::assertSame(
            ['voting', 'registration', 'matchmaking', 'battle'],
            $occurrence->phases->sortBy('sort_order')->pluck('key')->values()->all(),
        );

        $voting = $occurrence->phases->firstWhere('key', 'voting');
        self::assertNotNull($voting);
        self::assertSame($battleStart->subMinutes(8640)->toIso8601String(), $voting->starts_at->toIso8601String());
        self::assertSame($battleStart->subMinutes(5760)->toIso8601String(), $voting->ends_at->toIso8601String());

        $battle = $occurrence->phases->firstWhere('key', 'battle');
        self::assertNotNull($battle);
        self::assertSame($battleStart->toIso8601String(), $battle->starts_at->toIso8601String());
        self::assertSame($battleStart->addMinutes(60)->toIso8601String(), $battle->ends_at->toIso8601String());

        self::assertCount(1, $occurrence->polls);
        $poll = $occurrence->polls->first();
        self::assertSame('battle-time', $poll->key);
        self::assertSame(EventPollType::TimeVote, $poll->poll_type);
        self::assertSame(EventPollStatus::Draft, $poll->status);
        self::assertSame('events.polls.swordland_battle_time.question', $poll->question_key);
        self::assertSame($battleStart->subMinutes(8640)->toIso8601String(), $poll->opens_at->toIso8601String());
        self::assertSame($battleStart->subMinutes(5760)->toIso8601String(), $poll->closes_at->toIso8601String());
        self::assertSame(60, $poll->settings['deadline_reminder_minutes']);
        self::assertTrue($poll->settings['manager_supplied_options']);
        self::assertCount(0, $poll->options);
    }

    public function test_players_vote_independently_and_can_change_their_own_selection(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create(['email' => 'member-8202@example.com']);
        $kingdom = Kingdom::query()->create(['number' => 8202, 'status' => 'active']);
        $ownerPlayer = Player::query()->create([
            'user_id' => $owner->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => '8202-owner',
            'current_name' => 'Owner 8202',
        ]);
        $memberPlayer = Player::query()->create([
            'user_id' => $member->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => '8202-member',
            'current_name' => 'Member 8202',
        ]);
        $alliance = $this->app->make(CreateAlliance::class)->handle($ownerPlayer, 'Swordland 8202', 'swordland-8202');
        $saveRoster = $this->app->make(SaveRosterEntry::class);
        $saveRoster->handle($alliance, $ownerPlayer, ['name' => 'Owner 8202', 'game_player_id' => '8202-owner']);
        $saveRoster->handle($alliance, $ownerPlayer, ['name' => 'Member 8202', 'game_player_id' => '8202-member']);
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
        $poll = $this->openTimeVote($ownerPlayer, $occurrence, $occurrence->polls()->where('key', 'battle-time')->sole());
        $options = $poll->options->values();
        $vote = $this->app->make(CastEventPollVote::class);

        $vote->handle($ownerPlayer, $poll, $ownerPlayer, [(string) $options[0]->id]);
        $vote->handle($memberPlayer, $poll, $memberPlayer, [(string) $options[1]->id]);

        self::assertSame(2, EventPollVote::query()->where('poll_id', $poll->id)->count());
        self::assertSame(1, EventPollVote::query()->where('poll_id', $poll->id)->where('player_id', $ownerPlayer->id)->count());
        self::assertSame(1, EventPollVote::query()->where('poll_id', $poll->id)->where('player_id', $memberPlayer->id)->count());

        $vote->handle($ownerPlayer, $poll, $ownerPlayer, [(string) $options[1]->id]);

        $ownerVote = EventPollVote::query()->where('poll_id', $poll->id)->where('player_id', $ownerPlayer->id)->sole();
        self::assertSame((string) $options[1]->id, (string) $ownerVote->option_id);
        self::assertSame(2, EventPollVote::query()->where('poll_id', $poll->id)->count());
    }

    public function test_poll_options_are_locked_after_voting_starts(): void
    {
        $owner = User::factory()->create();
        $kingdom = Kingdom::query()->create(['number' => 8203, 'status' => 'active']);
        $ownerPlayer = Player::query()->create([
            'user_id' => $owner->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => '8203-owner',
            'current_name' => 'Owner 8203',
        ]);
        $alliance = $this->app->make(CreateAlliance::class)->handle($ownerPlayer, 'Swordland 8203', 'swordland-8203');
        $this->app->make(SaveRosterEntry::class)->handle($alliance, $ownerPlayer, ['name' => 'Owner 8203', 'game_player_id' => '8203-owner']);
        $type = EventType::query()->where('slug', 'swordland-showdown')->sole();
        $configuration = $this->app->make(EventTypeRegistry::class)->scope($type, EventScope::Alliance);
        $event = $this->app->make(CreateEvent::class)->handle(
            actor: $ownerPlayer,
            configuration: $configuration,
            target: $alliance,
            firstLocalStart: CarbonImmutable::now('UTC')->addDays(8)->startOfMinute(),
        );
        $occurrence = $event->occurrences->firstOrFail();
        $poll = $this->openTimeVote($ownerPlayer, $occurrence, $occurrence->polls()->where('key', 'battle-time')->sole());
        $option = $poll->options->firstOrFail();
        $this->app->make(CastEventPollVote::class)->handle($ownerPlayer, $poll, $ownerPlayer, [(string) $option->id]);

        $this->expectException(ValidationException::class);
        $this->app->make(SaveEventPoll::class)->handle(
            actor: $ownerPlayer,
            occurrence: $occurrence,
            key: 'battle-time',
            type: EventPollType::TimeVote,
            questionKey: 'events.polls.swordland_battle_time.question',
            opensAt: CarbonImmutable::now('UTC')->subMinutes(5),
            closesAt: CarbonImmutable::now('UTC')->addMinutes(40),
            status: EventPollStatus::Open,
            maxChoices: 1,
            options: $this->timeOptions(),
            poll: $poll,
        );
    }

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
        $poll = $this->openTimeVote($ownerPlayer, $occurrence, $occurrence->polls()->where('key', 'battle-time')->sole(), closeMinutes: 30);

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

    public function test_poll_deadline_rule_tracks_the_current_poll_configuration(): void
    {
        $owner = User::factory()->create();
        $kingdom = Kingdom::query()->create(['number' => 8207, 'status' => 'active']);
        $ownerPlayer = Player::query()->create([
            'user_id' => $owner->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => '8207-owner',
            'current_name' => 'Owner 8207',
        ]);
        $alliance = $this->app->make(CreateAlliance::class)->handle($ownerPlayer, 'Swordland 8207', 'swordland-8207');
        $this->app->make(SaveRosterEntry::class)->handle($alliance, $ownerPlayer, ['name' => 'Owner 8207', 'game_player_id' => '8207-owner']);
        $type = EventType::query()->where('slug', 'swordland-showdown')->sole();
        $configuration = $this->app->make(EventTypeRegistry::class)->scope($type, EventScope::Alliance);
        $event = $this->app->make(CreateEvent::class)->handle(
            actor: $ownerPlayer,
            configuration: $configuration,
            target: $alliance,
            firstLocalStart: CarbonImmutable::now('UTC')->addDays(8)->startOfMinute(),
        );
        $occurrence = $event->occurrences->firstOrFail();
        $poll = $this->openTimeVote($ownerPlayer, $occurrence, $occurrence->polls()->where('key', 'battle-time')->sole());
        $sync = $this->app->make(SyncEventPollDeadlineReminder::class);
        $sync->handle($ownerPlayer, $poll);

        self::assertSame(1, EventReminderRule::query()->where('poll_id', $poll->id)->where('is_enabled', true)->count());
        self::assertSame(60, (int) EventReminderRule::query()->where('poll_id', $poll->id)->where('is_enabled', true)->sole()->minutes_before);

        $poll = $this->app->make(SaveEventPoll::class)->handle(
            actor: $ownerPlayer,
            occurrence: $occurrence,
            key: 'battle-time',
            type: EventPollType::TimeVote,
            questionKey: 'events.polls.swordland_battle_time.question',
            opensAt: CarbonImmutable::now('UTC')->subMinutes(5),
            closesAt: CarbonImmutable::now('UTC')->addMinutes(90),
            status: EventPollStatus::Open,
            maxChoices: 1,
            settings: array_replace($poll->settings ?? [], ['deadline_reminder_minutes' => 30]),
            poll: $poll,
        );
        $sync->handle($ownerPlayer, $poll);

        self::assertSame(1, EventReminderRule::query()->where('poll_id', $poll->id)->where('is_enabled', true)->count());
        self::assertSame(30, (int) EventReminderRule::query()->where('poll_id', $poll->id)->where('is_enabled', true)->sole()->minutes_before);
        self::assertTrue(EventReminderRule::query()->where('poll_id', $poll->id)->where('minutes_before', 60)->where('is_enabled', false)->exists());

        $poll = $this->app->make(SaveEventPoll::class)->handle(
            actor: $ownerPlayer,
            occurrence: $occurrence,
            key: 'battle-time',
            type: EventPollType::TimeVote,
            questionKey: 'events.polls.swordland_battle_time.question',
            opensAt: $poll->opens_at?->toImmutable(),
            closesAt: $poll->closes_at?->toImmutable(),
            status: EventPollStatus::Closed,
            maxChoices: 1,
            settings: $poll->settings,
            poll: $poll,
        );
        $sync->handle($ownerPlayer, $poll);

        self::assertSame(0, EventReminderRule::query()->where('poll_id', $poll->id)->where('is_enabled', true)->count());
    }

    public function test_vote_attention_clears_after_active_player_votes(): void
    {
        $owner = User::factory()->create();
        $kingdom = Kingdom::query()->create(['number' => 8205, 'status' => 'active']);
        $ownerPlayer = Player::query()->create([
            'user_id' => $owner->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => '8205-owner',
            'current_name' => 'Owner 8205',
        ]);
        $alliance = $this->app->make(CreateAlliance::class)->handle($ownerPlayer, 'Swordland 8205', 'swordland-8205');
        $this->app->make(SaveRosterEntry::class)->handle($alliance, $ownerPlayer, ['name' => 'Owner 8205', 'game_player_id' => '8205-owner']);
        $type = EventType::query()->where('slug', 'swordland-showdown')->sole();
        $configuration = $this->app->make(EventTypeRegistry::class)->scope($type, EventScope::Alliance);
        $event = $this->app->make(CreateEvent::class)->handle(
            actor: $ownerPlayer,
            configuration: $configuration,
            target: $alliance,
            firstLocalStart: CarbonImmutable::now('UTC')->addDays(8)->startOfMinute(),
        );
        $occurrence = $event->occurrences->firstOrFail();
        $poll = $this->openTimeVote($ownerPlayer, $occurrence, $occurrence->polls()->where('key', 'battle-time')->sole());
        $attention = $this->app->make(EventAttentionQuery::class);

        $before = collect($attention->for($ownerPlayer));
        self::assertTrue($before->contains(static fn (array $item): bool => $item['action'] === 'vote' && $item['pollId'] === (string) $poll->id));

        $this->app->make(CastEventPollVote::class)->handle($ownerPlayer, $poll, $ownerPlayer, [(string) $poll->options->firstOrFail()->id]);

        $after = collect($attention->for($ownerPlayer));
        self::assertFalse($after->contains(static fn (array $item): bool => $item['action'] === 'vote' && $item['pollId'] === (string) $poll->id));
    }

    public function test_event_without_poll_capability_rejects_poll_management(): void
    {
        $owner = User::factory()->create();
        $kingdom = Kingdom::query()->create(['number' => 8206, 'status' => 'active']);
        $ownerPlayer = Player::query()->create([
            'user_id' => $owner->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => '8206-owner',
            'current_name' => 'Bear Owner',
        ]);
        $alliance = $this->app->make(CreateAlliance::class)->handle($ownerPlayer, 'Bear Ops', 'bear-ops');
        $this->app->make(SaveRosterEntry::class)->handle($alliance, $ownerPlayer, ['name' => 'Bear Owner', 'game_player_id' => '8206-owner']);
        $type = EventType::query()->where('slug', 'bear-hunt')->sole();
        $configuration = $this->app->make(EventTypeRegistry::class)->scope($type, EventScope::Alliance);
        $event = $this->app->make(CreateEvent::class)->handle(
            actor: $ownerPlayer,
            configuration: $configuration,
            target: $alliance,
            firstLocalStart: CarbonImmutable::now('UTC')->addDays(2),
        );

        $this->expectException(ValidationException::class);
        $this->app->make(SaveEventPoll::class)->handle(
            actor: $ownerPlayer,
            occurrence: $event->occurrences->firstOrFail(),
            key: 'unsupported',
            type: EventPollType::Choice,
            question: 'Unsupported?',
        );
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
