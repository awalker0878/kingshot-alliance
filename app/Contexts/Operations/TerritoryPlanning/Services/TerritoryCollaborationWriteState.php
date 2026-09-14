<?php

declare(strict_types=1);

namespace App\Contexts\Operations\TerritoryPlanning\Services;

use App\Contexts\Operations\TerritoryPlanning\Enums\TerritoryPlanStatus;
use App\Contexts\Operations\TerritoryPlanning\ValueObjects\TerritoryPlanMutationContext;
use Illuminate\Validation\ValidationException;

final readonly class TerritoryCollaborationWriteState
{
    public function __construct(private TerritoryPlanWriteState $state, private TerritoryPlanningAuthorization $authorization) {}

    public function lock(string $actorPlayerId, string $planId, ?int $expectedRevision = null): TerritoryPlanMutationContext
    {
        $context = $this->state->lock($actorPlayerId, $planId);
        $this->authorization->authorizeView($context);
        if ($context->plan->status === TerritoryPlanStatus::Archived) {
            throw ValidationException::withMessages(['plan' => 'Archived plans cannot be changed.']);
        }
        if ($expectedRevision !== null && $context->plan->revision !== $expectedRevision) {
            throw ValidationException::withMessages(['revision' => 'This plan changed. Review the current revision before retrying.']);
        }

        return $context;
    }
}
