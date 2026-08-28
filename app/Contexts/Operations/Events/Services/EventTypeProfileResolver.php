<?php

declare(strict_types=1);

namespace App\Contexts\Operations\Events\Services;

use App\Contexts\Operations\Events\Enums\EventWorkflowDimension;
use App\Contexts\Operations\Events\Models\EventType;

final class EventTypeProfileResolver
{
    /**
     * @return array{
     *   id:string,
     *   canonical_key:string,
     *   name_key:string,
     *   verification_state:string,
     *   profile_state:string,
     *   profile_enabled:bool,
     *   source:array{label:string,reference:string,observed_at:string|null,game_version_boundary:string|null}|null,
     *   workflow_dimensions:list<string>
     * }
     */
    public function resolve(EventType $type): array
    {
        $type->loadMissing('workflowDimensions');

        $enabled = $type->profileEnabled();
        $dimensions = $enabled
            ? $type->workflowDimensions
                ->map(static fn ($row): string => $row->dimensionEnum()->value)
                ->sort()
                ->values()
                ->all()
            : [];

        return [
            'id' => (string) $type->id,
            'canonical_key' => (string) $type->slug,
            'name_key' => (string) $type->name_key,
            'verification_state' => $type->verificationStateEnum()->value,
            'profile_state' => $type->profileStateEnum()->value,
            'profile_enabled' => $enabled,
            'source' => $type->source_label === null || $type->source_reference === null ? null : [
                'label' => (string) $type->source_label,
                'reference' => (string) $type->source_reference,
                'observed_at' => $type->source_observed_at?->toAtomString(),
                'game_version_boundary' => $type->game_version_boundary,
            ],
            'workflow_dimensions' => array_values(array_filter(
                $dimensions,
                static fn (string $value): bool => EventWorkflowDimension::tryFrom($value) !== null,
            )),
        ];
    }
}
