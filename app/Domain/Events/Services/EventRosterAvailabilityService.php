<?php

declare(strict_types=1);

namespace App\Domain\Events\Services;

use App\Domain\Events\Enums\EventCapability;
use App\Domain\Events\Enums\EventRegistrationStatus;
use App\Domain\Events\Enums\EventResponseChoice;
use App\Domain\Events\Models\EventOccurrence;
use App\Domain\Events\Models\EventRegistration;
use App\Domain\Events\Models\EventResponse;
use App\Contexts\GameWorld\Models\Player;
use Carbon\CarbonImmutable;

final readonly class EventRosterAvailabilityService
{
    public function __construct(private EventCapabilityResolver $capabilities) {}

    /** @return list<string> */
    public function warnings(EventOccurrence $occurrence, Player $player): array
    {
        $occurrence->loadMissing('event.typeScope');
        $warnings = [];
        $response = EventResponse::query()
            ->where('occurrence_id', $occurrence->id)
            ->where('player_id', $player->id)
            ->first();

        if (! $response instanceof EventResponse) {
            if ($this->capabilities->supports($occurrence->event->typeScope, EventCapability::Responses)) {
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

        if ($this->capabilities->supports($occurrence->event->typeScope, EventCapability::Registration)) {
            $registration = EventRegistration::query()
                ->where('occurrence_id', $occurrence->id)
                ->where('player_id', $player->id)
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
