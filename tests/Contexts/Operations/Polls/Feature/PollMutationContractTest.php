<?php

declare(strict_types=1);

namespace Tests\Contexts\Operations\Polls\Feature;

use App\Contexts\Operations\Events\Actions\CreateEvent;
use App\Contexts\Operations\Events\Enums\EventScope;
use App\Contexts\Operations\Events\Models\EventTypeScope;
use App\Contexts\Operations\Polls\Actions\CastEventPollVote;
use App\Contexts\Operations\Polls\Actions\SaveEventPoll;
use App\Contexts\Operations\Polls\Enums\EventPollStatus;
use App\Contexts\Operations\Polls\Enums\EventPollType;
use App\Contexts\Operations\Polls\Models\EventPoll;
use Carbon\CarbonImmutable;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Support\ScenarioFactory;
use Tests\TestCase;

final class PollMutationContractTest extends TestCase
{
    use RefreshDatabase;

    private string $actor;

    private string $occurrence;

    protected function setUp(): void
    {
        parent::setUp();
        $factory = app(ScenarioFactory::class);
        $actor = $factory->player((int) $factory->authUser()->id, 61733);
        $alliance = $factory->alliance($actor);
        $factory->roster($actor, $alliance);
        $scope = EventTypeScope::query()->where('scope', EventScope::Alliance->value)
            ->whereHas('eventType', static fn ($query) => $query->where('slug', 'bear-hunt'))->firstOrFail();
        $event = app(CreateEvent::class)->handle($actor->playerId, (string) $scope->id,
            EventScope::Alliance, $alliance->allianceId, CarbonImmutable::now('UTC')->addDay(), durationMinutes: 60);
        $this->actor = $actor->playerId;
        $this->occurrence = $event->firstOccurrenceId ?? throw new \LogicException;
    }

    public function test_supported_option_and_vote_boundaries_persist_complete_rows(): void
    {
        $poll = $this->save(options: array_values(array_map(static fn (int $i): array => ['label' => 'Option '.$i, 'value' => (string) $i,
            'metadata' => ['source' => 'reviewed', 'confirmed' => true, 'weight' => 1]], range(1, 50))), maxChoices: 20);
        $ids = array_values(DB::table('event_poll_options')->where('poll_id', $poll)->orderBy('sort_order')->limit(20)->pluck('id')->all());
        app(CastEventPollVote::class)->handle($this->actor, $this->occurrence, $poll, $ids);
        self::assertSame(50, DB::table('event_poll_options')->where('poll_id', $poll)->count());
        self::assertSame(20, DB::table('event_poll_votes')->where('poll_id', $poll)->count());
        self::assertSame(['deadline_reminder_minutes' => 10080], EventPoll::query()->findOrFail($poll)->settings);
        app(CastEventPollVote::class)->handle($this->actor, $this->occurrence, $poll, [$ids[0], $ids[0]]);
        self::assertSame(1, DB::table('event_poll_votes')->where('poll_id', $poll)->count());
    }

    /** @return iterable<string,array{string}> */
    public static function votedEdits(): iterable
    {
        yield 'type' => ['type'];
        yield 'maximum choices' => ['choices'];
        yield 'option replacement' => ['options'];
    }

    #[DataProvider('votedEdits')]
    public function test_existing_votes_keep_their_option_and_choice_semantics(string $change): void
    {
        $poll = $this->save();
        $id = (string) DB::table('event_poll_options')->where('poll_id', $poll)->value('id');
        app(CastEventPollVote::class)->handle($this->actor, $this->occurrence, $poll, [$id]);
        $before = $this->state();
        try {
            app(SaveEventPoll::class)->handle($this->actor, $this->occurrence, 'contract',
                $change === 'type' ? EventPollType::TimeVote : EventPollType::Choice,
                question: 'Choose', status: EventPollStatus::Open, maxChoices: $change === 'choices' ? 2 : 1,
                options: $change === 'options' ? [['label' => 'New', 'value' => 'new'], ['label' => 'Other', 'value' => 'other']] : null,
                pollId: $poll);
            self::fail('Existing votes cannot be reinterpreted by a changed command contract.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey($change === 'options' ? 'options' : 'poll_type', $exception->errors());
        }
        self::assertSame($before, $this->state());
        app(SaveEventPoll::class)->handle($this->actor, $this->occurrence, 'contract', EventPollType::Choice,
            question: 'Choose', status: EventPollStatus::Closed, pollId: $poll);
        self::assertSame(EventPollStatus::Closed, EventPoll::query()->findOrFail($poll)->status);
        self::assertSame(1, DB::table('event_poll_votes')->where('poll_id', $poll)->count());
    }

    public function test_a_type_change_requires_revalidated_replacement_options_before_voting(): void
    {
        $poll = $this->save();
        $before = $this->state();
        try {
            app(SaveEventPoll::class)->handle($this->actor, $this->occurrence, 'contract', EventPollType::TimeVote,
                question: 'Choose', status: EventPollStatus::Open, pollId: $poll);
            self::fail('A type change must validate existing choice meaning.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey('options', $exception->errors());
        }
        self::assertSame($before, $this->state());
        app(SaveEventPoll::class)->handle($this->actor, $this->occurrence, 'contract', EventPollType::TimeVote,
            question: 'Choose', status: EventPollStatus::Open, pollId: $poll,
            options: [['label' => 'Morning', 'value' => '2026-10-01T10:00:00Z'], ['label' => 'Evening', 'value' => '2026-10-01T20:00:00Z']]);
        self::assertSame(EventPollType::TimeVote, EventPoll::query()->findOrFail($poll)->poll_type);
        self::assertSame(2, DB::table('event_poll_options')->where('poll_id', $poll)->count());
    }

    public function test_a_late_outbox_failure_rolls_back_poll_options_and_audit_then_retries(): void
    {
        $before = $this->state();
        $failed = false;
        DB::listen(static function (QueryExecuted $query) use (&$failed): void {
            if (! $failed && str_starts_with($query->sql, 'insert into "outbox_messages"')) {
                $failed = true;
                throw new RuntimeException('Injected Poll outbox failure');
            }
        });
        try {
            $this->save();
            self::fail('Outbox failure must reject the whole command.');
        } catch (RuntimeException $exception) {
            self::assertSame('Injected Poll outbox failure', $exception->getMessage());
        }
        self::assertTrue($failed);
        self::assertSame($before, $this->state());
        $poll = $this->save();
        self::assertSame(2, DB::table('event_poll_options')->where('poll_id', $poll)->count());
    }

    /** @param list<array{label:string,value:string,metadata?:array<string,mixed>}> $options */
    private function save(array $options = [['label' => 'A', 'value' => 'a'], ['label' => 'B', 'value' => 'b']], int $maxChoices = 1): string
    {
        return app(SaveEventPoll::class)->handle($this->actor, $this->occurrence, 'contract', EventPollType::Choice,
            question: 'Choose', status: EventPollStatus::Open, options: $options, maxChoices: $maxChoices,
            closesAt: CarbonImmutable::now('UTC')->addDay(), settings: ['deadline_reminder_minutes' => 10080]);
    }

    /** @return array<string,list<string>> */
    private function state(): array
    {
        $state = [];
        foreach (['event_polls', 'event_poll_options', 'event_poll_votes', 'audit_events', 'outbox_messages'] as $table) {
            $state[$table] = array_values(DB::table($table)->orderBy('id')->get()->map(static fn (object $row): string => json_encode($row, JSON_THROW_ON_ERROR))->all());
        }

        return $state;
    }
}
