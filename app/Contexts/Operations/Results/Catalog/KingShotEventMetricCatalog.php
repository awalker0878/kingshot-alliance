<?php

declare(strict_types=1);

namespace App\Contexts\Operations\Results\Catalog;

use App\Contexts\Operations\Events\Enums\EventCapability;
use App\Contexts\Operations\Events\Enums\EventScope;
use App\Contexts\Operations\Results\Enums\EventMetricAggregation;
use App\Contexts\Operations\Results\Enums\EventMetricSubject;
use App\Contexts\Operations\Results\Enums\EventMetricValueType;

/**
 * @phpstan-type ScoreProfile array{label_key:string,unit:string|null,higher_is_better:bool|null}
 * @phpstan-type MetricProfile array{
 *   subject:EventMetricSubject,
 *   key:string,
 *   label_key:string,
 *   unit:string|null,
 *   value_type:EventMetricValueType,
 *   aggregation:EventMetricAggregation,
 *   dimension_kind:string|null,
 *   is_primary:bool,
 *   is_contribution_metric:bool,
 *   higher_is_better:bool|null,
 *   sort_order:int
 * }
 */
final class KingShotEventMetricCatalog
{
    /**
     * @param  list<EventCapability>  $capabilities
     * @return array{score:ScoreProfile|null,metrics:list<MetricProfile>}
     */
    public static function profile(string $eventSlug, EventScope $scope, array $capabilities): array
    {
        $defaultScore = in_array(EventCapability::Scoring, $capabilities, true)
            ? self::score('events.metrics.score', 'points', true)
            : null;

        return match ($eventSlug.':'.$scope->value) {
            'bear-hunt:alliance' => self::result(
                self::score('events.metrics.damage', 'damage', true),
                [
                    self::metric(EventMetricSubject::Player, 'rallies_joined', 'count', EventMetricValueType::Integer, EventMetricAggregation::Sum, true, 10),
                    self::metric(EventMetricSubject::Player, 'rallies_led', 'count', EventMetricValueType::Integer, EventMetricAggregation::Sum, true, 20),
                ],
            ),
            'viking-vengeance:alliance' => self::result(
                self::score('events.metrics.score', 'points', true),
                [
                    self::metric(EventMetricSubject::Player, 'waves_defended', 'count', EventMetricValueType::Integer, EventMetricAggregation::Sum, true, 10),
                    self::metric(EventMetricSubject::Player, 'defense_failures', 'count', EventMetricValueType::Integer, EventMetricAggregation::Sum, false, 20),
                ],
            ),
            'alliance-mobilization:alliance',
            'alliance-brawl:alliance' => self::result(
                $defaultScore,
                [self::phasePoints(EventMetricSubject::Player, 10)],
            ),
            'alliance-championship:alliance' => self::result(
                $defaultScore,
                [self::metric(EventMetricSubject::Player, 'round_wins', 'count', EventMetricValueType::Integer, EventMetricAggregation::Sum, true, 10)],
            ),
            'swordland-showdown:alliance' => self::result(
                self::score('events.metrics.relic_points', 'points', true),
                self::battlefieldPlayerMetrics(),
            ),
            'tri-alliance-clash:alliance' => self::result(
                self::score('events.metrics.battle_points', 'points', true),
                self::battlefieldPlayerMetrics(),
            ),
            'flamedragon-tyrant:alliance' => self::result(
                self::score('events.metrics.personal_points', 'points', true),
                [
                    self::metric(EventMetricSubject::Player, 'palace_occupation_seconds', 'seconds', EventMetricValueType::Duration, EventMetricAggregation::Sum, true, 10, isPrimary: true),
                    self::metric(EventMetricSubject::Player, 'aerie_captures', 'count', EventMetricValueType::Integer, EventMetricAggregation::Sum, true, 20),
                ],
            ),
            'swordland-summit-league:alliance' => self::result(
                self::score('events.metrics.battle_points', 'points', true),
                self::battlefieldPlayerMetrics(),
            ),
            'cesares-fury:alliance' => self::result(
                $defaultScore,
                [
                    self::metric(EventMetricSubject::Player, 'stages_cleared', 'count', EventMetricValueType::Integer, EventMetricAggregation::Max, true, 10, isPrimary: true),
                    self::metric(EventMetricSubject::Player, 'captain_participations', 'count', EventMetricValueType::Integer, EventMetricAggregation::Sum, true, 20),
                ],
            ),
            'outpost-battle:alliance' => self::result(
                $defaultScore,
                [self::objectiveOccupation(EventMetricSubject::Event, 10)],
            ),
            'sanctuary-battle:alliance' => self::result(
                $defaultScore,
                [
                    self::objectiveOccupation(EventMetricSubject::Event, 10),
                    self::metric(EventMetricSubject::Player, 'enemy_troops_defeated', 'count', EventMetricValueType::Integer, EventMetricAggregation::Sum, true, 20),
                ],
            ),
            'castle-battle:alliance' => self::result(
                self::score('events.metrics.castle_points', 'points', true),
                [
                    self::objectiveOccupation(EventMetricSubject::Event, 10),
                    ...self::castlePointComponents(EventMetricSubject::Player, 20),
                ],
            ),
            'castle-battle:kingdom' => self::result(
                self::score('events.metrics.castle_points', 'points', true),
                [
                    self::objectiveOccupation(EventMetricSubject::Event, 10),
                    ...self::castlePointComponents(EventMetricSubject::Alliance, 20),
                    ...self::castlePointComponents(EventMetricSubject::Player, 50),
                ],
            ),
            'kingdom-of-power:kingdom' => self::result(
                self::score('events.metrics.total_points', 'points', true),
                [
                    self::phasePoints(EventMetricSubject::Event, 10),
                    self::phasePoints(EventMetricSubject::Alliance, 20),
                    self::phasePoints(EventMetricSubject::Player, 30),
                ],
            ),
            'hall-of-governors:player',
            'armament-competition:player',
            'treasure-raiders:player',
            'merchant-empire:player',
            'eternitys-reach:player' => self::result(
                $defaultScore,
                [self::phasePoints(EventMetricSubject::Player, 10)],
            ),
            'hero-roulette:player' => self::result(
                null,
                [self::metric(EventMetricSubject::Player, 'spins', 'count', EventMetricValueType::Integer, EventMetricAggregation::Sum, true, 10, isPrimary: true)],
            ),
            default => self::result($defaultScore),
        };
    }

