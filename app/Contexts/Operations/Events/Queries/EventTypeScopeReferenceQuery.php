<?php

declare(strict_types=1);

namespace App\Contexts\Operations\Events\Queries;

use App\Contexts\Operations\Events\Enums\EventScope;
use App\Contexts\Operations\Events\Models\EventTypeScope;

final class EventTypeScopeReferenceQuery
{
    public function requireConfigurationId(string $eventTypeId, EventScope $scope): string
    {
        $configuration = EventTypeScope::query()
            ->where('event_type_id', $eventTypeId)
            ->where('scope', $scope->value)
            ->firstOrFail(['id']);

        return (string) $configuration->id;
    }
}
