<?php

declare(strict_types=1);

namespace App\Contexts\Operations\TerritoryPlanning\Actions;

use App\Contexts\Operations\TerritoryPlanning\Services\TerritoryRecoveryDrafts;

final readonly class SaveTerritoryRecoveryDraft
{
    public function __construct(private TerritoryRecoveryDrafts $drafts) {}

    /**
     * @param  array<string,mixed>  $document
     * @return array<string,mixed>
     */
    public function handle(string $actorPlayerId, string $planId, int $baseRevision, string $mapDatasetId, string $mapDatasetChecksum, array $document): array
    {
        return $this->drafts->store($actorPlayerId, $planId, $baseRevision, $mapDatasetId, $mapDatasetChecksum, $document);
    }
}
