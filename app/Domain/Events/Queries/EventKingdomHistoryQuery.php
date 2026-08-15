<?php

declare(strict_types=1);

namespace App\Domain\Events\Queries;

use App\Domain\Authorization\Enums\PermissionKey;
use App\Domain\Events\Enums\EventScope;
use App\Domain\Events\Services\EventAuthorization;
use App\Domain\Kingdoms\Models\Kingdom;
use App\Domain\Kingdoms\Models\Player;
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
            PermissionKey::EventKingdomView,
        );

        return $this->history->forTarget(EventScope::Kingdom, (string) $kingdom->id, $filters);
    }
}
