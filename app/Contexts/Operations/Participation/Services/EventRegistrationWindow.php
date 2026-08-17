<?php

declare(strict_types=1);

namespace App\Contexts\Operations\Participation\Services;

use App\Contexts\Operations\Events\Models\Event;
use App\Contexts\Operations\Events\Models\EventOccurrence;
use Carbon\CarbonImmutable;

final class EventRegistrationWindow
{
    /** @return array{opens_at:?CarbonImmutable,closes_at:CarbonImmutable,is_open:bool} */
    public function for(Event $event, EventOccurrence $occurrence, ?CarbonImmutable $at = null): array
    {
        $at ??= CarbonImmutable::now('UTC');
        $start = CarbonImmutable::instance($occurrence->starts_at)->utc();
        $opensAt = $event->registration_opens_minutes_before === null
            ? null
            : $start->subMinutes((int) $event->registration_opens_minutes_before);
        $closesAt = $event->registration_closes_minutes_before === null
            ? $start
            : $start->subMinutes((int) $event->registration_closes_minutes_before);

        return [
            'opens_at' => $opensAt,
            'closes_at' => $closesAt,
            'is_open' => ($opensAt === null || $at->greaterThanOrEqualTo($opensAt)) && $at->lessThan($closesAt),
        ];
    }
}
