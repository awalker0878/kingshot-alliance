<?php

declare(strict_types=1);

namespace App\Domain\Events\Services;

use App\Domain\Events\Models\EventTypeScope;

final class EventTypeDefaultsResolver
{
    /** @return array<string, mixed> */
    public function resolve(EventTypeScope $configuration): array
    {
        $stored = EventTypeScope::query()
            ->whereKey($configuration->id)
            ->with(['eventType', 'capabilities'])
            ->firstOrFail();

        $capabilities = [];
        foreach ($stored->capabilities as $capability) {
            $capabilities[$capability->capability->value] = $capability->configuration ?? [];
        }

        return [
            'event_type_scope_id' => (string) $stored->id,
            'event_type_id' => (string) $stored->event_type_id,
            'event_type_slug' => (string) $stored->eventType->slug,
            'scope' => $stored->scope->value,
            'schedule_source' => $stored->schedule_source->value,
            'recurrence_policy' => $stored->recurrence_policy->value,
            'recurrence_allowed' => $stored->recurrence_policy->allowsRecurrence(),
            'default_duration_minutes' => $stored->default_duration_minutes,
            'default_capacity' => $stored->default_capacity,
            'default_recurrence_frequency' => $stored->default_recurrence_frequency->value,
            'default_recurrence_interval' => $stored->default_recurrence_interval,
            'minimum_repeat_interval_minutes' => $stored->minimum_repeat_interval_minutes,
            'default_registration_opens_minutes_before' => $stored->default_registration_opens_minutes_before,
            'default_registration_closes_minutes_before' => $stored->default_registration_closes_minutes_before,
            'default_instructions_key' => $stored->default_instructions_key,
            'default_settings' => $stored->default_settings ?? [],
            'capabilities' => $capabilities,
        ];
    }
}
