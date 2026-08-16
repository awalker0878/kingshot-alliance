<?php

declare(strict_types=1);

namespace App\Contexts\Operations\Access\Services;

use App\Contexts\Alliance\Access\Services\AllianceMutationAuthority;
use App\Contexts\Alliance\Access\ValueObjects\AllianceMutationContext;
use App\Contexts\Alliance\Core\Models\Alliance;
use App\Contexts\GameWorld\Models\Player;
use App\Contexts\Operations\Access\Enums\OperationsPermission;
use Illuminate\Auth\Access\AuthorizationException;

final readonly class AllianceOperationsMutationAuthority
{
    public function __construct(
        private AllianceMutationAuthority $scopeAuthority,
        private AllianceOperationsAuthorization $authorization,
    ) {}

    public function require(
        Player $actor,
        Alliance $alliance,
        OperationsPermission $permission,
    ): AllianceMutationContext {
        $context = $this->scopeAuthority->acquireActiveScope($actor, $alliance);

        if (! $this->authorization->allowsMembership($context->membership, $context->alliance, $permission)) {
            throw new AuthorizationException;
        }

        return $context;
    }
}
