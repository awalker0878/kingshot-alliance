<?php

declare(strict_types=1);

namespace Tests\RewriteInput\Intelligence\EventAnalysis;

use App\Contexts\Accounts\Models\User;
use App\Contexts\Alliance\Core\Actions\CreateAlliance;
use App\Contexts\GameWorld\Models\Kingdom;
use App\Contexts\GameWorld\Models\Player;
use App\Contexts\Intelligence\EventAnalysis\Queries\EventPlayerIntelligenceQuery;
use App\Contexts\Operations\EventCore\Actions\CreateEvent;
use App\Contexts\Operations\EventCore\Enums\EventScope;
use App\Contexts\Operations\EventCore\Models\EventPlayerResult;
use App\Contexts\Operations\EventCore\Models\EventResult;
use App\Contexts\Operations\EventCore\Models\EventType;
use App\Contexts\Operations\EventCore\Services\EventTypeRegistry;
use App\Contexts\Operations\Participation\Actions\RecordEventAttendance;
use App\Contexts\Operations\Participation\Enums\EventAttendanceStatus;
use App\Contexts\Operations\Results\Actions\SaveEventPlayerResult;
use App\Contexts\Operations\Results\Actions\SaveEventResult;
use App\Contexts\Operations\Results\Queries\EventResultQuery;
use App\Domain\Kingdoms\Actions\SaveRosterEntry;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class EventResultsIntelligenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_one_user_can_have_independent_results_for_multiple_player_ids(): void
    {
        $owner = User::factory()->create();
        $kingdom = Kingdom::query()->create(['number' => 8601, 'status' => 'active']);
        $firstPlayer = Player::query()->create([
            'user_id' => $owner->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => '8601-a',
            'current_name' => 'Alpha',
        ]);
        $secondPlayer = Player::query()->create([
            'user_id' => $owner->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => '8601-b',
            'current_name' => 'Bravo',
        ]);
        $alliance = $this->app->make(CreateAlliance::class)->handle($firstPlayer, 'Results 8601', 'results-8601');
        $saveRoster = $this->app->make(SaveRosterEntry::class);
        $saveRoster->handle($alliance, $firstPlayer, ['name' => 'Alpha', 'game_player_id' => '8601-a']);
        $saveRoster->handle($alliance, $firstPlayer, ['name' => 'Bravo', 'game_player_id' => '8601-b']);
        $type = EventType::query()->where('slug', 'custom')->sole();
        $configuration = $this->app->make(EventTypeRegistry::class)->scope($type, EventScope::Alliance);
        $event = $this->app->make(CreateEvent::class)->handle(
            actor: $firstPlayer,
            configuration: $configuration,
            target: $alliance,
            firstLocalStart: CarbonImmutable::now('UTC')->subDay(),
            durationMinutes: 60,
        );
        $occurrence = $event->occurrences->firstOrFail();
        $save = $this->app->make(SaveEventPlayerResult::class);

        $save->handle($firstPlayer, $occurrence, $firstPlayer, outcome: 'completed', score: 125, rank: 4);
        $save->handle($firstPlayer, $occurrence, $secondPlayer, outcome: 'completed', score: 275, rank: 1);

        self::assertSame(2, EventPlayerResult::query()->where('occurrence_id', $occurrence->id)->count());
        self::assertSame(125, EventPlayerResult::query()->where('player_id', $firstPlayer->id)->sole()->score);
        self::assertSame(275, EventPlayerResult::query()->where('player_id', $secondPlayer->id)->sole()->score);
        self::assertSame((int) $owner->id, (int) $firstPlayer->user_id);
        self::assertSame((int) $owner->id, (int) $secondPlayer->user_id);

        $query = $this->app->make(EventResultQuery::class)->forOccurrence($occurrence, $secondPlayer);
        self::assertSame((string) $secondPlayer->id, $query['player']['playerId']);
        self::assertSame(275, $query['player']['score']);
    }

    public function test_occurrence_and_player_results_are_idempotent_updates(): void
    {
        $owner = User::factory()->create();
        $kingdom = Kingdom::query()->create(['number' => 8602, 'status' => 'active']);
        $player = Player::query()->create([
            'user_id' => $owner->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => '8602-a',
            'current_name' => 'Alpha',
        ]);
        $alliance = $this->app->make(CreateAlliance::class)->handle($player, 'Results 8602', 'results-8602');
        $this->app->make(SaveRosterEntry::class)->handle($alliance, $player, ['name' => 'Alpha', 'game_player_id' => '8602-a']);
        $type = EventType::query()->where('slug', 'custom')->sole();
        $configuration = $this->app->make(EventTypeRegistry::class)->scope($type, EventScope::Alliance);
        $event = $this->app->make(CreateEvent::class)->handle(
            actor: $player,
            configuration: $configuration,
            target: $alliance,
            firstLocalStart: CarbonImmutable::now('UTC')->subDay(),
            durationMinutes: 60,
        );
        $occurrence = $event->occurrences->firstOrFail();

        $summary = $this->app->make(SaveEventResult::class);
        $summary->handle($player, $occurrence, outcome: 'win', score: 1000, opponentScore: 900);
        $summary->handle($player, $occurrence, outcome: 'win', score: 1100, opponentScore: 900);

        $playerResults = $this->app->make(SaveEventPlayerResult::class);
        $playerResults->handle($player, $occurrence, $player, score: 100);
        $playerResults->handle($player, $occurrence, $player, score: 150);

        self::assertSame(1, EventResult::query()->where('occurrence_id', $occurrence->id)->count());
        self::assertSame(1100, EventResult::query()->where('occurrence_id', $occurrence->id)->sole()->score);
        self::assertSame(1, EventPlayerResult::query()->where('occurrence_id', $occurrence->id)->where('player_id', $player->id)->count());
        self::assertSame(150, EventPlayerResult::query()->where('occurrence_id', $occurrence->id)->where('player_id', $player->id)->sole()->score);
    }

    public function test_player_result_rejects_player_outside_the_event_target(): void
    {
        $owner = User::factory()->create();
        $otherOwner = User::factory()->create();
        $kingdom = Kingdom::query()->create(['number' => 8603, 'status' => 'active']);
        $otherKingdom = Kingdom::query()->create(['number' => 8604, 'status' => 'active']);
        $player = Player::query()->create([
            'user_id' => $owner->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => '8603-player',
            'current_name' => 'Inside Player',
        ]);
        $otherPlayer = Player::query()->create([
            'user_id' => $otherOwner->id,
            'current_kingdom_id' => $otherKingdom->id,
            'game_player_id' => '8604-player',
            'current_name' => 'Outside Player',
        ]);
        $alliance = $this->app->make(CreateAlliance::class)->handle($player, 'Results 8603', 'results-8603');
        $otherAlliance = $this->app->make(CreateAlliance::class)->handle($otherPlayer, 'Other Results', 'other-results');
        $this->app->make(SaveRosterEntry::class)->handle($alliance, $player, ['name' => 'Inside Player', 'game_player_id' => '8603-player']);
        $this->app->make(SaveRosterEntry::class)->handle($otherAlliance, $otherPlayer, ['name' => 'Outside Player', 'game_player_id' => '8604-player']);
        $type = EventType::query()->where('slug', 'custom')->sole();
        $configuration = $this->app->make(EventTypeRegistry::class)->scope($type, EventScope::Alliance);
        $event = $this->app->make(CreateEvent::class)->handle(
            actor: $player,
            configuration: $configuration,
            target: $alliance,
            firstLocalStart: CarbonImmutable::now('UTC')->subDay(),
            durationMinutes: 60,
        );

        $this->expectException(ValidationException::class);
        $this->app->make(SaveEventPlayerResult::class)->handle($player, $event->occurrences->firstOrFail(), $otherPlayer, score: 999);
    }

    public function test_reliability_uses_recorded_outcomes_and_keeps_excused_separate(): void
    {
        $owner = User::factory()->create();
        $kingdom = Kingdom::query()->create(['number' => 8605, 'status' => 'active']);
        $firstPlayer = Player::query()->create([
            'user_id' => $owner->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => '8605-a',
            'current_name' => 'Alpha',
        ]);
        $secondPlayer = Player::query()->create([
            'user_id' => $owner->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => '8605-b',
            'current_name' => 'Bravo',
        ]);
        $alliance = $this->app->make(CreateAlliance::class)->handle($firstPlayer, 'Results 8605', 'results-8605');
        $saveRoster = $this->app->make(SaveRosterEntry::class);
        $saveRoster->handle($alliance, $firstPlayer, ['name' => 'Alpha', 'game_player_id' => '8605-a']);
        $saveRoster->handle($alliance, $firstPlayer, ['name' => 'Bravo', 'game_player_id' => '8605-b']);
        $custom = EventType::query()->where('slug', 'custom')->sole();
        $customConfiguration = $this->app->make(EventTypeRegistry::class)->scope($custom, EventScope::Alliance);
        $createEvent = $this->app->make(CreateEvent::class);
        $present = $createEvent->handle($firstPlayer, $customConfiguration, $alliance, CarbonImmutable::now('UTC')->subDays(3), durationMinutes: 60);
        $absent = $createEvent->handle($firstPlayer, $customConfiguration, $alliance, CarbonImmutable::now('UTC')->subDays(2), durationMinutes: 60);
        $excused = $createEvent->handle($firstPlayer, $customConfiguration, $alliance, CarbonImmutable::now('UTC')->subDay(), durationMinutes: 60);
        $attendance = $this->app->make(RecordEventAttendance::class);
        $results = $this->app->make(SaveEventPlayerResult::class);

        $attendance->handle($firstPlayer, $present->occurrences->firstOrFail(), $firstPlayer, EventAttendanceStatus::Present);
        $attendance->handle($firstPlayer, $absent->occurrences->firstOrFail(), $firstPlayer, EventAttendanceStatus::Absent);
        $attendance->handle($firstPlayer, $excused->occurrences->firstOrFail(), $firstPlayer, EventAttendanceStatus::Excused);
        $results->handle($firstPlayer, $present->occurrences->firstOrFail(), $firstPlayer, score: 100);
        $results->handle($firstPlayer, $absent->occurrences->firstOrFail(), $firstPlayer, score: 200);
        $results->handle($firstPlayer, $excused->occurrences->firstOrFail(), $firstPlayer, score: 150);
        $results->handle($firstPlayer, $present->occurrences->firstOrFail(), $secondPlayer, score: 500);

        $otherType = EventType::query()->where('slug', 'viking-vengeance')->sole();
        $otherConfiguration = $this->app->make(EventTypeRegistry::class)->scope($otherType, EventScope::Alliance);
        $otherEvent = $createEvent->handle(
            actor: $firstPlayer,
            configuration: $otherConfiguration,
            target: $alliance,
            firstLocalStart: CarbonImmutable::now('UTC')->subHours(12),
            durationMinutes: 30,
        );
        $results->handle($firstPlayer, $otherEvent->occurrences->firstOrFail(), $firstPlayer, score: 9999);

        $rows = collect($this->app->make(EventPlayerIntelligenceQuery::class)->forEvent($excused))->keyBy('playerId');
        $firstRow = $rows[(string) $firstPlayer->id];
        $secondRow = $rows[(string) $secondPlayer->id];

        self::assertSame(1, $firstRow['completed']);
        self::assertSame(1, $firstRow['absent']);
        self::assertSame(1, $firstRow['excused']);
        self::assertSame(50.0, $firstRow['reliabilityPercent']);
        self::assertSame(3, $firstRow['resultCount']);
        self::assertSame(150, $firstRow['averageScore']);
        self::assertSame(200, $firstRow['bestScore']);

        self::assertNull($secondRow['reliabilityPercent']);
        self::assertSame(1, $secondRow['resultCount']);
        self::assertSame(500, $secondRow['averageScore']);
    }

    public function test_player_scoped_intelligence_does_not_merge_histories_for_two_players_owned_by_same_user(): void
    {
        $owner = User::factory()->create();
        $kingdom = Kingdom::query()->create(['number' => 8606, 'status' => 'active']);
        $firstPlayer = Player::query()->create([
            'user_id' => $owner->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => '8606-a',
            'current_name' => 'Alpha',
        ]);
        $secondPlayer = Player::query()->create([
            'user_id' => $owner->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => '8606-b',
            'current_name' => 'Bravo',
        ]);
        $type = EventType::query()->where('slug', 'custom')->sole();
        $configuration = $this->app->make(EventTypeRegistry::class)->scope($type, EventScope::Player);
        $createEvent = $this->app->make(CreateEvent::class);
        $saveResult = $this->app->make(SaveEventPlayerResult::class);

        $firstEvent = $createEvent->handle($firstPlayer, $configuration, $firstPlayer, CarbonImmutable::now('UTC')->subDays(2), durationMinutes: 60);
        $saveResult->handle($firstPlayer, $firstEvent->occurrences->firstOrFail(), $firstPlayer, score: 100);

        $secondEvent = $createEvent->handle($secondPlayer, $configuration, $secondPlayer, CarbonImmutable::now('UTC')->subDay(), durationMinutes: 60);
        $saveResult->handle($secondPlayer, $secondEvent->occurrences->firstOrFail(), $secondPlayer, score: 900);

        $firstIntel = $this->app->make(EventPlayerIntelligenceQuery::class)->forPlayer($firstEvent, $firstPlayer);
        $secondIntel = $this->app->make(EventPlayerIntelligenceQuery::class)->forPlayer($secondEvent, $secondPlayer);

        self::assertSame(1, $firstIntel['resultCount']);
        self::assertSame(100, $firstIntel['averageScore']);
        self::assertSame(1, $secondIntel['resultCount']);
        self::assertSame(900, $secondIntel['averageScore']);
        self::assertSame((int) $owner->id, (int) $firstPlayer->user_id);
        self::assertSame((int) $owner->id, (int) $secondPlayer->user_id);
    }
}
