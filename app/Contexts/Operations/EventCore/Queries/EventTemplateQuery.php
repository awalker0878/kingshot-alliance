<?php

declare(strict_types=1);

namespace App\Contexts\Operations\EventCore\Queries;

use App\Contexts\GameWorld\Models\Player;
use App\Contexts\Operations\EventCore\Enums\EventScope;
use App\Contexts\Operations\EventCore\Models\EventTemplate;
use App\Contexts\Operations\EventCore\Services\EventCreationContextResolver;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;

final class EventTemplateQuery
{
    public function __construct(private EventCreationContextResolver $contexts) {}

    /** @return Collection<int, EventTemplate> */
    public function available(Player $actor): Collection
    {
        $contexts = $this->contexts->forPlayer($actor);
        if ($contexts === []) {
            return new Collection;
        }

        return EventTemplate::query()
            ->where('is_active', true)
            ->where(function (Builder $query) use ($contexts): void {
                $first = true;
                foreach ($contexts as $context) {
                    $scope = EventScope::from((string) $context['scope']);
                    $method = $first ? 'where' : 'orWhere';
                    $query->{$method}(function (Builder $scopeQuery) use ($context, $scope): void {
                        $scopeQuery->where('scope', $scope->value);
                        $column = match ($scope) {
                            EventScope::Player => 'player_id',
                            EventScope::Alliance => 'alliance_id',
                            EventScope::Kingdom => 'kingdom_id',
                        };
                        $scopeQuery->where($column, $context['targetId']);
                    });
                    $first = false;
                }
            })
            ->with(['eventType', 'typeScope', 'alliance', 'kingdom', 'player'])
            ->orderBy('name')
            ->get();
    }
}
