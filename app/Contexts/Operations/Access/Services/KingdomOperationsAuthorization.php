<?php

declare(strict_types=1);

namespace App\Contexts\Operations\Access\Services;

use App\Contexts\GameWorld\Governance\Queries\KingdomAuthorityFactsQuery;
use App\Contexts\GameWorld\Governance\ValueObjects\KingdomAuthorityFacts;
use App\Contexts\Operations\Access\Enums\OperationsPermission;

final readonly class KingdomOperationsAuthorization
{
    public function __construct(private KingdomAuthorityFactsQuery $authorityFacts) {}

    public function allows(string $actorPlayerId, string $kingdomId, OperationsPermission $permission): bool
    {
        $facts = $this->authorityFacts->findCurrent($actorPlayerId, $kingdomId);

        return $facts instanceof KingdomAuthorityFacts && $this->allowsFacts($facts, $permission);
    }

    public function allowsFacts(KingdomAuthorityFacts $facts, OperationsPermission $permission): bool
    {
        return $this->supports($permission)
            && $facts->hasPermissionObservedAtRead($permission->key());
    }

    private function supports(OperationsPermission $permission): bool
    {
        return in_array($permission, [
            OperationsPermission::EventKingdomView,
            OperationsPermission::EventKingdomCreate,
            OperationsPermission::EventKingdomManage,
        ], true);
    }
}
