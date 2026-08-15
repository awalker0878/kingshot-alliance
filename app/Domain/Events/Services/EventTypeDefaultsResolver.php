<?php

declare(strict_types=1);

namespace App\Domain\Events\Services;

use App\Domain\Events\Models\EventMetricDefinition;
use App\Domain\Events\Models\EventTypeScope;

final class EventTypeDefaultsResolver
{
    /** @return array<string, mixed> */
    public function resolve(EventTypeScope $configuration): array
    {
        $stored = EventTypeScope::query()
            ->whereKey($configuration->id)
            ->with(['capabilities', 'metricDefinitions'])
            ->firstOrFail();
        $eventType = $stored->eventType()->firstOrFail();

        $capabilities = [];
        foreach ($stored->capabilities as $capability) {
            $capabilities[$capability->capabilityEnum()->value] = $capability->configuration ?? [];
        }

        $metrics = $stored->metricDefinitions
            ->map(static fn (EventMetricDefinition $definition): array => [
                'id' => (string) $definition->id,
                'subject' => $definition->subject->value,
                'key' => (string) $definition->key,
                'label_key' => (string) $definition->label_key,
                'unit' => $definition->unit,
                'value_type' => $definition->value_type->value,
                'aggregation' => $definition->aggregation->value,
                'dimension_kind' => $definition->dimension_kind,
                'is_primary' => $definition->is_primary,
                'is_contribution_metric' => $definition->is_contribution_metric,
                'higher_is_better' => $definition->higher_is_better,
                'sort_order' => $definition->sort_order,
            ])
            ->values()
            ->all();

        return [
            'event_type_scope_id' => (string) $stored->id,
            'event_type_id' => (string) $stored->event_type_id,
            'event_type_slug' => (string) $eventType->slug,
            'scope' => $stored->scopeEnum()->value,
            'schedule_source' => $stored->scheduleSourceEnum()->value,
            'recurrence_policy' => $stored->recurrencePolicyEnum()->value,
            'recurrence_allowed' => $stored->allowsRecurrence(),
            'default_duration_minutes' => $stored->default_duration_minutes,
            'default_capacity' => $stored->default_capacity,
            'default_recurrence_frequency' => $stored->defaultRecurrenceFrequencyEnum()->value,
            'default_recurrence_interval' => $stored->default_recurrence_interval,
            'minimum_repeat_interval_minutes' => $stored->minimum_repeat_interval_minutes,
            'default_registration_opens_minutes_before' => $stored->default_registration_opens_minutes_before,
            'default_registration_closes_minutes_before' => $stored->default_registration_closes_minutes_before,
            'default_instructions_key' => $stored->default_instructions_key,
            'default_settings' => $stored->default_settings ?? [],
            'result_score' => $stored->result_score_label_key === null ? null : [
                'label_key' => $stored->result_score_label_key,
                'unit' => $stored->result_score_unit,
                'higher_is_better' => $stored->result_score_higher_is_better,
            ],
            'metrics' => $metrics,
            'capabilities' => $capabilities,
        ];
    }
}
