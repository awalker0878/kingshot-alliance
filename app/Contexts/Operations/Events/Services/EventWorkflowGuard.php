<?php

declare(strict_types=1);

namespace App\Contexts\Operations\Events\Services;

use App\Contexts\Operations\Events\Enums\EventWorkflowDimension;
use App\Contexts\Operations\Events\Models\Event;
use Illuminate\Validation\ValidationException;

/**
 * Enforces the verified Kingshot event-profile boundary for specialized owner
 * workflows. Baseline scheduling and independently authored workflows such as
 * Polls, phases and King Perks deliberately do not use this guard.
 */
final class EventWorkflowGuard
{
    public function supports(Event $event, EventWorkflowDimension $dimension): bool
    {
        $event->loadMissing('eventType.workflowDimensions');

        return $event->eventType->supportsWorkflow($dimension);
    }

    public function require(Event $event, EventWorkflowDimension $dimension): void
    {
        if (! $this->supports($event, $dimension)) {
            throw ValidationException::withMessages([
                'event' => sprintf(
                    'This verified Event profile does not support the %s workflow.',
                    str_replace('_', ' ', $dimension->value),
                ),
            ]);
        }
    }

    /** @param non-empty-list<EventWorkflowDimension> $dimensions */
    public function requireAll(Event $event, array $dimensions): void
    {
        foreach ($dimensions as $dimension) {
            $this->require($event, $dimension);
        }
    }
}
