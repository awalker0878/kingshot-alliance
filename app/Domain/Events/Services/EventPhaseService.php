<?php

declare(strict_types=1);

namespace App\Domain\Events\Services;

use App\Contexts\GameWorld\Models\Player;
use App\Domain\Events\Enums\EventCapability;
use App\Domain\Events\Enums\EventPhaseStatus;
use App\Domain\Events\Enums\EventPhaseType;
use App\Domain\Events\Models\EventOccurrence;
use App\Domain\Events\Models\EventPhase;
use Carbon\CarbonImmutable;

final readonly class EventPhaseService
{
    public function __construct(private EventCapabilityResolver $capabilities) {}

    public function materializeDefaults(EventOccurrence $occurrence, ?Player $actor = null): void
    {
        $occurrence->loadMissing('event.typeScope.capabilities');
        $event = $occurrence->event;
        if (! $this->capabilities->supports($event->typeScope, EventCapability::Phases)) {
            return;
        }

        $configuration = $this->capabilities->configuration($event->typeScope, EventCapability::Phases);
        $definitions = $configuration['default_phases'] ?? [];
        if (! is_array($definitions)) {
            return;
        }

        foreach ($definitions as $index => $definition) {
            if (! is_array($definition) || ! isset($definition['key'], $definition['phase_type'])) {
                continue;
            }

            $key = (string) $definition['key'];
            $offset = (int) ($definition['start_offset_minutes'] ?? 0);
            $duration = (int) ($definition['duration_minutes'] ?? 0);
            if ($duration < 1) {
                continue;
            }

            $startsAt = CarbonImmutable::instance($occurrence->starts_at)->utc()->addMinutes($offset);
            $endsAt = $startsAt->addMinutes($duration);
            $existing = EventPhase::query()
                ->where('occurrence_id', $occurrence->id)
                ->where('key', $key)
                ->first();

            if ($existing instanceof EventPhase && ($existing->settings['source'] ?? null) !== 'catalogue') {
                continue;
            }

            $values = [
                'name_key' => isset($definition['name_key']) ? (string) $definition['name_key'] : null,
                'name' => isset($definition['name']) ? (string) $definition['name'] : null,
                'phase_type' => EventPhaseType::from((string) $definition['phase_type']),
                'starts_at' => $startsAt,
                'ends_at' => $endsAt,
                'status' => EventPhaseStatus::Scheduled,
                'sort_order' => (int) ($definition['sort_order'] ?? $index),
                'settings' => [
                    'source' => 'catalogue',
                    'start_offset_minutes' => $offset,
                    'duration_minutes' => $duration,
                ],
                'updated_by_player_id' => $actor?->id,
            ];

            if ($existing instanceof EventPhase) {
                $existing->forceFill($values)->save();
            } else {
                EventPhase::query()->create([
                    'occurrence_id' => $occurrence->id,
                    'key' => $key,
                    ...$values,
                    'created_by_player_id' => $actor?->id,
                ]);
            }
        }
    }

    public function effectiveStatus(EventPhase $phase): EventPhaseStatus
    {
        if ($phase->status === EventPhaseStatus::Cancelled) {
            return EventPhaseStatus::Cancelled;
        }
        if ($phase->status === EventPhaseStatus::Completed) {
            return EventPhaseStatus::Completed;
        }
        if ($phase->starts_at === null || $phase->ends_at === null) {
            return $phase->status;
        }

        $now = CarbonImmutable::now('UTC');
        if ($now->greaterThanOrEqualTo(CarbonImmutable::instance($phase->ends_at)->utc())) {
            return EventPhaseStatus::Completed;
        }
        if ($now->greaterThanOrEqualTo(CarbonImmutable::instance($phase->starts_at)->utc())) {
            return EventPhaseStatus::Active;
        }

        return EventPhaseStatus::Scheduled;
    }
}
