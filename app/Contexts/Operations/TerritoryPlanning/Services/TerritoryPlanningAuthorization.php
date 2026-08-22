<?php

declare(strict_types=1);

namespace App\Contexts\Operations\TerritoryPlanning\Services;

use App\Contexts\Operations\Access\Enums\OperationsPermission;
use App\Contexts\Operations\Access\Services\AllianceOperationsAuthorization;
use App\Contexts\Operations\Access\Services\KingdomOperationsAuthorization;
use App\Contexts\Operations\TerritoryPlanning\Enums\TerritoryPlanScope;
use App\Contexts\Operations\TerritoryPlanning\ValueObjects\TerritoryPlanMutationContext;
use Illuminate\Auth\Access\AuthorizationException;

final readonly class TerritoryPlanningAuthorization
{
    public function __construct(
        private AllianceOperationsAuthorization $alliance,
        private KingdomOperationsAuthorization $kingdom,
    ) {}

    public function authorizeView(TerritoryPlanMutationContext $context): void
    {
        $permission = $context->plan->scope === TerritoryPlanScope::Alliance
            ? OperationsPermission::TerritoryAllianceView
            : OperationsPermission::TerritoryKingdomView;
        $this->authorize($context, $permission);
    }

    public function authorizeManage(TerritoryPlanMutationContext $context): void
    {
        $permission = $context->plan->scope === TerritoryPlanScope::Alliance
            ? OperationsPermission::TerritoryAllianceManage
            : OperationsPermission::TerritoryKingdomManage;
        $this->authorize($context, $permission);
    }

    private function authorize(TerritoryPlanMutationContext $context, OperationsPermission $permission): void
    {
        if ($context->allianceFacts !== null && $this->alliance->allowsFacts($context->allianceFacts, $permission)) {
            return;
        }
        if ($context->kingdomFacts !== null && $this->kingdom->allowsFacts($context->kingdomFacts, $permission)) {
            return;
        }

        throw new AuthorizationException;
    }
}
