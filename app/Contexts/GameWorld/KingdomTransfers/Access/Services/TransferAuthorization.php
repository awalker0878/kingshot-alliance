<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\KingdomTransfers\Access\Services;

use App\Contexts\Alliance\Access\Queries\AllianceAuthorityFactsQuery;
use App\Contexts\Alliance\Access\ValueObjects\AllianceAuthorityFacts;
use App\Contexts\Alliance\Membership\Enums\AllianceRank;
use App\Contexts\GameWorld\KingdomTransfers\Access\Enums\TransferPermission;
use App\Contexts\GameWorld\KingdomTransfers\ValueObjects\TransferMutationContext;
use Illuminate\Auth\Access\AuthorizationException;

final readonly class TransferAuthorization
{
    public function __construct(private AllianceAuthorityFactsQuery $authorityFacts) {}

    /** Read-time authorization only. */
    public function allows(string $actorPlayerId, string $allianceId, TransferPermission $permission): bool
    {
        $facts = $this->authorityFacts->findCurrent($actorPlayerId, $allianceId);

        return $facts instanceof AllianceAuthorityFacts && $this->allowsFacts($facts, $permission);
    }

    public function authorizeContext(TransferMutationContext $context, TransferPermission $permission): void
    {
        if (! $this->allowsFacts($context->allianceAuthority, $permission)) {
            throw new AuthorizationException;
        }
    }

    private function allowsFacts(AllianceAuthorityFacts $facts, TransferPermission $permission): bool
    {
        return match ($permission) {
            TransferPermission::View => true,
            TransferPermission::Manage => in_array(
                $facts->rankObservedAtRead,
                [AllianceRank::R4, AllianceRank::R5],
                true,
            ),
        };
    }
}
