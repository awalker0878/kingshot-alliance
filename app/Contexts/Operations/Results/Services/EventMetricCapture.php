<?php

declare(strict_types=1);

namespace App\Contexts\Operations\Results\Services;

use App\Contexts\Operations\BattlePlans\Models\EventObjective;
use App\Contexts\Operations\Events\Models\EventOccurrence;
use App\Contexts\Operations\Events\Models\EventPhase;
use App\Contexts\Operations\Results\Enums\EventMetricSource;
use App\Contexts\Operations\Results\Enums\EventMetricSubject;
use App\Contexts\Operations\Results\Enums\EventMetricValueType;
use App\Contexts\Operations\Results\Models\EventAllianceResult;
use App\Contexts\Operations\Results\Models\EventAllianceResultMetric;
use App\Contexts\Operations\Results\Models\EventMetricDefinition;
use App\Contexts\Operations\Results\Models\EventPlayerResult;
use App\Contexts\Operations\Results\Models\EventPlayerResultMetric;
use App\Contexts\Operations\Results\Models\EventResult;
use App\Contexts\Operations\Results\Models\EventResultMetric;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use LogicException;

final class EventMetricCapture
{
    /**
     * @param  list<array{key:string,value:int|float|string,dimension_key?:string|null}>  $metrics
     */
    public function forEventResult(
        EventResult $result,
        array $metrics,
        EventMetricSource $source,
        ?string $recorderPlayerId,
    ): void {
        if ($metrics === []) {
            return;
        }

        $locked = EventResult::query()->whereKey($result->id)->lockForUpdate()->firstOrFail();
        $values = $this->validatedValues($locked->occurrence_id, EventMetricSubject::Event, $metrics, $source, $recorderPlayerId);

        foreach ($values as $value) {
            EventResultMetric::query()->updateOrCreate(
                [
                    'event_result_id' => $locked->id,
                    'metric_definition_id' => $value['definition']->id,
                    'dimension_key' => $value['dimension_key'],
                ],
                [
                    'value' => $value['value'],
                    'source' => $source,
                    'recorded_by_player_id' => $recorderPlayerId,
                    'recorded_at' => now(),
                ],
            );
        }
    }

    /**
     * @param  list<array{key:string,value:int|float|string,dimension_key?:string|null}>  $metrics
     */
    public function forAllianceResult(
        EventAllianceResult $result,
        array $metrics,
        EventMetricSource $source,
        ?string $recorderPlayerId,
    ): void {
        if ($metrics === []) {
            return;
        }

        $locked = EventAllianceResult::query()->whereKey($result->id)->lockForUpdate()->firstOrFail();
        $values = $this->validatedValues($locked->occurrence_id, EventMetricSubject::Alliance, $metrics, $source, $recorderPlayerId);

        foreach ($values as $value) {
            EventAllianceResultMetric::query()->updateOrCreate(
                [
                    'event_alliance_result_id' => $locked->id,
                    'metric_definition_id' => $value['definition']->id,
                    'dimension_key' => $value['dimension_key'],
                ],
                [
                    'value' => $value['value'],
                    'source' => $source,
                    'recorded_by_player_id' => $recorderPlayerId,
                    'recorded_at' => now(),
                ],
            );
        }
    }

    /**
     * @param  list<array{key:string,value:int|float|string,dimension_key?:string|null}>  $metrics
     */
    public function forPlayerResult(
        EventPlayerResult $result,
        array $metrics,
        EventMetricSource $source,
        ?string $recorderPlayerId,
    ): void {
        if ($metrics === []) {
            return;
        }

        $locked = EventPlayerResult::query()->whereKey($result->id)->lockForUpdate()->firstOrFail();
        $values = $this->validatedValues($locked->occurrence_id, EventMetricSubject::Player, $metrics, $source, $recorderPlayerId);

        foreach ($values as $value) {
            EventPlayerResultMetric::query()->updateOrCreate(
                [
                    'event_player_result_id' => $locked->id,
                    'metric_definition_id' => $value['definition']->id,
                    'dimension_key' => $value['dimension_key'],
                ],
                [
                    'value' => $value['value'],
                    'source' => $source,
                    'recorded_by_player_id' => $recorderPlayerId,
                    'recorded_at' => now(),
                ],
            );
        }
    }

