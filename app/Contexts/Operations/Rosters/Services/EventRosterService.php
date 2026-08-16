<?php

declare(strict_types=1);

namespace App\Contexts\Operations\Rosters\Services;

use App\Contexts\GameWorld\Players\Models\Player;
use App\Contexts\Operations\Events\Enums\EventCapability;
use App\Contexts\Operations\Events\Models\EventOccurrence;
use App\Contexts\Operations\Events\Services\EventCapabilityResolver;
use App\Contexts\Operations\Rosters\Enums\EventRosterType;
use App\Contexts\Operations\Rosters\Models\EventRoster;

final readonly class EventRosterService
{
    public function __construct(private EventCapabilityResolver $capabilities) {}

    public function materializeDefaults(EventOccurrence $occurrence, ?Player $actor = null): void
    {
        $occurrence->loadMissing('event.typeScope.capabilities');
        $event = $occurrence->event;
        if (! $this->capabilities->supports($event->typeScope, EventCapability::Rosters)) {
            return;
        }

        $configuration = $this->capabilities->configuration($event->typeScope, EventCapability::Rosters);
        $definitions = $configuration['default_rosters'] ?? [];
        if (! is_array($definitions)) {
            return;
        }

        /** @var array<string, EventRoster> $rows */
        $rows = [];
        foreach ($definitions as $index => $definition) {
            if (! is_array($definition) || ! isset($definition['key'], $definition['roster_type'])) {
                continue;
            }

            $key = (string) $definition['key'];
            $existing = EventRoster::query()
                ->where('occurrence_id', $occurrence->id)
                ->where('key', $key)
                ->first();
            if ($existing instanceof EventRoster && ($existing->settings['source'] ?? null) !== 'catalogue') {
                $rows[$key] = $existing;

                continue;
            }

            $values = [
                'name_key' => isset($definition['name_key']) ? (string) $definition['name_key'] : null,
                'name' => isset($definition['name']) ? (string) $definition['name'] : null,
                'roster_type' => EventRosterType::from((string) $definition['roster_type']),
                'assignment_group' => (string) ($definition['assignment_group'] ?? 'primary'),
                'capacity' => isset($definition['capacity']) ? max(1, (int) $definition['capacity']) : null,
                'sort_order' => (int) ($definition['sort_order'] ?? $index),
                'settings' => [
                    'source' => 'catalogue',
                    'parent_key' => isset($definition['parent_key']) ? (string) $definition['parent_key'] : null,
                ],
                'updated_by_player_id' => $actor?->id,
            ];

            if ($existing instanceof EventRoster) {
                $existing->forceFill($values)->save();
                $rows[$key] = $existing;
            } else {
                $rows[$key] = EventRoster::query()->create([
                    'occurrence_id' => $occurrence->id,
                    'key' => $key,
                    ...$values,
                    'created_by_player_id' => $actor?->id,
                ]);
            }
        }

        foreach ($definitions as $definition) {
            if (! is_array($definition) || ! isset($definition['key'])) {
                continue;
            }
            $key = (string) $definition['key'];
            $parentKey = isset($definition['parent_key']) ? (string) $definition['parent_key'] : null;
            $row = $rows[$key] ?? null;
            if (! $row instanceof EventRoster) {
                continue;
            }
            $parentId = $parentKey === null ? null : ($rows[$parentKey]->id ?? null);
            if ((string) ($row->parent_id ?? '') !== (string) ($parentId ?? '')) {
                $row->forceFill(['parent_id' => $parentId])->save();
            }
        }
    }
}
