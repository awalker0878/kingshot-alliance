<?php

declare(strict_types=1);

namespace App\Contexts\Operations\Access\Services;

use App\Contexts\GameWorld\Governance\Services\KingdomMutationAuthority;
use App\Contexts\GameWorld\Governance\ValueObjects\KingdomMutationContext;
use App\Contexts\GameWorld\Models\Kingdom;
use App\Contexts\GameWorld\Models\Player;
use App\Contexts\Operations\Access\Enums\OperationsPermission;
use Illuminate\Auth\Access\AuthorizationException;

final readonly class KingdomOperationsMutationAuthority
{
    public function __construct(
        private KingdomMutationAuthority $scopeAuthority,
        private KingdomOperationsAuthorization $authorization,
    ) {}

    public function require(
        Player $actor,
        Kingdom $kingdom,
        OperationsPermission $permission,
    ): KingdomMutationContext {
        $context = $this->scopeAuthority->acquireActiveScope($actor, $kingdom);

        if (! $this->authorization->allows($context->actor, $context->kingdom, $permission)) {
            throw new AuthorizationException;
        }

        return $context;
    }
}
