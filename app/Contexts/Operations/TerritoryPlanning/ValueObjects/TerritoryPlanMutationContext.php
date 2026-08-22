<?php

declare(strict_types=1);

namespace App\Contexts\Operations\TerritoryPlanning\ValueObjects;

use App\Contexts\Alliance\Access\ValueObjects\AllianceAuthorityFacts;
use App\Contexts\GameWorld\Governance\ValueObjects\KingdomAuthorityFacts;
use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;
use App\Contexts\Operations\TerritoryPlanning\Models\TerritoryPlan;

final readonly class TerritoryPlanMutationContext
{
    public function __construct(
        public TerritoryPlan $plan,
        public PlayerReference $actor,
        public ?AllianceAuthorityFacts $allianceFacts,
        public ?KingdomAuthorityFacts $kingdomFacts,
    ) {}
}
