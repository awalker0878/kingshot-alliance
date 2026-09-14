<?php

declare(strict_types=1);

namespace App\Contexts\Operations\TerritoryPlanning\Actions;

use App\Contexts\Operations\TerritoryPlanning\Services\TerritoryRecoveryDrafts;

final readonly class DiscardTerritoryRecoveryDraft
{
    public function __construct(private TerritoryRecoveryDrafts $drafts) {}

    public function handle(string $actorPlayerId, string $planId): bool
    {
        return $this->drafts->delete($actorPlayerId, $planId);
    }
}
