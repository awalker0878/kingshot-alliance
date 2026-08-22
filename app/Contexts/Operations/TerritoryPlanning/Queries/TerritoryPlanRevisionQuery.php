<?php

declare(strict_types=1);

namespace App\Contexts\Operations\TerritoryPlanning\Queries;

use App\Contexts\Operations\TerritoryPlanning\Models\TerritoryPlanRevision;

final readonly class TerritoryPlanRevisionQuery
{
    public function __construct(private TerritoryPlanQuery $plans) {}

    /** @return array<string, mixed> */
    public function snapshot(string $actorPlayerId, string $planId, string $revisionId): array
    {
        $this->plans->detail($actorPlayerId, $planId);
        $revision = TerritoryPlanRevision::query()
            ->where('territory_plan_id', $planId)
            ->findOrFail($revisionId);

        return [
            'id' => $revision->id,
            'revision_number' => $revision->revision_number,
            'map_dataset_id' => $revision->map_dataset_id,
            'map_dataset_checksum' => $revision->map_dataset_checksum,
            'snapshot_checksum' => $revision->snapshot_checksum,
            'published_at' => $revision->published_at->toIso8601String(),
            'snapshot' => $revision->snapshot,
        ];
    }
}
