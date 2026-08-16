<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\EventAnalysis\Queries;

use App\Contexts\Alliance\Lifecycle\Models\Alliance;
use App\Contexts\GameWorld\Players\Models\Player;
use App\Contexts\Operations\Access\Enums\OperationsPermission;
use App\Contexts\Operations\Events\Enums\EventScope;
use App\Contexts\Operations\Events\Services\EventAuthorization;
use DateTimeInterface;

final readonly class EventAllianceHistoryQuery
{
    public function __construct(
        private EventAuthorization $authorization,
        private EventOrganizationHistoryQuery $history,
    ) {}

    /**
     * @param  array{event_type_slug?:string|null,from?:DateTimeInterface|null,until?:DateTimeInterface|null,limit?:int|null}  $filters
     * @return list<array<string,mixed>>
     */
    public function forAlliance(Player $actor, Alliance $alliance, array $filters = []): array
    {
        $this->authorization->authorize(
            $actor,
            EventScope::Alliance,
            $alliance,
            OperationsPermission::EventAllianceView,
        );

        return $this->history->forTarget(EventScope::Alliance, (string) $alliance->id, $filters);
    }
}
