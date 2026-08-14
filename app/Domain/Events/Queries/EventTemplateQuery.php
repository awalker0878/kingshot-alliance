<?php

declare(strict_types=1);

namespace App\Domain\Events\Queries;

use App\Domain\Events\Models\EventTemplate;
use App\Domain\Events\Services\EventCreationContextResolver;
use App\Domain\Kingdoms\Models\Player;
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
                    $method = $first ? 'where' : 'orWhere';
                    $query->{$method}(function (Builder $scopeQuery) use ($context): void {
                        $scopeQuery->where('scope', $context['scope']);
                        $column = match ($context['scope']) {
                            'player' => 'player_id',
                            'alliance' => 'alliance_id',
                            'kingdom' => 'kingdom_id',
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
