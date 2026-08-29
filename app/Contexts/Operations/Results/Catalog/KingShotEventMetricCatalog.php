<?php

declare(strict_types=1);

namespace App\Contexts\Operations\Results\Catalog;

use App\Contexts\Operations\Events\Enums\EventScope;
use App\Contexts\Operations\Results\Enums\EventMetricAggregation;
use App\Contexts\Operations\Results\Enums\EventMetricSubject;
use App\Contexts\Operations\Results\Enums\EventMetricValueType;

/**
 * Result metric definitions are Results-owned evidence-backed schemas.
 *
 * Phase 13 deliberately does not derive metrics from Event category, profile
 * similarity or a generic capability list. Candidate/profile-disabled event
 * types therefore resolve to no event-specific metric definitions.
 *
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
    /** @return array{score:ScoreProfile|null,metrics:list<MetricProfile>} */
    public static function profile(string $eventSlug, EventScope $scope): array
    {
        return match ($eventSlug.':'.$scope->value) {
            // The existing Bear Hunt Evidence contract fixture-proves damage
            // and reported rank. Damage remains the existing scalar score
            // representation; no unproven rally-count metrics are inferred.
            'bear-hunt:alliance' => self::result(
                self::score('events.metrics.damage', 'damage', true),
            ),
            default => self::result(null),
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
}
