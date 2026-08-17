<?php

declare(strict_types=1);

namespace App\ReadModels\EventAnalysis\Queries;

use App\Contexts\GameWorld\Kingdoms\Models\Kingdom;
use App\Contexts\GameWorld\Players\Models\Player;
use App\Contexts\Operations\Access\Enums\OperationsPermission;
use App\Contexts\Operations\Events\Enums\EventScope;
use App\Contexts\Operations\Events\Services\EventAuthorization;
use DateTimeInterface;

final readonly class EventKingdomHistoryQuery
{
    public function __construct(
        private EventAuthorization $authorization,
        private EventOrganizationHistoryQuery $history,
    ) {}

    /**
     * @param  array{event_type_slug?:string|null,from?:DateTimeInterface|null,until?:DateTimeInterface|null,limit?:int|null}  $filters
     * @return list<array<string,mixed>>
     */
    public function forKingdom(Player $actor, Kingdom $kingdom, array $filters = []): array
    {
        $this->authorization->authorize(
            $actor,
            EventScope::Kingdom,
            $kingdom,
            OperationsPermission::EventKingdomView,
        );

        return $this->history->forTarget(EventScope::Kingdom, (string) $kingdom->id, $filters);
    }
}
