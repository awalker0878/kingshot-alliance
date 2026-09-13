<?php

declare(strict_types=1);

namespace Tests\Contexts\Operations\Events\Feature;

use App\Contexts\Operations\Events\Actions\CreateEvent;
use App\Contexts\Operations\Events\Enums\EventScope;
use App\Contexts\Operations\Events\Models\EventTypeScope;
use App\Contexts\Operations\Rallies\Actions\AssignRallyPlayer;
use App\Contexts\Operations\Rallies\Actions\SaveRallyGroup;
use App\Contexts\Operations\Rallies\Enums\RallyAssignmentRole;
use App\Contexts\Operations\Results\Actions\RecordBearHuntBattleReport;
use App\Contexts\Operations\Results\Actions\SaveEventPlayerResult;
use App\Contexts\Operations\Rosters\Actions\AssignEventRosterPlayer;
use App\Contexts\Operations\Rosters\Actions\SaveEventRoster;
use App\Contexts\Operations\Rosters\Enums\EventRosterType;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\ScenarioFactory;
use Tests\TestCase;

final class EventLinkedReferenceContentionTest extends TestCase
{
    use DatabaseTruncation;

    /** @return iterable<string,array{string,string}> */
    public static function commands(): iterable
    {
        yield 'Rally assignment' => ['rally', 'rally_assignments'];
        yield 'Event roster assignment' => ['roster', 'event_roster_members'];
        yield 'Player result' => ['result', 'event_player_results'];
        yield 'Bear Hunt report' => ['report', 'bear_hunt_battle_reports'];
    }

    #[DataProvider('commands')]
    public function test_contended_linked_governor_rejects_atomically_and_current_retry_succeeds(string $command, string $table): void
    {
        $factory = app(ScenarioFactory::class);
        $actor = $factory->player((int) $factory->authUser()->id, 61647);
        $target = $factory->unclaimedPlayer(61647);
        $alliance = $factory->alliance($actor);
        $factory->roster($actor, $alliance, $target);
        $configuration = EventTypeScope::query()->where('scope', EventScope::Alliance->value)
            ->whereHas('eventType', static fn ($query) => $query->where('slug', 'bear-hunt'))->firstOrFail();
        $event = app(CreateEvent::class)->handle($actor->playerId, (string) $configuration->id, EventScope::Alliance,
            $alliance->allianceId, CarbonImmutable::now('UTC')->addDay(), durationMinutes: 60);
        $occurrence = $event->firstOccurrenceId ?? throw new \LogicException;
        app(SaveRallyGroup::class)->handle($actor->playerId, $occurrence, $alliance->allianceId, 'Reference contention');
        $group = (string) DB::table('rally_groups')->where('occurrence_id', $occurrence)->value('id');
        app(SaveEventRoster::class)->handle($actor->playerId, $occurrence, 'reference-contention', EventRosterType::Roster, 'hardening', name: 'Reference contention');
        $roster = (string) DB::table('event_rosters')->where('occurrence_id', $occurrence)->where('key', 'reference-contention')->value('id');
        $source = strtolower((string) Str::ulid());
        $attempt = strtolower((string) Str::ulid());
        $run = static function () use ($command, $actor, $target, $occurrence, $group, $roster, $source, $attempt): void {
            match ($command) {
                'rally' => app(AssignRallyPlayer::class)->handle($actor->playerId, $occurrence, $group, $target->playerId, RallyAssignmentRole::Joiner),
                'roster' => app(AssignEventRosterPlayer::class)->handle($actor->playerId, $occurrence, $roster, $target->playerId),
                'result' => app(SaveEventPlayerResult::class)->handle($actor->playerId, $occurrence, $target->playerId, score: 100),
                'report' => app(RecordBearHuntBattleReport::class)->handle($actor->playerId, $occurrence, $source, $attempt,
                    hash('sha256', 'reference-report'), hash('sha256', 'reference-fingerprint'), null,
                    [['player_id' => $target->playerId, 'damage_points' => 100]]),
                default => throw new \LogicException,
            };
        };
        $before = $this->persistedState();
        $count = DB::table($table)->count();
        config()->set('database.connections.event_reference_competitor', [...DB::connection()->getConfig(), 'name' => 'event_reference_competitor']);
        $other = DB::connection('event_reference_competitor');
        $other->beginTransaction();
        $other->table('players')->where('id', $target->playerId)->lockForUpdate()->first();
        DB::statement("SET lock_timeout = '150ms'");
        try {
            try {
                $run();
                self::fail('A changing lower owner reference must reject the whole Event command.');
            } catch (ValidationException $exception) {
                self::assertArrayHasKey('player', $exception->errors());
                self::assertStringContainsString('Retry', $exception->errors()['player'][0]);
            }
            self::assertSame($before, $this->persistedState());
            $other->commit();
            $run();
            self::assertSame($count + 1, DB::table($table)->count());
            self::assertGreaterThan(count($before['audit_events']), DB::table('audit_events')->count());
            self::assertGreaterThan(count($before['outbox_messages']), DB::table('outbox_messages')->count());
        } finally {
            DB::statement('RESET lock_timeout');
            while ($other->transactionLevel() > 0) {
                $other->rollBack();
            }
            DB::purge('event_reference_competitor');
        }
    }

    /** @return array<string,list<string>> */
    private function persistedState(): array
    {
        $state = [];
        foreach (['rally_assignments', 'event_roster_members', 'event_player_contexts', 'event_player_results',
            'event_player_result_metrics', 'bear_hunt_battle_reports', 'bear_hunt_battle_report_entries', 'bear_hunt_result_baselines', 'audit_events', 'outbox_messages'] as $table) {
            $state[$table] = array_values(DB::table($table)->orderBy('id')->get()->map(static fn (object $row): string => json_encode($row, JSON_THROW_ON_ERROR))->all());
        }

        return $state;
    }
}
