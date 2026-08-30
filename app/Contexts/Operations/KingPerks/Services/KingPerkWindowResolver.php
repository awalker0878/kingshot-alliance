<?php

declare(strict_types=1);

namespace App\Contexts\Operations\KingPerks\Services;

use App\Contexts\Operations\Events\Enums\EventPhaseType;
use App\Contexts\Operations\Events\Enums\EventScope;
use App\Contexts\Operations\Events\Models\Event;
use App\Contexts\Operations\Events\Models\EventOccurrence;
use App\Contexts\Operations\Events\Models\EventPhase;
use Carbon\CarbonImmutable;
use DateTimeInterface;
use Illuminate\Validation\ValidationException;

final class KingPerkWindowResolver
{
    /** @return array{starts_at: CarbonImmutable, ends_at: CarbonImmutable} */
    public function forOccurrence(EventOccurrence $occurrence): array
    {
        $occurrence->loadMissing(['event.eventType', 'event.typeScope', 'phases']);
        $event = $occurrence->event;

        if (! $event instanceof Event
            || $event->scope !== EventScope::Kingdom
            || $event->eventType?->slug !== 'kingdom-of-power') {
            throw ValidationException::withMessages([
                'event' => 'King Perks are only available for Kingdom-scoped Kingdom of Power Events.',
            ]);
        }

        $phase = $occurrence->phases->first(
            static fn (EventPhase $candidate): bool => $candidate->phase_type === EventPhaseType::Preparation
                && $candidate->starts_at instanceof DateTimeInterface
                && $candidate->ends_at instanceof DateTimeInterface,
        );

        if ($phase instanceof EventPhase
            && $phase->starts_at instanceof DateTimeInterface
            && $phase->ends_at instanceof DateTimeInterface) {
            return [
                'starts_at' => CarbonImmutable::instance($phase->starts_at)->utc(),
                'ends_at' => CarbonImmutable::instance($phase->ends_at)->utc(),
            ];
        }

        $minutes = data_get($event->settings, 'preparation_phase_minutes');

        if (! is_numeric($minutes) || (int) $minutes < 1) {
            throw ValidationException::withMessages([
                'event' => 'The Kingdom of Power Event does not define a preparation window.',
            ]);
        }

        $endsAt = CarbonImmutable::instance($occurrence->starts_at)->utc();

        return [
            'starts_at' => $endsAt->subMinutes((int) $minutes),
            'ends_at' => $endsAt,
        ];
    }
}
