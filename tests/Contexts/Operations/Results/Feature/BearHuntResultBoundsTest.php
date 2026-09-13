<?php

declare(strict_types=1);

namespace Tests\Contexts\Operations\Results\Feature;

use App\Contexts\Operations\Events\Actions\CreateEvent;
use App\Contexts\Operations\Events\Enums\EventScope;
use App\Contexts\Operations\Events\Models\EventTypeScope;
use App\Contexts\Operations\Results\Actions\RecordBearHuntBattleReport;
use App\Contexts\Operations\Results\Actions\RemoveBearHuntBattleReport;
use App\Contexts\Operations\Results\Models\EventPlayerResult;
use App\Contexts\Operations\Results\Services\BearHuntResultProjector;
use App\Contexts\Operations\Results\ValueObjects\BearHuntBattleReportReceipt;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\ScenarioFactory;
use Tests\TestCase;

final class BearHuntResultBoundsTest extends TestCase
{
    use RefreshDatabase;

    private string $actor;

    private string $kingdom;

    private string $occurrence;

    protected function setUp(): void
    {
        parent::setUp();
        $factory = app(ScenarioFactory::class);
        $actor = $factory->player((int) $factory->authUser()->id, 61732);
        $alliance = $factory->alliance($actor);
        $factory->roster($actor, $alliance);
        $configuration = EventTypeScope::query()->where('scope', EventScope::Alliance->value)
            ->whereHas('eventType', static fn ($query) => $query->where('slug', 'bear-hunt'))->firstOrFail();
        $event = app(CreateEvent::class)->handle($actor->playerId, (string) $configuration->id,
            EventScope::Alliance, $alliance->allianceId, CarbonImmutable::now('UTC')->addDay(), durationMinutes: 60);
        $this->actor = $actor->playerId;
        $this->kingdom = $actor->kingdomId;
        $this->occurrence = $event->firstOccurrenceId ?? throw new \LogicException;
    }

    public function test_full_work_budget_has_grouped_queries_complete_ranks_and_a_report_scoped_receipt(): void
    {
        $this->history(1000, true, 10);
        DB::enableQueryLog();
        DB::flushQueryLog();
        try {
            $receipt = $this->record('bounded', 5);
            $queries = DB::getQueryLog();
        } finally {
            DB::disableQueryLog();
            DB::flushQueryLog();
        }
        self::assertLessThan(75, count($queries), 'Query count must not grow once per retained Governor.');
        self::assertCount(1, array_filter($queries, static fn (array $query): bool => str_contains(strtolower($query['query']), 'sum(entry.damage_points)')));
        self::assertSame(1000, EventPlayerResult::query()->where('occurrence_id', $this->occurrence)->count());
        self::assertSame(999, EventPlayerResult::query()->where('occurrence_id', $this->occurrence)->where('rank', 2)->count());
        self::assertSame([['playerId' => $this->actor, 'score' => 15, 'rank' => 1]], $receipt->playerResults);
        self::assertSame(1, $receipt->entryCount);
        self::assertLessThan(1024, strlen(json_encode($receipt->toArray(), JSON_THROW_ON_ERROR)));
        $fullReceipt = app(RecordBearHuntBattleReport::class)->handle($this->actor, $this->occurrence,
            (string) Str::ulid(), (string) Str::ulid(), hash('sha256', 'history-0'), hash('sha256', 'history-0'), null,
            [['player_id' => $this->actor, 'damage_points' => 999]]);
        self::assertTrue($fullReceipt->idempotentReplay);
        self::assertSame(100, $fullReceipt->entryCount);
        self::assertCount(100, $fullReceipt->playerResults);
        self::assertSame($this->actor, $fullReceipt->playerResults[0]['playerId']);
        $replay = $this->record('bounded', 999);
        self::assertTrue($replay->idempotentReplay);
        self::assertSame($receipt->playerResults, $replay->playerResults);
        app(RemoveBearHuntBattleReport::class)->handle($this->actor, $receipt->reportId, 'Corrected report');
        self::assertSame(1000, EventPlayerResult::query()->where('occurrence_id', $this->occurrence)->where('rank', 1)->where('score', 10)->count());
        $removedReplay = $this->record('bounded', 999);
        self::assertSame([['playerId' => $this->actor, 'score' => 10, 'rank' => 1]], $removedReplay->playerResults);
    }

