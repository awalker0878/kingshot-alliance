<?php

declare(strict_types=1);

namespace Tests\ReadModels\EventAnalysis\Feature;

use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;
use App\Contexts\Operations\Events\Actions\CreateEvent;
use App\Contexts\Operations\Events\Enums\EventScope;
use App\Contexts\Operations\Events\Models\EventOccurrence;
use App\Contexts\Operations\Events\Models\EventTypeScope;
use App\Contexts\Operations\Participation\Models\EventAttendance;
use App\Contexts\Operations\Rallies\Models\RallyAssignment;
use App\Contexts\Operations\Results\Actions\RecordBearHuntBattleReport;
use App\Contexts\Operations\Results\Models\EventPlayerResult;
use App\Contexts\Operations\Results\Queries\BearHuntDebriefResultQuery;
use App\ReadModels\EventAnalysis\Queries\BearHuntDebriefQuery;
use App\ReadModels\EventAnalysis\Queries\BearHuntRunHistoryQuery;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\ScenarioFactory;
use Tests\TestCase;

final class BearHuntDebriefPagesTest extends TestCase
{
    use RefreshDatabase;

    private PlayerReference $actor;

    private EventOccurrence $occurrence;

    private string $allianceId;

    protected function setUp(): void
    {
        parent::setUp();
        $scenario = app(ScenarioFactory::class);
        $user = $scenario->authUser();
        $user->forceFill(['email_verified_at' => now()])->save();
        $this->actor = $scenario->player((int) $user->id, 62735);
        $alliance = $scenario->alliance($this->actor);
        $this->allianceId = $alliance->allianceId;
        $scenario->roster($this->actor, $alliance);
        $scope = EventTypeScope::query()->where('scope', EventScope::Alliance->value)
            ->whereHas('eventType', static fn ($query) => $query->where('slug', 'bear-hunt'))->firstOrFail();
        $created = app(CreateEvent::class)->handle($this->actor->playerId, (string) $scope->id, EventScope::Alliance,
            $this->allianceId, CarbonImmutable::now('UTC')->subHour(), durationMinutes: 30);
        $this->occurrence = EventOccurrence::query()->findOrFail($created->firstOccurrenceId);
        $this->actingAs($user)->withSession([(string) config('game_world.active_player_session_key') => $this->actor->playerId]);
    }

    public function test_every_result_remains_reachable_across_rank_score_ties_nulls_and_an_insertion_frontier(): void
    {
        $ids = $this->seedResults(81, ties: true);
        $expected = DB::table('event_player_results')->orderByRaw('rank IS NULL')->orderBy('rank')
            ->orderByDesc('score')->orderBy('player_id')->pluck('player_id')->all();
        $query = app(BearHuntDebriefResultQuery::class);
        $page = $query->forOccurrence((string) $this->occurrence->id, $this->actor->playerId);
        self::assertSame(81, $page['governorCount']);
        $seen = array_column($page['governors'], 'playerId');
        $extra = app(ScenarioFactory::class)->unclaimedPlayer(62735);
        EventPlayerResult::query()->create(['occurrence_id' => $this->occurrence->id, 'player_id' => $extra->playerId,
            'score' => 100, 'rank' => 1, 'recorded_at' => now()]);
        while ($page['governorPage']['hasMore']) {
            $page = $query->forOccurrence((string) $this->occurrence->id, $this->actor->playerId, $page['governorPage']['nextCursor']);
            self::assertLessThanOrEqual(25, count($page['governors']));
            $seen = [...$seen, ...array_column($page['governors'], 'playerId')];
        }
        self::assertSame($expected, $seen);
        self::assertEqualsCanonicalizing($ids, $seen);
        self::assertSame(82, $query->forOccurrence((string) $this->occurrence->id, $this->actor->playerId)['governorCount']);
    }

