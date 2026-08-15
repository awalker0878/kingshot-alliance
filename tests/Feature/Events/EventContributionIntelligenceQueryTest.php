<?php

declare(strict_types=1);

namespace Tests\Feature\Events;

use App\Domain\Alliances\Actions\CreateAlliance;
use App\Domain\Alliances\Models\Alliance;
use App\Domain\Events\Actions\CreateEvent;
use App\Domain\Events\Actions\RecordEventAttendance;
use App\Domain\Events\Actions\SaveEventPlayerResult;
use App\Domain\Events\Enums\EventAttendanceStatus;
use App\Domain\Events\Enums\EventScope;
use App\Domain\Events\Models\EventType;
use App\Domain\Events\Queries\EventContributionIntelligenceQuery;
use App\Domain\Events\Services\EventTypeRegistry;
use App\Contexts\Accounts\Models\User;
use App\Domain\Kingdoms\Actions\SaveRosterEntry;
use App\Contexts\GameWorld\Models\Kingdom;
use App\Contexts\GameWorld\Models\Player;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class EventContributionIntelligenceQueryTest extends TestCase
{
    use RefreshDatabase;

    public function test_alliance_intelligence_compares_only_compatible_event_type_scope_metrics(): void
    {
        [$leader, $member, $alliance] = $this->context();
        $create = $this->app->make(CreateEvent::class);
        $save = $this->app->make(SaveEventPlayerResult::class);
        $registry = $this->app->make(EventTypeRegistry::class);

        $bearType = EventType::query()->where('slug', 'bear-hunt')->sole();
        $bear = $registry->scope($bearType, EventScope::Alliance);

        $first = $create->handle(
            actor: $leader,
            configuration: $bear,
            target: $alliance,
            firstLocalStart: CarbonImmutable::now('UTC')->subDays(3),
            durationMinutes: 30,
        );
        $save->handle($leader, $first->occurrences->firstOrFail(), $leader, score: 1000, metrics: [['key' => 'rallies_joined', 'value' => 2]]);
        $save->handle($leader, $first->occurrences->firstOrFail(), $member, score: 800, metrics: [['key' => 'rallies_joined', 'value' => 4]]);

        $second = $create->handle(
            actor: $leader,
            configuration: $bear,
            target: $alliance,
            firstLocalStart: CarbonImmutable::now('UTC')->subDays(2),
            durationMinutes: 30,
        );
        $save->handle($leader, $second->occurrences->firstOrFail(), $leader, score: 1200, metrics: [['key' => 'rallies_joined', 'value' => 3]]);
        $save->handle($leader, $second->occurrences->firstOrFail(), $member, score: 900, metrics: [['key' => 'rallies_joined', 'value' => 1]]);

        $vikingType = EventType::query()->where('slug', 'viking-vengeance')->sole();
        $viking = $registry->scope($vikingType, EventScope::Alliance);
        $third = $create->handle(
            actor: $leader,
            configuration: $viking,
            target: $alliance,
            firstLocalStart: CarbonImmutable::now('UTC')->subDay(),
            durationMinutes: 30,
        );
        $save->handle($leader, $third->occurrences->firstOrFail(), $leader, score: 99999, metrics: [['key' => 'waves_defended', 'value' => 20]]);

        $intelligence = $this->app->make(EventContributionIntelligenceQuery::class)->forAlliance($leader, $alliance);
        $series = collect($intelligence['series']);

        $bearScore = $series->first(static fn (array $row): bool => $row['eventTypeSlug'] === 'bear-hunt' && $row['metricKey'] === 'score');
        $vikingScore = $series->first(static fn (array $row): bool => $row['eventTypeSlug'] === 'viking-vengeance' && $row['metricKey'] === 'score');
        self::assertIsArray($bearScore);
        self::assertIsArray($vikingScore);
        self::assertNotSame($bearScore['eventTypeScopeId'], $vikingScore['eventTypeScopeId']);
        self::assertSame(4, $bearScore['samples']);
        self::assertSame(975.0, $bearScore['average']);
        self::assertSame(99999.0, $vikingScore['average']);

        $bearBoard = collect($intelligence['leaderboards'])
            ->first(static fn (array $row): bool => $row['eventTypeSlug'] === 'bear-hunt' && $row['metricKey'] === 'rallies_joined');
        self::assertIsArray($bearBoard);
        self::assertSame('sum', $bearBoard['aggregation']);
        self::assertSame((string) $leader->id, $bearBoard['entries'][0]['playerId']);
        self::assertSame(5.0, $bearBoard['entries'][0]['value']);
        self::assertSame((string) $member->id, $bearBoard['entries'][1]['playerId']);
        self::assertSame(5.0, $bearBoard['entries'][1]['value']);
    }

    public function test_player_intelligence_keeps_scope_specific_series_and_has_no_organization_leaderboard(): void
    {
        [$leader, , $alliance] = $this->context();
        $type = EventType::query()->where('slug', 'bear-hunt')->sole();
        $configuration = $this->app->make(EventTypeRegistry::class)->scope($type, EventScope::Alliance);
        $event = $this->app->make(CreateEvent::class)->handle(
            actor: $leader,
            configuration: $configuration,
            target: $alliance,
            firstLocalStart: CarbonImmutable::now('UTC')->subDay(),
            durationMinutes: 30,
        );
        $occurrence = $event->occurrences->firstOrFail();
        $this->app->make(SaveEventPlayerResult::class)->handle(
            $leader,
            $occurrence,
            $leader,
            outcome: 'completed',
            score: 500,
            metrics: [['key' => 'rallies_joined', 'value' => 2]],
        );
        $this->app->make(RecordEventAttendance::class)->handle(
            $leader,
            $occurrence,
            $leader,
            EventAttendanceStatus::Present,
        );

        $intelligence = $this->app->make(EventContributionIntelligenceQuery::class)->forPlayer($leader);

        self::assertSame('player', $intelligence['scope']);
        self::assertSame(1, $intelligence['participation']['events']);
        self::assertSame(1, $intelligence['participation']['completed']);
        self::assertSame([], $intelligence['leaderboards']);
        self::assertCount(2, $intelligence['series']);
        self::assertSame(['rallies_joined', 'score'], collect($intelligence['series'])->pluck('metricKey')->sort()->values()->all());
    }

    /** @return array{Player, Player, Alliance} */
    private function context(): array
    {
        $kingdom = Kingdom::query()->create(['number' => random_int(9000, 9099), 'status' => 'active']);
        $leader = Player::query()->create([
            'user_id' => User::factory()->create()->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => 'intel-leader-'.random_int(100000, 999999),
            'current_name' => 'Intel Leader',
        ]);
        $member = Player::query()->create([
            'user_id' => User::factory()->create()->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => 'intel-member-'.random_int(100000, 999999),
            'current_name' => 'Intel Member',
        ]);
        $alliance = $this->app->make(CreateAlliance::class)->handle($leader, 'Intel Alliance', 'intel-'.random_int(100000, 999999));
        $saveRoster = $this->app->make(SaveRosterEntry::class);
        $saveRoster->handle($alliance, $leader, ['name' => 'Intel Leader', 'game_player_id' => $leader->game_player_id], expectedPlayerId: (string) $leader->id);
        $saveRoster->handle($alliance, $leader, ['name' => 'Intel Member', 'game_player_id' => $member->game_player_id], expectedPlayerId: (string) $member->id);

        return [$leader, $member, $alliance];
    }
}