    public function test_admission_beyond_the_atomic_work_budget_rolls_back_every_owner_effect(): void
    {
        $this->history(1000, false, 10);
        $before = $this->state();
        try {
            $this->record('over-budget', 5);
            self::fail('A new distinct Governor must not exceed the bounded occurrence contract.');
        } catch (ValidationException $exception) {
            self::assertStringContainsString('1000', $exception->errors()['entries'][0]);
        }
        self::assertSame($before, $this->state());
        self::assertFalse(DB::table('bear_hunt_result_baselines')->where('occurrence_id', $this->occurrence)->where('player_id', $this->actor)->exists());
    }

    public function test_competition_ties_zero_scores_and_manual_baseline_restoration_preserve_result_identity(): void
    {
        $players = $this->history(3, true, 0);
        DB::table('bear_hunt_battle_report_entries')->whereIn('player_id', [$players[0], $players[1]])->update(['damage_points' => 100]);
        $manual = EventPlayerResult::query()->create([
            'occurrence_id' => $this->occurrence, 'player_id' => $this->actor,
            'score' => 0, 'rank' => 7, 'outcome' => 'Reviewed', 'notes' => 'Preserve manual notes', 'recorded_at' => now(),
        ]);
        DB::table('bear_hunt_result_baselines')->where('occurrence_id', $this->occurrence)->where('player_id', $this->actor)
            ->update(['source_event_player_result_id' => $manual->id, 'baseline_score' => 0, 'baseline_rank' => 7]);
        $receipt = $this->record('ties', 0);
        self::assertSame([['playerId' => $this->actor, 'score' => 100, 'rank' => 1]], $receipt->playerResults);
        self::assertSame(2, EventPlayerResult::query()->where('occurrence_id', $this->occurrence)->where('rank', 1)->count());
        self::assertSame(3, EventPlayerResult::query()->where('occurrence_id', $this->occurrence)->where('player_id', $players[2])->value('rank'));
        $manual->refresh();
        self::assertSame('Reviewed', $manual->outcome);
        self::assertSame('Preserve manual notes', $manual->notes);
        $reportIds = DB::table('bear_hunt_battle_reports')->where('occurrence_id', $this->occurrence)->pluck('id')->all();
        foreach ($reportIds as $id) {
            app(RemoveBearHuntBattleReport::class)->handle($this->actor, (string) $id, 'Remove all imported results');
        }
        $manual->refresh();
        self::assertSame(0, $manual->score);
        self::assertSame(7, $manual->rank);
        self::assertSame('Preserve manual notes', $manual->notes);
        self::assertSame(2, EventPlayerResult::query()->where('occurrence_id', $this->occurrence)->whereNull('score')->whereNull('rank')->count());
    }

    /** @return iterable<string,array{int,int}> */
    public static function overflows(): iterable
    {
        yield 'accepted history sum' => [PHP_INT_MAX, 0];
        yield 'baseline plus accepted damage' => [1, PHP_INT_MAX];
    }

    #[DataProvider('overflows')]
    public function test_unrepresentable_totals_reject_without_saturation_or_partial_effects(int $damage, int $baseline): void
    {
        $this->history(1, true, $damage);
        DB::table('bear_hunt_result_baselines')->where('occurrence_id', $this->occurrence)->update(['baseline_score' => $baseline]);
        $before = $this->state();
        try {
            $this->record('overflow', 1);
            self::fail('Integer overflow must reject the owner transaction.');
        } catch (ValidationException $exception) {
            self::assertStringContainsString('integer range', $exception->errors()['entries'][0]);
        }
        self::assertSame($before, $this->state());
    }

