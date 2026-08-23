<?php

declare(strict_types=1);

namespace Tests\v3\Contexts\Operations\Participation;

use App\Contexts\Operations\Events\Actions\CreateEvent;
use App\Contexts\Operations\Events\Enums\EventScope;
use App\Contexts\Operations\Events\Models\EventTypeScope;
use App\Contexts\Operations\Participation\Enums\EventAttendanceStatus;
use App\Contexts\Operations\Participation\Models\EventAttendance;
use App\Contexts\Operations\Participation\Queries\BearHuntAttendanceSummaryQuery;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\v3\Support\ScenarioFactory;
use Tests\v3\TestCase;

final class BearHuntAttendanceSummaryV3Test extends TestCase
{
    use RefreshDatabase;

    public function test_summary_preserves_present_absent_excused_and_unknown_as_recorded_facts(): void
    {
        $scenario = new ScenarioFactory;
        $account = $scenario->authUser();
        $actor = $scenario->player((int) $account->id, 61301);
        $alliance = $scenario->alliance($actor);
        $scenario->roster($actor, $alliance);
        $players = [$actor];
        for ($index = 0; $index < 3; $index++) {
            $player = $scenario->unclaimedPlayer(61301);
            $scenario->roster($actor, $alliance, $player);
            $players[] = $player;
        }

        $configuration = EventTypeScope::query()
            ->where('scope', EventScope::Alliance->value)
            ->whereHas('eventType', static fn ($query) => $query->where('slug', 'bear-hunt'))
            ->firstOrFail();
        $created = app(CreateEvent::class)->handle(
            actorPlayerId: $actor->playerId,
            configurationId: (string) $configuration->id,
            scope: EventScope::Alliance,
            targetId: $alliance->allianceId,
            firstLocalStart: CarbonImmutable::now('UTC'),
            title: 'Bear Hunt Attendance Fixture',
            durationMinutes: 30,
        );
        self::assertNotNull($created->firstOccurrenceId);

        foreach ([
            EventAttendanceStatus::Present,
            EventAttendanceStatus::Absent,
            EventAttendanceStatus::Excused,
            EventAttendanceStatus::Unknown,
        ] as $index => $status) {
            EventAttendance::query()->create([
                'occurrence_id' => $created->firstOccurrenceId,
                'player_id' => $players[$index]->playerId,
                'status' => $status,
                'recorded_by_player_id' => $actor->playerId,
                'recorded_at' => now(),
            ]);
        }

        $summary = app(BearHuntAttendanceSummaryQuery::class)->forOccurrence($created->firstOccurrenceId);

        self::assertTrue($summary['available']);
        self::assertSame(4, $summary['total']);
        self::assertSame(1, $summary['byStatus']['present']);
        self::assertSame(1, $summary['byStatus']['absent']);
        self::assertSame(1, $summary['byStatus']['excused']);
        self::assertSame(1, $summary['byStatus']['unknown']);
        self::assertSame(50.0, $summary['ratePercent']);
        self::assertSame('present', $summary['players'][$players[0]->playerId]['status']);
        self::assertSame('absent', $summary['players'][$players[1]->playerId]['status']);
        self::assertSame('excused', $summary['players'][$players[2]->playerId]['status']);
        self::assertSame('unknown', $summary['players'][$players[3]->playerId]['status']);
    }

    public function test_no_attendance_rows_is_not_recorded_instead_of_zero_percent(): void
    {
        $summary = app(BearHuntAttendanceSummaryQuery::class)->forOccurrence((string) \Illuminate\Support\Str::ulid());

        self::assertFalse($summary['available']);
        self::assertSame(0, $summary['total']);
        self::assertNull($summary['ratePercent']);
        self::assertSame([], $summary['players']);
    }
}
