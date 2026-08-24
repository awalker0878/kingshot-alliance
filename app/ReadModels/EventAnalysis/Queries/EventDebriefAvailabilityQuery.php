<?php

declare(strict_types=1);

namespace App\ReadModels\EventAnalysis\Queries;

use App\Contexts\Operations\Events\Enums\EventScope;
use App\Contexts\Operations\Events\Models\Event;
use App\Contexts\Operations\Events\Models\EventOccurrence;
use App\Contexts\Operations\Results\Queries\BearHuntDebriefResultQuery;

final readonly class EventDebriefAvailabilityQuery
{
    public function __construct(private BearHuntDebriefResultQuery $bearHuntResults) {}

    /**
     * @return array{supported:bool,available:bool,href:?string}
     */
    public function forOccurrence(EventOccurrence $occurrence): array
    {
        $occurrence->loadMissing('event.eventType');
        $event = $occurrence->event;
        if (! $event instanceof Event
            || $event->scopeEnum() !== EventScope::Alliance
            || $event->eventType?->slug !== 'bear-hunt') {
            return ['supported' => false, 'available' => false, 'href' => null];
        }

        $results = $this->bearHuntResults->forOccurrence((string) $occurrence->id);

        return [
            'supported' => true,
            'available' => (bool) $results['available'],
            'href' => '/events/'.(string) $occurrence->id.'/debrief',
        ];
    }
}