    public function test_an_oversized_stored_report_rolls_back_reprojection_audit_and_outbox_on_replay(): void
    {
        $this->history(101, true, 10);
        $first = (string) DB::table('bear_hunt_battle_reports')->where('idempotency_key', hash('sha256', 'history-0'))->value('id');
        DB::table('bear_hunt_battle_report_entries')->update(['report_id' => $first]);
        $before = $this->state();
        try {
            app(RecordBearHuntBattleReport::class)->handle($this->actor, $this->occurrence,
                (string) Str::ulid(), (string) Str::ulid(), hash('sha256', 'history-0'), hash('sha256', 'history-0'), null,
                [['player_id' => $this->actor, 'damage_points' => 10]]);
            self::fail('A corrupt stored report must not create a truncated receipt.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey('report', $exception->errors());
        }
        self::assertSame($before, $this->state());
    }

    public function test_the_projector_requires_an_owner_transaction(): void
    {
        // RefreshDatabase owns one test transaction; use a clean named connection to check the actual guard.
        config()->set('database.connections.result_guard', [...DB::connection()->getConfig(), 'name' => 'result_guard']);
        $original = DB::getDefaultConnection();
        DB::setDefaultConnection('result_guard');
        try {
            $this->expectException(\LogicException::class);
            app(BearHuntResultProjector::class)->recompute($this->occurrence, $this->actor);
        } finally {
            DB::setDefaultConnection($original);
            DB::purge('result_guard');
        }
    }

    private function record(string $key, int $damage): BearHuntBattleReportReceipt
    {
        return app(RecordBearHuntBattleReport::class)->handle($this->actor, $this->occurrence,
            (string) Str::ulid(), (string) Str::ulid(), hash('sha256', $key), hash('sha256', 'report-'.$key), null,
            [['player_id' => $this->actor, 'damage_points' => $damage]]);
    }

    /** @return list<string> */
    private function history(int $count, bool $includeActor, int $damage): array
    {
        $ids = [];
        $players = [];
        $baselines = [];
        for ($i = 0; $i < $count; $i++) {
            $id = $includeActor && $i === 0 ? $this->actor : (string) Str::ulid();
            $ids[] = $id;
            if ($id !== $this->actor) {
                $players[] = ['id' => $id, 'current_kingdom_id' => $this->kingdom, 'current_name' => 'Retained Governor '.$i];
            }
            $baselines[] = ['id' => (string) Str::ulid(), 'occurrence_id' => $this->occurrence, 'player_id' => $id, 'captured_at' => now()];
        }
        foreach (array_chunk($players, 100) as $batch) {
            DB::table('players')->insert($batch);
        }
        foreach (array_chunk($baselines, 100) as $batch) {
            DB::table('bear_hunt_result_baselines')->insert($batch);
        }
        foreach (array_chunk($ids, 100) as $page => $chunk) {
            $report = (string) Str::ulid();
            DB::table('bear_hunt_battle_reports')->insert([
                'id' => $report, 'occurrence_id' => $this->occurrence, 'source_evidence_id' => (string) Str::ulid(),
                'source_commit_attempt_id' => (string) Str::ulid(), 'idempotency_key' => hash('sha256', 'history-'.$page),
                'report_fingerprint' => hash('sha256', 'history-'.$page), 'status' => 'accepted',
                'recorded_by_player_id' => $this->actor, 'recorded_at' => now(),
            ]);
            DB::table('bear_hunt_battle_report_entries')->insert(array_map(static fn (string $id): array => [
                'id' => (string) Str::ulid(), 'report_id' => $report, 'player_id' => $id, 'damage_points' => $damage,
            ], $chunk));
        }

        return $ids;
    }

    /** @return array<string,list<string>> */
    private function state(): array
    {
        $state = [];
        foreach (['bear_hunt_battle_reports', 'bear_hunt_battle_report_entries', 'bear_hunt_result_baselines',
            'event_player_results', 'event_player_contexts', 'audit_events', 'outbox_messages'] as $table) {
            $state[$table] = array_values(DB::table($table)->orderBy('id')->get()->map(static fn (object $row): string => json_encode($row, JSON_THROW_ON_ERROR))->all());
        }

        return $state;
    }
}
