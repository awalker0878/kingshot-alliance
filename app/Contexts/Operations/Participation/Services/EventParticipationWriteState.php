<?php

declare(strict_types=1);

namespace App\Contexts\Operations\Participation\Services;

use App\Contexts\Operations\Events\Enums\EventWorkflowDimension;
use App\Contexts\Operations\Events\Models\EventOccurrence;
use App\Contexts\Operations\Events\Services\EventAuthorization;
use App\Contexts\Operations\Events\Services\EventWorkflowGuard;
use App\Contexts\Operations\Events\Services\EventWriteState;
use App\Contexts\Operations\Events\ValueObjects\EventMutationContext;

/** The common current scope for self-service response and registration writes. */
final readonly class EventParticipationWriteState
{
    public function __construct(private EventWriteState $events, private EventAuthorization $authorization, private EventWorkflowGuard $workflows) {}

    /** @return array{EventMutationContext,EventOccurrence} */
    public function lock(string $actorId, string $occurrenceId, bool $exclusiveOccurrence): array
    {
        $route = EventOccurrence::query()->select(['id', 'event_id'])->whereKey($occurrenceId)->firstOrFail();
        $context = $this->events->lockSelfScope($actorId, (string) $route->event_id, $actorId);
        $this->authorization->authorizeSelf($context, $actorId);
        $this->workflows->require($context->event, EventWorkflowDimension::Participation);
        $query = EventOccurrence::query()->whereKey($occurrenceId)->where('event_id', $context->event->id);
        $occurrence = $exclusiveOccurrence ? $query->lockForUpdate()->firstOrFail() : $query->sharedLock()->firstOrFail();

        return [$context, $occurrence];
    }
}
