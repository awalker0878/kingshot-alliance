<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Access\Services;

use App\Contexts\Alliance\Access\Enums\AlliancePermission;
use App\Contexts\Alliance\Access\Services\AllianceMutationAuthority;
use App\Contexts\Alliance\Access\ValueObjects\AllianceMutationContext;
use App\Contexts\Alliance\Core\Models\Alliance;
use App\Contexts\GameWorld\Models\Player;
use App\Contexts\Intelligence\Access\Enums\IntelligencePermission;
use Illuminate\Auth\Access\AuthorizationException;

final readonly class AllianceIntelligenceMutationAuthority
{
    public function __construct(
        private AllianceMutationAuthority $scopeAuthority,
        private AllianceIntelligenceAuthorization $authorization,
    ) {}

    public function require(
        Player $actor,
        Alliance $alliance,
        IntelligencePermission|AlliancePermission $permission,
    ): AllianceMutationContext {
        $context = $this->scopeAuthority->acquireActiveScope($actor, $alliance);

        if ($permission instanceof AlliancePermission) {
            if ($permission !== AlliancePermission::View) {
                throw new AuthorizationException;
            }

            return $context;
        }

        if (! $this->authorization->allowsMembership($context->membership, $context->alliance, $permission)) {
            throw new AuthorizationException;
        }

        return $context;
    }
}