    public function test_large_results_attendance_and_rallies_compose_exact_summaries_without_history_hydration(): void
    {
        $ids = $this->seedResults(1001);
        $attendance = $assignments = [];
        $group = strtolower((string) Str::ulid());
        DB::table('rally_groups')->insert(['id' => $group, 'occurrence_id' => $this->occurrence->id,
            'alliance_id' => $this->allianceId, 'name' => 'Retained Rally']);
        foreach ($ids as $i => $id) {
            $attendance[] = ['id' => strtolower((string) Str::ulid()), 'occurrence_id' => $this->occurrence->id,
                'player_id' => $id, 'status' => ['present', 'absent', 'excused', 'unknown'][$i % 4], 'recorded_at' => now()];
            $assignments[] = ['id' => strtolower((string) Str::ulid()), 'rally_group_id' => $group,
                'player_id' => $id, 'role' => $i % 3 === 0 ? 'lead' : 'joiner',
                'status' => $i % 2 === 0 ? 'participated' : 'absent', 'assigned_at' => now(), 'recorded_at' => now()];
        }
        DB::table('event_attendance')->insert($attendance);
        DB::table('rally_assignments')->insert($assignments);
        $results = $attendanceRows = $rallyRows = 0;
        EventPlayerResult::retrieved(static function () use (&$results): void {
            $results++;
        });
        EventAttendance::retrieved(static function () use (&$attendanceRows): void {
            $attendanceRows++;
        });
        RallyAssignment::retrieved(static function () use (&$rallyRows): void {
            $rallyRows++;
        });
        $debrief = app(BearHuntDebriefQuery::class)->forOccurrence($this->occurrence, $this->actor, false);
        self::assertCount(25, $debrief['governors']);
        self::assertSame(1001, $debrief['summary']['governorCount']);
        self::assertSame(10010, $debrief['summary']['totalDamage']);
        self::assertSame(1001, $debrief['summary']['attendance']['total']);
        self::assertSame(['present' => 251, 'absent' => 250, 'excused' => 250, 'unknown' => 250], $debrief['summary']['attendance']['byStatus']);
        self::assertSame(501, $debrief['summary']['rallies']['participated']);
        self::assertSame(167, $debrief['summary']['rallies']['led']);
        self::assertSame(334, $debrief['summary']['rallies']['joined']);
        self::assertSame($this->actor->playerId, $debrief['personal']['result']['playerId']);
        self::assertSame(1001, $debrief['personal']['result']['rank']);
        self::assertSame('present', $debrief['personal']['attendanceStatus']);
        self::assertSame(1, $debrief['personal']['rallies']['participated']);
        self::assertSame(27, $results);
        self::assertSame(26, $attendanceRows);
        self::assertSame(0, $rallyRows);
        self::assertSame(10010, $debrief['runs'][0]['totalDamage']);
        self::assertSame(501, $debrief['runs'][0]['rallies']['participated']);
        self::assertSame(1001, $debrief['runs'][0]['attendance']['total']);
    }

    public function test_exact_scores_above_php_and_json_integer_ranges_survive_current_and_history_projections(): void
    {
        $this->seedResults(2);
        DB::table('event_player_results')->update(['score' => PHP_INT_MAX]);
        $debrief = app(BearHuntDebriefQuery::class)->forOccurrence($this->occurrence, $this->actor, false);
        self::assertSame('18446744073709551614', $debrief['summary']['totalDamage']);
        self::assertSame('18446744073709551614', $debrief['runs'][0]['totalDamage']);
        self::assertSame('9223372036854775807', $debrief['personal']['result']['damage']);
        self::assertSame('9223372036854775807', $debrief['personalTrend'][0]['damage']);
        $this->get('/events/'.$this->occurrence->id.'/debrief')->assertOk()->assertInertia(fn (Assert $page) => $page
            ->where('debrief.summary.totalDamage', '18446744073709551614')
            ->where('debrief.personal.result.damage', '9223372036854775807'));
    }

    public function test_comparison_preserves_unit_differences_and_large_deltas_above_the_safe_number_range(): void
    {
        $this->seedResults(1);
        DB::table('event_player_results')->update(['score' => 9007199254740993]);
        $previous = EventOccurrence::query()->create(['event_id' => $this->occurrence->event_id,
            'starts_at' => now()->subDays(2), 'ends_at' => now()->subDays(2)->addHour(), 'status' => 'completed']);
        EventPlayerResult::query()->create(['occurrence_id' => $previous->id, 'player_id' => $this->actor->playerId,
            'score' => 9007199254740992, 'rank' => 1, 'recorded_at' => now()]);
        $query = app(BearHuntDebriefQuery::class);
        $debrief = $query->forOccurrence($this->occurrence, $this->actor, false);
        self::assertSame(1, $debrief['comparison']['personalDamage']['delta']);
        self::assertSame('increased', $debrief['signals']['personalDamage']);
        DB::table('event_player_results')->where('occurrence_id', $previous->id)->update(['score' => 0]);
        $debrief = $query->forOccurrence($this->occurrence, $this->actor, false);
        self::assertSame('9007199254740993', $debrief['comparison']['allianceDamage']['delta']);
        self::assertSame('previous_zero', $debrief['comparison']['allianceDamage']['state']);
    }

