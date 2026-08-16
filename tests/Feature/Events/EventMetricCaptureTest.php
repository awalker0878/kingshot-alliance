<?php

declare(strict_types=1);

namespace Tests\Feature\Events;

use App\Contexts\Accounts\Models\User;
use App\Contexts\Alliance\Core\Actions\CreateAlliance;
use App\Contexts\Alliance\Core\Models\Alliance;
use App\Contexts\GameWorld\Models\Kingdom;
use App\Contexts\GameWorld\Models\Player;
use App\Contexts\Operations\EventCore\Actions\CreateEvent;
use App\Contexts\Operations\EventCore\Enums\EventPhaseStatus;
use App\Contexts\Operations\EventCore\Enums\EventPhaseType;
use App\Contexts\Operations\EventCore\Enums\EventScope;
use App\Contexts\Operations\EventCore\Models\Event;
use App\Contexts\Operations\EventCore\Models\EventAllianceResultMetric;
use App\Contexts\Operations\EventCore\Models\EventObjective;
use App\Contexts\Operations\EventCore\Models\EventPhase;
use App\Contexts\Operations\EventCore\Models\EventPlayerContext;
use App\Contexts\Operations\EventCore\Models\EventPlayerResultMetric;
use App\Contexts\Operations\EventCore\Models\EventResultMetric;
use App\Contexts\Operations\EventCore\Models\EventType;
use App\Contexts\Operations\EventCore\Services\EventTypeRegistry;
use App\Contexts\Operations\Participation\Actions\RecordEventAttendance;
use App\Contexts\Operations\Participation\Enums\EventAttendanceStatus;
use App\Contexts\Operations\Results\Actions\SaveEventAllianceResult;
use App\Contexts\Operations\Results\Actions\SaveEventPlayerResult;
use App\Contexts\Operations\Results\Actions\SaveEventResult;
use App\Contexts\Operations\Results\Enums\EventMetricSource;
use App\Domain\Kingdoms\Actions\SaveRosterEntry;
use App\Workflows\KingdomGovernance\Actions\BootstrapKingdomAdministrator;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class EventMetricCaptureTest extends TestCase
{
    use RefreshDatabase;

    public function test_player_phase_metric_is_validated_and_idempotently_updated(): void
    {
        [$actor, $kingdom] = $this->player(8821, 'Metric Administrator');
        $this->grantKingdomAdministrator($actor, $kingdom);
        $event = $this->createEvent($actor, $kingdom, 'kingdom-of-power', EventScope::Kingdom);
        $occurrence = $event->occurrences->firstOrFail();
        $phase = EventPhase::query()->create([
            'occurrence_id' => $occurrence->id,
            'key' => 'metric-test-phase',
            'name' => 'Metric Test Phase',
            'phase_type' => EventPhaseType::Custom,
            'status' => EventPhaseStatus::Scheduled,
            'sort_order' => 900,
            'created_by_player_id' => $actor->id,
            'updated_by_player_id' => $actor->id,
        ]);
        $save = $this->app->make(SaveEventPlayerResult::class);

        $save->handle(
            $actor,
            $occurrence,
            $actor,
            score: 1000,
            metrics: [[
                'key' => 'phase_points',
                'dimension_key' => $phase->key,
                'value' => 125,
            ]],
        );
        $save->handle(
            $actor,
            $occurrence,
            $actor,
            score: 1100,
            metrics: [[
                'key' => 'phase_points',
                'dimension_key' => $phase->key,
                'value' => 175,
            ]],
        );

        $metric = EventPlayerResultMetric::query()->with('definition')->sole();
        self::assertSame('phase_points', $metric->definition?->key);
        self::assertSame($phase->key, $metric->dimension_key);
        self::assertSame('175.0000', $metric->value);
        self::assertSame(EventMetricSource::Manual, $metric->source);
        self::assertSame((string) $actor->id, (string) $metric->recorded_by_player_id);
        self::assertSame(1, EventPlayerResultMetric::query()->count());
        self::assertSame(1, EventPlayerContext::query()->where('occurrence_id', $occurrence->id)->where('player_id', $actor->id)->count());
    }

    public function test_metric_dimension_must_exist_on_the_exact_occurrence(): void
    {
        [$actor, $kingdom] = $this->player(8822, 'Dimension Administrator');
        $this->grantKingdomAdministrator($actor, $kingdom);
        $event = $this->createEvent($actor, $kingdom, 'kingdom-of-power', EventScope::Kingdom);
        $occurrence = $event->occurrences->firstOrFail();

        $this->expectException(ValidationException::class);
        $this->app->make(SaveEventPlayerResult::class)->handle(
            $actor,
            $occurrence,
            $actor,
            metrics: [[
                'key' => 'phase_points',
                'dimension_key' => 'not-an-occurrence-phase',
                'value' => 10,
            ]],
        );
    }

    public function test_objective_metric_accepts_only_an_objective_from_the_same_occurrence(): void
    {
        [$owner] = $this->player(8823, 'Castle Owner');
        $alliance = $this->app->make(CreateAlliance::class)->handle($owner, 'Castle Metrics', 'castle-metrics');
        $event = $this->createEvent($owner, $alliance, 'castle-battle', EventScope::Alliance);
        $occurrence = $event->occurrences->firstOrFail();
        $objective = EventObjective::query()->create([
            'occurrence_id' => $occurrence->id,
            'objective_type' => 'castle',
            'name' => 'Castle',
            'priority' => 100,
            'status' => 'planned',
            'sort_order' => 0,
            'created_by_player_id' => $owner->id,
            'updated_by_player_id' => $owner->id,
        ]);

        $this->app->make(SaveEventResult::class)->handle(
            $owner,
            $occurrence,
            score: 500,
            metrics: [[
                'key' => 'objective_occupation_seconds',
                'dimension_key' => (string) $objective->id,
                'value' => 120,
            ]],
        );

        $metric = EventResultMetric::query()->with('definition')->sole();
        self::assertSame('objective_occupation_seconds', $metric->definition?->key);
        self::assertSame((string) $objective->id, $metric->dimension_key);
        self::assertSame('120.0000', $metric->value);

        $otherEvent = $this->createEvent($owner, $alliance, 'castle-battle', EventScope::Alliance);
        $otherOccurrence = $otherEvent->occurrences->firstOrFail();
        $otherObjective = EventObjective::query()->create([
            'occurrence_id' => $otherOccurrence->id,
            'objective_type' => 'castle',
            'name' => 'Other Castle',
            'priority' => 100,
            'status' => 'planned',
            'sort_order' => 0,
            'created_by_player_id' => $owner->id,
            'updated_by_player_id' => $owner->id,
        ]);

        try {
            $this->app->make(SaveEventResult::class)->handle(
                $owner,
                $occurrence,
                metrics: [[
                    'key' => 'objective_occupation_seconds',
                    'dimension_key' => (string) $otherObjective->id,
                    'value' => 60,
                ]],
            );
            self::fail('A metric dimension from another occurrence must be rejected.');
        } catch (ValidationException) {
            self::assertSame(1, EventResultMetric::query()->count());
        }
    }

    public function test_frozen_player_context_allows_later_result_correction_after_kingdom_move_without_rewriting_snapshot(): void
    {
        [$actor, $kingdom] = $this->player(8824, 'History Administrator');
        $this->grantKingdomAdministrator($actor, $kingdom);
        $participant = $this->playerInKingdom($kingdom, 'Original Name', '8824-participant');
        $event = $this->createEvent($actor, $kingdom, 'custom', EventScope::Kingdom);
        $occurrence = $event->occurrences->firstOrFail();
        $save = $this->app->make(SaveEventPlayerResult::class);

        $save->handle($actor, $occurrence, $participant, score: 100);
        $context = EventPlayerContext::query()
            ->where('occurrence_id', $occurrence->id)
            ->where('player_id', $participant->id)
            ->sole();
        self::assertSame('Original Name', $context->player_name_snapshot);
        self::assertSame((string) $kingdom->id, (string) $context->kingdom_id_at_event);

        $newKingdom = Kingdom::query()->create(['number' => 8825, 'status' => 'active']);
        $participant->forceFill([
            'current_name' => 'Moved Name',
            'current_kingdom_id' => $newKingdom->id,
        ])->save();

        $updated = $save->handle($actor, $occurrence, $participant->refresh(), score: 250);
        $context->refresh();

        self::assertSame(250, $updated->score);
        self::assertSame(1, EventPlayerContext::query()->where('occurrence_id', $occurrence->id)->where('player_id', $participant->id)->count());
        self::assertSame('Original Name', $context->player_name_snapshot);
        self::assertSame((string) $kingdom->id, (string) $context->kingdom_id_at_event);
        self::assertNotSame((string) $newKingdom->id, (string) $context->kingdom_id_at_event);
    }

    public function test_kingdom_event_alliance_result_captures_metrics_and_preserves_first_name_snapshot(): void
    {
        [$actor, $kingdom] = $this->player(8826, 'Alliance Result Administrator');
        $this->grantKingdomAdministrator($actor, $kingdom);
        $allianceOwner = $this->playerInKingdom($kingdom, 'Alliance Owner', '8826-alliance-owner');
        $alliance = $this->app->make(CreateAlliance::class)->handle($allianceOwner, 'Original Result Alliance', 'original-result-alliance');
        $event = $this->createEvent($actor, $kingdom, 'castle-battle', EventScope::Kingdom);
        $occurrence = $event->occurrences->firstOrFail();
        $save = $this->app->make(SaveEventAllianceResult::class);

        $result = $save->handle(
            $actor,
            $occurrence,
            $alliance,
            score: 900,
            metrics: [[
                'key' => 'carnage_points',
                'value' => 450,
            ]],
        );
        self::assertSame('Original Result Alliance', $result->alliance_name_snapshot);
        self::assertSame('450.0000', EventAllianceResultMetric::query()->sole()->value);

        $alliance->forceFill(['name' => 'Renamed Result Alliance'])->save();
        $result = $save->handle($actor, $occurrence, $alliance->refresh(), score: 950);

        self::assertSame('Original Result Alliance', $result->alliance_name_snapshot);
        self::assertSame(1, EventAllianceResultMetric::query()->count());
    }

    public function test_kingdom_event_alliance_result_rejects_an_alliance_from_another_kingdom(): void
    {
        [$actor, $kingdom] = $this->player(8827, 'Boundary Administrator');
        $this->grantKingdomAdministrator($actor, $kingdom);
        $event = $this->createEvent($actor, $kingdom, 'castle-battle', EventScope::Kingdom);

        [$otherOwner] = $this->player(8828, 'Other Alliance Owner');
        $otherAlliance = $this->app->make(CreateAlliance::class)->handle($otherOwner, 'Other Kingdom Alliance', 'other-kingdom-result-alliance');

        $this->expectException(ValidationException::class);
        $this->app->make(SaveEventAllianceResult::class)->handle(
            $actor,
            $event->occurrences->firstOrFail(),
            $otherAlliance,
            score: 100,
        );
    }

    public function test_explicit_attendance_freezes_context_but_unknown_attendance_does_not(): void
    {
        [$owner] = $this->player(8829, 'Attendance Owner');
        $alliance = $this->app->make(CreateAlliance::class)->handle($owner, 'Attendance Context', 'attendance-context');
        $this->app->make(SaveRosterEntry::class)->handle($alliance, $owner, [
            'name' => $owner->current_name,
            'game_player_id' => $owner->game_player_id,
        ]);
        $event = $this->createEvent($owner, $alliance, 'custom', EventScope::Alliance);
        $occurrence = $event->occurrences->firstOrFail();
        $attendance = $this->app->make(RecordEventAttendance::class);

        $attendance->handle($owner, $occurrence, $owner, EventAttendanceStatus::Unknown);
        self::assertFalse(EventPlayerContext::query()->where('occurrence_id', $occurrence->id)->where('player_id', $owner->id)->exists());

        $attendance->handle($owner, $occurrence, $owner, EventAttendanceStatus::Present);
        self::assertTrue(EventPlayerContext::query()->where('occurrence_id', $occurrence->id)->where('player_id', $owner->id)->exists());
    }

    public function test_integer_metric_rejects_fractional_values(): void
    {
        [$actor, $kingdom] = $this->player(8830, 'Integer Metric Administrator');
        $this->grantKingdomAdministrator($actor, $kingdom);
        $event = $this->createEvent($actor, $kingdom, 'kingdom-of-power', EventScope::Kingdom);
        $occurrence = $event->occurrences->firstOrFail();
        $phase = EventPhase::query()->create([
            'occurrence_id' => $occurrence->id,
            'key' => 'fraction-test',
            'name' => 'Fraction Test',
            'phase_type' => EventPhaseType::Custom,
            'status' => EventPhaseStatus::Scheduled,
            'sort_order' => 901,
            'created_by_player_id' => $actor->id,
            'updated_by_player_id' => $actor->id,
        ]);

        $this->expectException(ValidationException::class);
        $this->app->make(SaveEventPlayerResult::class)->handle(
            $actor,
            $occurrence,
            $actor,
            metrics: [[
                'key' => 'phase_points',
                'dimension_key' => $phase->key,
                'value' => '1.5',
            ]],
        );
    }

    /** @return array{0:Player,1:Kingdom} */
    private function player(int $kingdomNumber, string $name): array
    {
        $kingdom = Kingdom::query()->create([
            'number' => $kingdomNumber,
            'status' => 'active',
        ]);

        return [$this->playerInKingdom($kingdom, $name, (string) $kingdomNumber.'-owner'), $kingdom];
    }

    private function playerInKingdom(Kingdom $kingdom, string $name, string $gamePlayerId): Player
    {
        $user = User::factory()->create();

        return Player::query()->create([
            'user_id' => $user->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => $gamePlayerId,
            'current_name' => $name,
        ]);
    }

    private function grantKingdomAdministrator(Player $player, Kingdom $kingdom): void
    {
        $this->app->make(BootstrapKingdomAdministrator::class)->handle($kingdom, $player);
    }

    private function createEvent(
        Player $actor,
        Alliance|Kingdom|Player $target,
        string $slug,
        EventScope $scope,
    ): Event {
        $type = EventType::query()->where('slug', $slug)->sole();
        $configuration = $this->app->make(EventTypeRegistry::class)->scope($type, $scope);

        return $this->app->make(CreateEvent::class)->handle(
            actor: $actor,
            configuration: $configuration,
            target: $target,
            firstLocalStart: CarbonImmutable::now('UTC')->addHour(),
            durationMinutes: 60,
        );
    }
}
