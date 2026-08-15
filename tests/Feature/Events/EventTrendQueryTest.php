<?php

declare(strict_types=1);

namespace Tests\Feature\Events;

use App\Contexts\Accounts\Models\User;
use App\Contexts\Alliance\Core\Actions\CreateAlliance;
use App\Contexts\Alliance\Core\Models\Alliance;
use App\Contexts\GameWorld\Models\Kingdom;
use App\Contexts\GameWorld\Models\Player;
use App\Contexts\Operations\EventCore\Actions\CreateEvent;
use App\Contexts\Operations\Results\Actions\SaveEventPlayerResult;
use App\Contexts\Operations\Results\Actions\SaveEventResult;
use App\Contexts\Operations\BattlePlans\Enums\EventObjectiveStatus;
use App\Contexts\Operations\EventCore\Enums\EventScope;
use App\Contexts\Operations\EventCore\Models\Event;
use App\Contexts\Operations\EventCore\Models\EventObjective;
use App\Contexts\Operations\EventCore\Models\EventType;
use App\Contexts\Intelligence\EventAnalysis\Queries\EventTrendQuery;
use App\Contexts\Operations\EventCore\Services\EventTypeRegistry;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class EventTrendQueryTest extends TestCase
{
    use RefreshDatabase;

    public function test_participation_reliability_can_span_event_types_but_stays_exact_player(): void
    {
        $kingdom = Kingdom::query()->create(['number' => 9001, 'status' => 'active']);
        $user = User::factory()->create();
        $player = $this->player($user, $kingdom, 'Trend Player', '9001-player');
        $sibling = $this->player($user, $kingdom, 'Sibling', '9001-sibling');
        $first = $this->event($player, $player, 'custom', EventScope::Player, 1);
        $second = $this->event($player, $player, 'eternitys-reach', EventScope::Player, 2);
        $save = $this->app->make(SaveEventPlayerResult::class);
        $save->handle($player, $first->occurrences->firstOrFail(), $player, outcome: 'completed');
        $save->handle($player, $second->occurrences->firstOrFail(), $player, outcome: 'absent');

        $query = $this->app->make(EventTrendQuery::class);
        self::assertSame([
            'completed' => 1,
            'absent' => 1,
            'excused' => 0,
            'unresolved' => 0,
            'reliability_percent' => 50.0,
        ], $query->playerParticipation($player));
        self::assertSame([
            'completed' => 0,
            'absent' => 0,
            'excused' => 0,
            'unresolved' => 0,
            'reliability_percent' => null,
        ], $query->playerParticipation($sibling));
    }

    public function test_player_score_and_metric_series_require_one_event_type_and_scope(): void
    {
        $kingdom = Kingdom::query()->create(['number' => 9002, 'status' => 'active']);
        $player = $this->player(User::factory()->create(), $kingdom, 'Metric Player', '9002-player');
        $custom = $this->event($player, $player, 'custom', EventScope::Player, 1);
        $roulette = $this->event($player, $player, 'hero-roulette', EventScope::Player, 2);
        $save = $this->app->make(SaveEventPlayerResult::class);
        $save->handle($player, $custom->occurrences->firstOrFail(), $player, score: 100);
        $save->handle($player, $roulette->occurrences->firstOrFail(), $player, metrics: [
            ['key' => 'spins', 'value' => 7],
        ]);

        $query = $this->app->make(EventTrendQuery::class);
        $customScores = $query->playerScoreSeries($player, 'custom', EventScope::Player);
        $rouletteScores = $query->playerScoreSeries($player, 'hero-roulette', EventScope::Player);
        $spins = $query->playerMetricSeries($player, 'hero-roulette', EventScope::Player, 'spins');

        self::assertCount(1, $customScores);
        self::assertSame(100, $customScores[0]['score']);
        self::assertCount(1, $rouletteScores);
        self::assertNull($rouletteScores[0]['score']);
        self::assertCount(1, $spins);
        self::assertSame(7.0, $spins[0]['value']);
        self::assertSame('count', $spins[0]['unit']);
        self::assertSame([], $query->playerMetricSeries($player, 'custom', EventScope::Player, 'spins'));
    }

    public function test_organization_trends_follow_exact_immutable_target_and_metric_definition(): void
    {
        $kingdom = Kingdom::query()->create(['number' => 9003, 'status' => 'active']);
        $owner = $this->player(User::factory()->create(), $kingdom, 'Alliance Owner', '9003-owner');
        $otherOwner = $this->player(User::factory()->create(), $kingdom, 'Other Owner', '9003-other');
        $alliance = $this->app->make(CreateAlliance::class)->handle($owner, 'Trend Alliance', 'trend-alliance');
        $otherAlliance = $this->app->make(CreateAlliance::class)->handle($otherOwner, 'Other Alliance', 'other-trend-alliance');
        $event = $this->event($owner, $alliance, 'outpost-battle', EventScope::Alliance, 1);
        $occurrence = $event->occurrences->firstOrFail();
        $objective = EventObjective::query()->create([
            'occurrence_id' => $occurrence->id,
            'objective_type' => 'outpost',
            'name' => 'North Outpost',
            'priority' => 1,
            'status' => EventObjectiveStatus::Completed,
            'sort_order' => 1,
            'created_by_player_id' => $owner->id,
            'updated_by_player_id' => $owner->id,
        ]);
        $this->app->make(SaveEventResult::class)->handle(
            $owner,
            $occurrence,
            outcome: 'win',
            score: 500,
            opponentScore: 450,
            rank: 1,
            metrics: [[
                'key' => 'objective_occupation_seconds',
                'dimension_key' => (string) $objective->id,
                'value' => 120,
            ]],
        );

        $query = $this->app->make(EventTrendQuery::class);
        $scores = $query->organizationScoreSeries(EventScope::Alliance, (string) $alliance->id, 'outpost-battle');
        $metrics = $query->organizationMetricSeries(
            EventScope::Alliance,
            (string) $alliance->id,
            'outpost-battle',
            'objective_occupation_seconds',
        );

        self::assertCount(1, $scores);
        self::assertSame(500, $scores[0]['score']);
        self::assertCount(1, $metrics);
        self::assertSame((string) $objective->id, $metrics[0]['dimension_key']);
        self::assertSame(120.0, $metrics[0]['value']);
        self::assertSame('seconds', $metrics[0]['unit']);
        self::assertSame([], $query->organizationScoreSeries(EventScope::Alliance, (string) $otherAlliance->id, 'outpost-battle'));
    }

    private function player(User $user, Kingdom $kingdom, string $name, string $gamePlayerId): Player
    {
        return Player::query()->create([
            'user_id' => $user->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => $gamePlayerId,
            'current_name' => $name,
        ]);
    }

    private function event(
        Player $actor,
        Player|Alliance $target,
        string $slug,
        EventScope $scope,
        int $hoursAgo,
    ): Event {
        $type = EventType::query()->where('slug', $slug)->sole();
        $configuration = $this->app->make(EventTypeRegistry::class)->scope($type, $scope);

        return $this->app->make(CreateEvent::class)->handle(
            actor: $actor,
            configuration: $configuration,
            target: $target,
            firstLocalStart: CarbonImmutable::now('UTC')->subHours($hoursAgo),
            durationMinutes: 60,
        );
    }
}