    public function test_accepted_personal_best_compares_adjacent_wide_integers_exactly(): void
    {
        $this->seedResults(1);
        DB::table('event_player_results')->update(['score' => 9007199254740993]);
        $previous = EventOccurrence::query()->create(['event_id' => $this->occurrence->event_id,
            'starts_at' => now()->subDays(2), 'ends_at' => now()->subDays(2)->addHour(), 'status' => 'completed']);
        EventPlayerResult::query()->create(['occurrence_id' => $previous->id, 'player_id' => $this->actor->playerId,
            'score' => 9007199254740992, 'rank' => 1, 'recorded_at' => now()]);
        foreach ([$this->occurrence, $previous] as $occurrence) {
            app(RecordBearHuntBattleReport::class)->handle($this->actor->playerId, (string) $occurrence->id,
                (string) Str::ulid(), (string) Str::ulid(), hash('sha256', 'exact-best-key-'.$occurrence->id),
                hash('sha256', 'exact-best-report-'.$occurrence->id), null,
                [['player_id' => $this->actor->playerId, 'reported_rank' => 1, 'damage_points' => 0]]);
        }
        $debrief = app(BearHuntDebriefQuery::class)->forOccurrence($this->occurrence, $this->actor, false);
        self::assertTrue($debrief['signals']['acceptedResult']);
        self::assertTrue($debrief['signals']['newPersonalBest']);
        self::assertSame(1, $debrief['comparison']['personalDamage']['delta']);
    }

    public function test_empty_later_page_keeps_complete_summary_and_independent_personal_result(): void
    {
        $this->seedResults(26);
        $query = app(BearHuntDebriefResultQuery::class);
        $first = $query->forOccurrence((string) $this->occurrence->id, $this->actor->playerId);
        DB::table('event_player_results')->where('player_id', $this->actor->playerId)->update(['rank' => 1]);
        $page = $query->forOccurrence((string) $this->occurrence->id, $this->actor->playerId, $first['governorPage']['nextCursor']);
        self::assertSame([], $page['governors']);
        self::assertSame(26, $page['governorCount']);
        self::assertSame($this->actor->playerId, $page['personal']['playerId']);
        self::assertFalse($page['governorPage']['isFirstPage']);
    }

    public function test_cursor_scope_and_current_authority_apply_on_every_http_page(): void
    {
        $this->seedResults(30);
        $query = app(BearHuntDebriefResultQuery::class);
        $first = $query->forOccurrence((string) $this->occurrence->id, $this->actor->playerId);
        $cursor = $first['governorPage']['nextCursor'];
        foreach ([[strtolower((string) Str::ulid()), $this->actor->playerId], [(string) $this->occurrence->id, strtolower((string) Str::ulid())]] as [$occurrenceId, $actor]) {
            try {
                $query->forOccurrence($occurrenceId, $actor, $cursor);
                self::fail('A Debrief cursor cannot cross actor or occurrence scope.');
            } catch (ValidationException $exception) {
                self::assertArrayHasKey('cursor', $exception->errors());
            }
        }
        $url = '/events/'.$this->occurrence->id.'/debrief?governor_cursor='.urlencode($cursor);
        $this->get($url)->assertOk()->assertInertia(fn (Assert $page) => $page->has('debrief.governors', 5)
            ->where('debrief.summary.governorCount', 30)->where('debrief.personal.result.rank', 30));
        DB::table('alliance_memberships')->where('player_id', $this->actor->playerId)->update(['status' => 'left']);
        $this->get($url)->assertForbidden();
    }

    public function test_history_rejects_oversized_raw_input_instead_of_silently_dropping_runs(): void
    {
        $this->expectException(ValidationException::class);
        app(BearHuntRunHistoryQuery::class)->forOccurrences(array_fill(0, 25, (string) $this->occurrence->id), $this->actor->playerId);
    }

    /** @return list<string> */
    private function seedResults(int $count, bool $ties = false): array
    {
        $players = [];
        for ($i = 0; $i < $count - 1; $i++) {
            $players[] = ['id' => strtolower((string) Str::ulid()), 'current_kingdom_id' => $this->actor->kingdomId,
                'current_name' => 'Debrief Governor '.$i];
        }
        if ($players !== []) {
            DB::table('players')->insert($players);
        }
        $ids = [...array_column($players, 'id'), $this->actor->playerId];
        $results = [];
        foreach ($ids as $i => $id) {
            $results[] = ['id' => strtolower((string) Str::ulid()), 'occurrence_id' => $this->occurrence->id,
                'player_id' => $id, 'score' => $ties ? $i % 7 : 10,
                'rank' => $ties ? ($i % 3 === 0 ? null : $i % 5 + 1) : $i + 1, 'recorded_at' => now()];
        }
        DB::table('event_player_results')->insert($results);

        return $ids;
    }
}
