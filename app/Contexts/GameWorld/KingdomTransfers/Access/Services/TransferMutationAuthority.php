<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\KingdomTransfers\Access\Services;

use App\Contexts\Alliance\Access\Services\AllianceMutationAuthority;
use App\Contexts\Alliance\Access\ValueObjects\AllianceMutationContext;
use App\Contexts\Alliance\Core\Models\Alliance;
use App\Contexts\GameWorld\Models\Player;
use App\Contexts\GameWorld\KingdomTransfers\Access\Enums\TransferPermission;
use Illuminate\Auth\Access\AuthorizationException;

final readonly class TransferMutationAuthority
{
    public function __construct(
        private AllianceMutationAuthority $scopeAuthority,
        private TransferAuthorization $authorization,
    ) {}

    public function require(
        Player $actor,
        Alliance $alliance,
        TransferPermission $permission,
    ): AllianceMutationContext {
        $context = $this->scopeAuthority->acquireActiveScope($actor, $alliance);

        if (! $this->authorization->allowsMembership($context->membership, $context->alliance, $permission)) {
            throw new AuthorizationException;
        }

        return $context;
    }

    public function requireExclusive(
        Player $actor,
        Alliance $alliance,
        TransferPermission $permission,
    ): AllianceMutationContext {
        $context = $this->scopeAuthority->acquireExclusiveScope($actor, $alliance);

        if (! $this->authorization->allowsMembership($context->membership, $context->alliance, $permission)) {
            throw new AuthorizationException;
        }

        return $context;
    }
}
