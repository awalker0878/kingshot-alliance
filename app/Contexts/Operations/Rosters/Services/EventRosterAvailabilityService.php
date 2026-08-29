<?php

declare(strict_types=1);

namespace App\Contexts\Operations\Rosters\Services;

use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;
use App\Contexts\Operations\Events\Enums\EventWorkflowDimension;
use App\Contexts\Operations\Events\Models\EventOccurrence;
use App\Contexts\Operations\Events\Services\EventWorkflowGuard;
use App\Contexts\Operations\Participation\Enums\EventRegistrationStatus;
use App\Contexts\Operations\Participation\Enums\EventResponseChoice;
use App\Contexts\Operations\Participation\Models\EventRegistration;
use App\Contexts\Operations\Participation\Models\EventResponse;
use Carbon\CarbonImmutable;

final readonly class EventRosterAvailabilityService
{
    public function __construct(private EventWorkflowGuard $workflows) {}

    /** @return list<string> */
    public function warnings(EventOccurrence $occurrence, PlayerReference $player): array
    {
        $occurrence->loadMissing('event.eventType.workflowDimensions');
        $participationEnabled = $this->workflows->supports(
            $occurrence->event,
            EventWorkflowDimension::Participation,
        );
        $warnings = [];
        $response = EventResponse::query()
            ->where('occurrence_id', $occurrence->id)
            ->where('player_id', $player->playerId)
            ->first();

        if (! $response instanceof EventResponse) {
            if ($participationEnabled) {
                $warnings[] = 'no_response';
            }
        } else {
            if ($response->response === EventResponseChoice::Unavailable) {
                $warnings[] = 'unavailable';
            } elseif ($response->response === EventResponseChoice::Maybe) {
                $warnings[] = 'maybe';
            }

            $startsAt = CarbonImmutable::instance($occurrence->starts_at)->utc();
            $endsAt = CarbonImmutable::instance($occurrence->ends_at)->utc();
            if (($response->available_from !== null && $startsAt->lessThan(CarbonImmutable::instance($response->available_from)->utc()))
                || ($response->available_until !== null && $endsAt->greaterThan(CarbonImmutable::instance($response->available_until)->utc()))) {
                $warnings[] = 'outside_availability_window';
            }
        }

        if ($participationEnabled) {
            $registration = EventRegistration::query()
                ->where('occurrence_id', $occurrence->id)
                ->where('player_id', $player->playerId)
                ->first();
            if (! $registration instanceof EventRegistration || $registration->status === EventRegistrationStatus::Cancelled) {
                $warnings[] = 'not_registered';
            } elseif ($registration->status === EventRegistrationStatus::Waitlisted) {
                $warnings[] = 'waitlisted';
            }
        }

        return array_values(array_unique($warnings));
    }
}
