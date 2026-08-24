<?php

declare(strict_types=1);

namespace App\Contexts\Operations\Results\Queries;

use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;
use App\Contexts\Operations\Events\Models\EventOccurrence;
use App\Contexts\Operations\Participation\Queries\EventEligiblePlayerQuery;
use App\Contexts\Operations\Results\Models\EventPlayerResult;
use App\Contexts\Operations\Results\Models\EventResult;

final readonly class EventResultCommandQuery
{
    public function __construct(private EventEligiblePlayerQuery $eligiblePlayers) {}

    /**
     * @return array{
     *   summaryExists:bool,
     *   eligiblePlayerCount:int,
     *   playerResultCount:int,
     *   missingPlayerResultCount:int,
     *   correctionStateSupported:bool,
     *   unresolvedCorrectionCount:int|null
     * }
     */
    public function forOccurrence(EventOccurrence $occurrence): array
    {
        $occurrence->loadMissing('event');
        $eligibleIds = $this->eligiblePlayers->for($occurrence->event)
            ->map(static fn (PlayerReference $player): string => $player->playerId)
            ->values()
            ->all();
        $resultCount = $eligibleIds === []
            ? 0
            : EventPlayerResult::query()
                ->where('occurrence_id', $occurrence->id)
                ->whereIn('player_id', $eligibleIds)
                ->distinct('player_id')
                ->count('player_id');

        return [
            'summaryExists' => EventResult::query()->where('occurrence_id', $occurrence->id)->exists(),
            'eligiblePlayerCount' => count($eligibleIds),
            'playerResultCount' => $resultCount,
            'missingPlayerResultCount' => max(0, count($eligibleIds) - $resultCount),
            'correctionStateSupported' => false,
            'unresolvedCorrectionCount' => null,
        ];
    }
}
