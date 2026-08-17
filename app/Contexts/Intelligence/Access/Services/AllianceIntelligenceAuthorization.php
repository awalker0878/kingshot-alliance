<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Access\Services;

use App\Contexts\Alliance\Access\Queries\AllianceAuthorityFactsQuery;
use App\Contexts\Alliance\Access\ValueObjects\AllianceAuthorityFacts;
use App\Contexts\Alliance\Membership\Enums\AllianceRank;
use App\Contexts\Intelligence\Access\Enums\IntelligencePermission;
use Illuminate\Auth\Access\AuthorizationException;

final readonly class AllianceIntelligenceAuthorization
{
    public function __construct(private AllianceAuthorityFactsQuery $authorityFacts) {}

    /** Read-time authorization only. Protected writes must pass locked facts to authorizeFacts(). */
    public function allows(string $actorPlayerId, string $allianceId, IntelligencePermission $permission): bool
    {
        $facts = $this->authorityFacts->findCurrent($actorPlayerId, $allianceId);

        return $facts instanceof AllianceAuthorityFacts && $this->allowsFacts($facts, $permission);
    }

    public function authorizeFacts(AllianceAuthorityFacts $facts, IntelligencePermission $permission): void
    {
        if (! $this->allowsFacts($facts, $permission)) {
            throw new AuthorizationException;
        }
    }

    public function allowsFacts(AllianceAuthorityFacts $facts, IntelligencePermission $permission): bool
    {
        return match ($permission) {
            IntelligencePermission::View => true,
            IntelligencePermission::KingdomManage => in_array(
                $facts->rankObservedAtRead,
                [AllianceRank::R4, AllianceRank::R5],
                true,
            ),
            IntelligencePermission::ContributionManage => $facts->rankObservedAtRead === AllianceRank::R5,
        };
    }
}
