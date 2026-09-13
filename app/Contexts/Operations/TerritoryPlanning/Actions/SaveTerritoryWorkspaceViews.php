<?php

declare(strict_types=1);

namespace App\Contexts\Operations\TerritoryPlanning\Actions;

use App\Contexts\GameWorld\KingdomMaps\Queries\KingdomMapDatasetQuery;
use App\Contexts\GameWorld\Kingdoms\Queries\KingdomReferenceQuery;
use App\Contexts\GameWorld\Players\Queries\PlayerReferenceQuery;
use App\Contexts\Operations\TerritoryPlanning\Exceptions\TerritoryRevisionConflict;
use App\Contexts\Operations\TerritoryPlanning\Models\TerritoryWorkspaceView;
use App\Contexts\Operations\TerritoryPlanning\Services\TerritoryWorkspaceViewContract;
use Illuminate\Support\Facades\DB;

final readonly class SaveTerritoryWorkspaceViews
{
    public function __construct(
        private PlayerReferenceQuery $players,
        private KingdomReferenceQuery $kingdoms,
        private KingdomMapDatasetQuery $datasets,
        private TerritoryWorkspaceViewContract $contract,
    ) {}

    /** @param array<mixed> $views
     * @return array{revision:int,views:list<array<string,mixed>>}
     */
    public function handle(int $userId, string $playerId, string $kingdomId, string $datasetId, string $checksum, int $expectedRevision, array $views): array
    {
        $dataset = $this->datasets->require($datasetId, $checksum);
        $normalized = $this->contract->validate($views, $dataset);

        return DB::transaction(function () use ($userId, $playerId, $kingdomId, $datasetId, $checksum, $expectedRevision, $normalized): array {
            $this->kingdoms->lockActiveShared($kingdomId);
            $player = $this->players->lockCurrent($playerId);
            abort_unless($player->userId === $userId && $player->kingdomId === $kingdomId, 403);
            $record = TerritoryWorkspaceView::query()->where('player_id', $playerId)
                ->where('kingdom_id', $kingdomId)->where('map_dataset_id', $datasetId)->lockForUpdate()->first();
            $revision = $record->revision ?? 0;
            if ($revision !== $expectedRevision) {
                throw new TerritoryRevisionConflict($expectedRevision, $revision);
            }
            $record ??= new TerritoryWorkspaceView;
            $record->fill([
                'player_id' => $playerId,
                'kingdom_id' => $kingdomId,
                'map_dataset_id' => $datasetId,
                'map_dataset_checksum' => $checksum,
                'revision' => $revision + 1,
                'views' => $normalized,
            ])->save();

            return ['revision' => $record->revision, 'views' => $record->views];
        });
    }
}