    /**
     * @param  list<array{key:string,value:int|float|string,dimension_key?:string|null}>  $metrics
     * @return list<array{definition:EventMetricDefinition,dimension_key:string,value:string}>
     */
    private function validatedValues(
        string $occurrenceId,
        EventMetricSubject $subject,
        array $metrics,
        EventMetricSource $source,
        ?string $recorderPlayerId,
    ): array {
        if (DB::transactionLevel() < 1) {
            throw new LogicException('Event metrics must be captured inside a database transaction.');
        }
        if ($source === EventMetricSource::Manual && ($recorderPlayerId === null || $recorderPlayerId === '')) {
            throw ValidationException::withMessages([
                'metrics' => 'Manual Event metrics require a recording Player.',
            ]);
        }

        $occurrence = EventOccurrence::query()->whereKey($occurrenceId)->firstOrFail();
        $event = $occurrence->event()->firstOrFail();
        $keys = array_values(array_unique(array_map(
            static fn (array $metric): string => trim((string) ($metric['key'] ?? '')),
            $metrics,
        )));
        $definitions = EventMetricDefinition::query()
            ->where('event_type_scope_id', $event->event_type_scope_id)
            ->where('subject', $subject->value)
            ->whereIn('key', $keys)
            ->get()
            ->keyBy('key');

        $seen = [];
        $validated = [];

        foreach ($metrics as $index => $metric) {
            $key = trim((string) ($metric['key'] ?? ''));
            $definition = $definitions->get($key);
            if (! ($definition instanceof EventMetricDefinition)) {
                throw ValidationException::withMessages([
                    "metrics.$index.key" => 'This metric is not defined for the Event Type, scope, and subject.',
                ]);
            }

            $dimensionKey = $this->dimensionKey($occurrence, $definition, $metric['dimension_key'] ?? null, $index);
            $identity = $key."\0".$dimensionKey;
            if (isset($seen[$identity])) {
                throw ValidationException::withMessages([
                    "metrics.$index" => 'The same metric and dimension may appear only once per write.',
                ]);
            }
            $seen[$identity] = true;

            $validated[] = [
                'definition' => $definition,
                'dimension_key' => $dimensionKey,
                'value' => $this->value($definition, $metric['value'] ?? null, $index),
            ];
        }

        return $validated;
    }

    private function dimensionKey(
        EventOccurrence $occurrence,
        EventMetricDefinition $definition,
        mixed $rawDimension,
        int $index,
    ): string {
        $dimension = is_string($rawDimension) ? trim($rawDimension) : '';

        if ($definition->dimension_kind === null) {
            if ($dimension !== '') {
                throw ValidationException::withMessages([
                    "metrics.$index.dimension_key" => 'This metric does not accept a dimension.',
                ]);
            }

            return '';
        }

        if ($dimension === '') {
            throw ValidationException::withMessages([
                "metrics.$index.dimension_key" => 'This metric requires an occurrence dimension.',
            ]);
        }

        $exists = match ($definition->dimension_kind) {
            'phase' => EventPhase::query()
                ->where('occurrence_id', $occurrence->id)
                ->where('key', $dimension)
                ->exists(),
            'objective' => EventObjective::query()
                ->where('occurrence_id', $occurrence->id)
                ->whereKey($dimension)
                ->exists(),
            default => throw new LogicException('Unsupported Event metric dimension kind: '.$definition->dimension_kind),
        };

        if (! $exists) {
            throw ValidationException::withMessages([
                "metrics.$index.dimension_key" => 'Metric dimension does not exist on this Event occurrence.',
            ]);
        }

        return $dimension;
    }

    private function value(EventMetricDefinition $definition, mixed $value, int $index): string
    {
        if (! is_int($value) && ! is_float($value) && ! is_string($value)) {
            throw ValidationException::withMessages([
                "metrics.$index.value" => 'Metric value must be numeric.',
            ]);
        }

        $normalized = trim((string) $value);
        $pattern = $definition->value_type === EventMetricValueType::Integer
            ? '/^\d+$/'
            : '/^\d+(?:\.\d{1,4})?$/';

        if ($normalized === '' || preg_match($pattern, $normalized) !== 1) {
            throw ValidationException::withMessages([
                "metrics.$index.value" => $definition->value_type === EventMetricValueType::Integer
                    ? 'Metric value must be a non-negative integer.'
                    : 'Metric value must be a non-negative number with at most four decimal places.',
            ]);
        }

        [$whole] = explode('.', $normalized, 2);
        if (strlen(ltrim($whole, '0')) > 26) {
            throw ValidationException::withMessages([
                "metrics.$index.value" => 'Metric value is too large.',
            ]);
        }

        if ($definition->value_type === EventMetricValueType::Percentage && (float) $normalized > 100.0) {
            throw ValidationException::withMessages([
                "metrics.$index.value" => 'Percentage metrics must be between 0 and 100.',
            ]);
        }

        return $normalized;
    }
}