    /**
     * @param  ScoreProfile|null  $score
     * @param  list<MetricProfile>  $metrics
     * @return array{score:ScoreProfile|null,metrics:list<MetricProfile>}
     */
    private static function result(?array $score, array $metrics = []): array
    {
        return ['score' => $score, 'metrics' => $metrics];
    }

    /** @return ScoreProfile */
    private static function score(string $labelKey, ?string $unit, ?bool $higherIsBetter): array
    {
        return [
            'label_key' => $labelKey,
            'unit' => $unit,
            'higher_is_better' => $higherIsBetter,
        ];
    }

    /** @return list<MetricProfile> */
    private static function battlefieldPlayerMetrics(): array
    {
        return [
            self::metric(EventMetricSubject::Player, 'kills', 'count', EventMetricValueType::Integer, EventMetricAggregation::Sum, true, 10),
            self::metric(EventMetricSubject::Player, 'objective_captures', 'count', EventMetricValueType::Integer, EventMetricAggregation::Sum, true, 20),
            self::objectiveOccupation(EventMetricSubject::Player, 30),
        ];
    }

    /** @return list<MetricProfile> */
    private static function castlePointComponents(EventMetricSubject $subject, int $sortOrder): array
    {
        return [
            self::metric($subject, 'carnage_points', 'points', EventMetricValueType::Integer, EventMetricAggregation::Sum, true, $sortOrder),
            self::metric($subject, 'occupation_points', 'points', EventMetricValueType::Integer, EventMetricAggregation::Sum, true, $sortOrder + 10),
            self::metric($subject, 'casualty_points', 'points', EventMetricValueType::Integer, EventMetricAggregation::Sum, null, $sortOrder + 20),
        ];
    }

    /** @return MetricProfile */
    private static function phasePoints(EventMetricSubject $subject, int $sortOrder): array
    {
        return self::metric(
            $subject,
            'phase_points',
            'points',
            EventMetricValueType::Integer,
            EventMetricAggregation::Sum,
            true,
            $sortOrder,
            dimensionKind: 'phase',
        );
    }

    /** @return MetricProfile */
    private static function objectiveOccupation(EventMetricSubject $subject, int $sortOrder): array
    {
        return self::metric(
            $subject,
            'objective_occupation_seconds',
            'seconds',
            EventMetricValueType::Duration,
            EventMetricAggregation::Sum,
            true,
            $sortOrder,
            dimensionKind: 'objective',
        );
    }

    /** @return MetricProfile */
    private static function metric(
        EventMetricSubject $subject,
        string $key,
        ?string $unit,
        EventMetricValueType $valueType,
        EventMetricAggregation $aggregation,
        ?bool $higherIsBetter,
        int $sortOrder,
        ?string $dimensionKind = null,
        bool $isPrimary = false,
        bool $isContributionMetric = true,
    ): array {
        return [
            'subject' => $subject,
            'key' => $key,
            'label_key' => 'events.metrics.'.$key,
            'unit' => $unit,
            'value_type' => $valueType,
            'aggregation' => $aggregation,
            'dimension_kind' => $dimensionKind,
            'is_primary' => $isPrimary,
            'is_contribution_metric' => $isContributionMetric,
            'higher_is_better' => $higherIsBetter,
            'sort_order' => $sortOrder,
        ];
    }
}
