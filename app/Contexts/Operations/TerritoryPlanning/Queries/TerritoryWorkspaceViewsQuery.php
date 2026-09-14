<?php

declare(strict_types=1);

namespace App\Contexts\Operations\TerritoryPlanning\Queries;

use App\Contexts\GameWorld\KingdomMaps\Queries\KingdomMapDatasetQuery;
use App\Contexts\GameWorld\Kingdoms\Queries\KingdomReferenceQuery;
use App\Contexts\GameWorld\Players\Queries\PlayerReferenceQuery;
use App\Contexts\Operations\TerritoryPlanning\Models\TerritoryWorkspaceView;

final readonly class TerritoryWorkspaceViewsQuery
{
    public function __construct(private PlayerReferenceQuery $players, private KingdomReferenceQuery $kingdoms, private KingdomMapDatasetQuery $datasets) {}

    /** @return array{revision:int,views:list<array<string,mixed>>} */
    public function forPlayer(int $userId, string $playerId, string $kingdomId, string $datasetId, string $checksum): array
    {
        $player = $this->players->findOwnedByUser($userId, $playerId);
        abort_unless($player !== null && $player->kingdomId === $kingdomId, 403);
        $this->kingdoms->requireActive($kingdomId);
        $this->datasets->require($datasetId, $checksum);
        $record = TerritoryWorkspaceView::query()->where('player_id', $playerId)->where('kingdom_id', $kingdomId)
            ->where('map_dataset_id', $datasetId)->where('map_dataset_checksum', $checksum)->first();

        return ['revision' => $record->revision ?? 0, 'views' => $record->views ?? []];
    }
}
