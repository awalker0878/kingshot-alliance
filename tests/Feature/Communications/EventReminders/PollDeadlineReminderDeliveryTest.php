<?php

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
