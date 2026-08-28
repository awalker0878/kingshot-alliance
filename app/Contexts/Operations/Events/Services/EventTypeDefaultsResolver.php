<?php

declare(strict_types=1);

namespace App\Contexts\Operations\Events\Services;

use App\Contexts\Operations\Events\Models\EventTypeScope;

/**
 * Resolves application-owned event creation context only.
 *
 * Phase 13 intentionally removed generic event-type game defaults from this
 * contract. Kingshot mechanics must come from their sourced owning capability,
 * never from an Event profile.
 */
final readonly class EventTypeDefaultsResolver
{
    public function __construct(private EventTypeProfileResolver $profiles) {}

    /** @return array<string, mixed> */
    public function resolve(EventTypeScope $configuration): array
    {
        $stored = EventTypeScope::query()
            ->whereKey($configuration->id)
            ->with('eventType.workflowDimensions')
            ->firstOrFail();
        $eventType = $stored->eventType;

        return [
            'event_type_scope_id' => (string) $stored->id,
            'event_type_id' => (string) $stored->event_type_id,
            'event_type_slug' => (string) $eventType->slug,
            'scope' => $stored->scopeEnum()->value,
            'profile' => $this->profiles->resolve($eventType),
        ];
    }
}
