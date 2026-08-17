<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Access\Services;

use App\Contexts\Alliance\Access\Queries\AllianceAuthorityFactsQuery;
use App\Contexts\Alliance\Access\ValueObjects\AllianceAuthorityFacts;
use App\Contexts\GameWorld\Players\Queries\PlayerReferenceQuery;
use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;
use App\Contexts\Intelligence\Access\Enums\IntelligencePermission;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Transaction-time acquisition of current Alliance authority for Intelligence writes.
 *
 * This service deliberately returns immutable current facts/references only. Intelligence
 * write actions remain responsible for locking their own aggregate rows after authority
 * has been reacquired from the owning contexts.
 */
final readonly class AllianceIntelligenceWriteState
{
    public function __construct(
        private AllianceAuthorityFactsQuery $authorityFacts,
        private PlayerReferenceQuery $players,
        private AllianceIntelligenceAuthorization $authorization,
    ) {}

    /** @return array{0: AllianceAuthorityFacts, 1: PlayerReference} */
    public function authorize(
        string $actorPlayerId,
        string $allianceId,
        IntelligencePermission $permission,
    ): array {
        if (DB::transactionLevel() < 1) {
            throw new LogicException('Intelligence write authority must be acquired inside a database transaction.');
        }

        $facts = $this->authorityFacts->lockCurrent($actorPlayerId, $allianceId);
        if (! $facts instanceof AllianceAuthorityFacts) {
            throw new AuthorizationException;
        }

        $actor = $this->players->lockCurrent($actorPlayerId);
        if ($actor->kingdomId !== $facts->kingdomId) {
            throw new AuthorizationException;
        }

        $this->authorization->authorizeFacts($facts, $permission);

        return [$facts, $actor];
    }
}
