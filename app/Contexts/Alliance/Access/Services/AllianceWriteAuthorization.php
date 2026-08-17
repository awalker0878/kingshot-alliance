<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Access\Services;

use App\Contexts\Alliance\Access\Enums\AlliancePermission;
use App\Contexts\Alliance\Lifecycle\ValueObjects\AllianceReference;
use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;

/**
 * Context-owned write authority API for callers outside Alliance.
 *
 * Eloquent mutation state remains private to Alliance. Callers receive only
 * immutable current references acquired and authorized inside their transaction.
 */
final readonly class AllianceWriteAuthorization
{
    public function __construct(
        private AllianceWriteState $state,
        private AllianceAuthorization $authorization,
    ) {}

    /** @return array{0: AllianceReference, 1: PlayerReference} */
    public function authorizeManagerActive(string $actorPlayerId, string $allianceId): array
    {
        return $this->authorize($actorPlayerId, $allianceId, AlliancePermission::Manage, false);
    }

    /** @return array{0: AllianceReference, 1: PlayerReference} */
    public function authorizeManagerExclusive(string $actorPlayerId, string $allianceId): array
    {
        return $this->authorize($actorPlayerId, $allianceId, AlliancePermission::Manage, true);
    }

    /** @return array{0: AllianceReference, 1: PlayerReference} */
    public function authorizeActive(string $actorPlayerId, string $allianceId, AlliancePermission $permission): array
    {
        return $this->authorize($actorPlayerId, $allianceId, $permission, false);
    }

    /** @return array{0: AllianceReference, 1: PlayerReference} */
    public function authorizeExclusive(string $actorPlayerId, string $allianceId, AlliancePermission $permission): array
    {
        return $this->authorize($actorPlayerId, $allianceId, $permission, true);
    }

    /** @return array{0: AllianceReference, 1: PlayerReference} */
    private function authorize(string $actorPlayerId, string $allianceId, AlliancePermission $permission, bool $exclusive): array
    {
        $context = $exclusive
            ? $this->state->lockExclusiveScope($actorPlayerId, $allianceId)
            : $this->state->lockActiveScope($actorPlayerId, $allianceId);
        $this->authorization->authorizeContext($context, $permission);

        return [
            new AllianceReference(
                allianceId: (string) $context->alliance->id,
                name: (string) $context->alliance->name,
                slug: (string) $context->alliance->slug,
                kingdomId: (string) $context->alliance->kingdom_id,
                language: (string) $context->alliance->language,
                timezone: (string) $context->alliance->timezone,
                status: $context->alliance->status->value,
            ),
            $context->actor,
        ];
    }
}
