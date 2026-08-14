<?php

declare(strict_types=1);

namespace App\Domain\Events\Services;

use App\Domain\Events\Enums\EventScope;
use App\Domain\Events\Models\EventType;
use App\Domain\Events\Models\EventTypeScope;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\ModelNotFoundException;

final class EventTypeRegistry
{
    /** @return Collection<int, EventType> */
    public function activeForScope(EventScope $scope): Collection
    {
        return EventType::query()
            ->where('is_active', true)
            ->whereHas('scopes', static fn ($query) => $query
                ->where('scope', $scope->value)
                ->where('is_active', true))
            ->with(['scopes' => static fn ($query) => $query
                ->where('scope', $scope->value)
                ->where('is_active', true)
                ->with('capabilities')])
            ->orderBy('sort_order')
            ->orderBy('slug')
            ->get();
    }

    public function scope(EventType $type, EventScope $scope): EventTypeScope
    {
        $configuration = $type->scopes()
            ->where('scope', $scope->value)
            ->where('is_active', true)
            ->with('capabilities')
            ->first();

        if (! $configuration instanceof EventTypeScope || ! $type->is_active) {
            throw (new ModelNotFoundException)->setModel(EventTypeScope::class);
        }

        return $configuration;
    }
}
