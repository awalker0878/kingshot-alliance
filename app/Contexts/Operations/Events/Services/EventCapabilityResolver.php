<?php

declare(strict_types=1);

namespace App\Contexts\Operations\Events\Services;

use App\Contexts\Operations\Events\Enums\EventCapability;
use App\Contexts\Operations\Events\Models\EventTypeCapability;
use App\Contexts\Operations\Events\Models\EventTypeScope;

final class EventCapabilityResolver
{
    public function supports(EventTypeScope $configuration, EventCapability $capability): bool
    {
        if ($configuration->relationLoaded('capabilities')) {
            return $configuration->capabilities->contains(
                static fn (EventTypeCapability $row): bool => $row->capabilityEnum() === $capability,
            );
        }

        return $configuration->capabilities()
            ->where('capability', $capability->value)
            ->exists();
    }

    /** @return array<string, mixed> */
    public function configuration(EventTypeScope $configuration, EventCapability $capability): array
    {
        $row = $configuration->relationLoaded('capabilities')
            ? $configuration->capabilities->first(
                static fn (EventTypeCapability $item): bool => $item->capabilityEnum() === $capability,
            )
            : $configuration->capabilities()->where('capability', $capability->value)->first();

        return is_array($row?->configuration) ? $row->configuration : [];
    }

    /** @return list<string> */
    public function keys(EventTypeScope $configuration): array
    {
        $keys = [];

        foreach ($configuration->capabilities()->orderBy('capability')->pluck('capability') as $value) {
            $keys[] = (string) $value;
        }

        return $keys;
    }
}
