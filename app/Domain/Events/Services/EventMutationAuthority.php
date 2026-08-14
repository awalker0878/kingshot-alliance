<?php

declare(strict_types=1);

namespace App\Domain\Events\Services;

use App\Domain\Alliances\Models\Alliance;
use App\Domain\Authorization\Enums\PermissionKey;
use App\Domain\Authorization\Services\AllianceMutationAuthority;
use App\Domain\Authorization\Services\KingdomMutationAuthority;
use App\Domain\Authorization\Services\PlayerMutationAuthority;
use App\Domain\Events\Enums\EventScope;
use App\Domain\Events\Models\Event;
use App\Domain\Events\Models\EventTypeScope;
use App\Domain\Events\ValueObjects\EventMutationContext;
use App\Domain\Kingdoms\Enums\RosterState;
use App\Domain\Kingdoms\Models\AllianceRosterEntry;
use App\Domain\Kingdoms\Models\Kingdom;
use App\Domain\Kingdoms\Models\Player;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Resolves current Event scope authority for state-changing Event/Rally workflows.
 * Business aggregates remain owned and locked by the calling domain.
 */
final readonly class EventMutationAuthority
{
    public function __construct(
        private EventAuthorization $authorization,
        private AllianceMutationAuthority $allianceAuthority,
        private KingdomMutationAuthority $kingdomAuthority,
        private PlayerMutationAuthority $playerAuthority,
    ) {}

    public function requireManager(Player $actor, Event $event): EventMutationContext
    {
        [$currentEvent, $typeScope] = $this->lockEventContract($event);
        $permission = PermissionKey::from((string) $typeScope->manage_permission_key);

        return $this->require($actor, $currentEvent, $typeScope, $permission);
    }

    public function requireSelf(Player $actor, Event $event, Player $participant): EventMutationContext
    {
        [$currentEvent, $typeScope] = $this->lockEventContract($event);

        if ((string) $actor->id !== (string) $participant->id
            || $currentEvent->scope === EventScope::Player
                && (string) $currentEvent->player_id !== (string) $participant->id) {
            throw new AuthorizationException;
        }

        $permission = PermissionKey::from((string) $typeScope->view_permission_key);
        $context = $this->require($actor, $currentEvent, $typeScope, $permission);

        // Alliance/Kingdom Events allow an eligible Player to act for itself even
        // though the Event target is the wider scope. Revalidate exact eligibility
        // using locked/current Player and roster state before returning.
        if ($currentEvent->scope === EventScope::Alliance) {
            $alliance = $context->target;
            if (! $alliance instanceof Alliance) {
                throw new LogicException('Alliance Event mutation context must resolve an Alliance target.');
            }

            $currentParticipant = Player::query()
                ->whereKey($participant->id)
                ->sharedLock()
                ->firstOrFail();

            if ((string) $currentParticipant->current_kingdom_id !== (string) $alliance->kingdom_id
                || ! AllianceRosterEntry::query()
                    ->where('alliance_id', $alliance->id)
                    ->where('player_id', $currentParticipant->id)
                    ->where('state', RosterState::Active->value)
                    ->sharedLock()
                    ->exists()) {
                throw new AuthorizationException;
            }
        } elseif ($currentEvent->scope === EventScope::Kingdom) {
            $kingdom = $context->target;
            if (! $kingdom instanceof Kingdom) {
                throw new LogicException('Kingdom Event mutation context must resolve a Kingdom target.');
            }

            $currentParticipant = Player::query()
                ->whereKey($participant->id)
                ->sharedLock()
                ->firstOrFail();

            if ((string) $currentParticipant->current_kingdom_id !== (string) $kingdom->id) {
                throw new AuthorizationException;
            }
        }

        return $context;
    }

    /** @return array{Event,EventTypeScope} */
    private function lockEventContract(Event $event): array
    {
        if (DB::transactionLevel() < 1) {
            throw new LogicException('Event mutation authority must be acquired inside a database transaction.');
        }

        // Event scope/target/type-scope are the immutable routing contract for the
        // mutation. Share-lock them so platform scope configuration or Event edits
        // cannot change the permission vocabulary mid-write.
        $currentEvent = Event::query()
            ->whereKey($event->id)
            ->sharedLock()
            ->firstOrFail();

        $typeScope = EventTypeScope::query()
            ->whereKey($currentEvent->event_type_scope_id)
            ->where('scope', $currentEvent->scope->value)
            ->sharedLock()
            ->firstOrFail();

        return [$currentEvent, $typeScope];
    }

    private function require(
        Player $actor,
        Event $event,
        EventTypeScope $typeScope,
        PermissionKey $permission,
    ): EventMutationContext {
        if (! $this->authorization->supports($event->scope, $permission)) {
            throw new AuthorizationException;
        }

        return match ($event->scope) {
            EventScope::Alliance => $this->requireAllianceScope($actor, $event, $typeScope, $permission),
            EventScope::Kingdom => $this->requireKingdomScope($actor, $event, $typeScope, $permission),
            EventScope::Player => $this->requirePlayerScope($actor, $event, $typeScope, $permission),
        };
    }

    private function requireAllianceScope(
        Player $actor,
        Event $event,
        EventTypeScope $typeScope,
        PermissionKey $permission,
    ): EventMutationContext {
        $alliance = Alliance::query()->whereKey($event->alliance_id)->firstOrFail();
        $context = $this->allianceAuthority->require($actor, $alliance, $permission);

        return new EventMutationContext($event, $typeScope, $context->actor, $context->alliance);
    }

    private function requireKingdomScope(
        Player $actor,
        Event $event,
        EventTypeScope $typeScope,
        PermissionKey $permission,
    ): EventMutationContext {
        $kingdom = Kingdom::query()->whereKey($event->kingdom_id)->firstOrFail();
        $context = $this->kingdomAuthority->require($actor, $kingdom, $permission);

        return new EventMutationContext($event, $typeScope, $context->actor, $context->kingdom);
    }

    private function requirePlayerScope(
        Player $actor,
        Event $event,
        EventTypeScope $typeScope,
        PermissionKey $permission,
    ): EventMutationContext {
        $target = Player::query()->whereKey($event->player_id)->firstOrFail();

        if ((string) $actor->id === (string) $target->id) {
            $context = $this->playerAuthority->require($actor);

            return new EventMutationContext($event, $typeScope, $context->actor, $context->actor);
        }

        if ($permission === PermissionKey::EventPlayerCreate) {
            throw new AuthorizationException;
        }

        // Route through active roster Alliances deterministically. The selected
        // Alliance mutation authority serializes the manager's rank/role state;
        // target Player + roster are then shared-locked to stabilize eligibility.
        $candidateAllianceIds = AllianceRosterEntry::query()
            ->where('player_id', $target->id)
            ->where('state', RosterState::Active->value)
            ->orderBy('alliance_id')
            ->pluck('alliance_id')
            ->unique()
            ->values();

        foreach ($candidateAllianceIds as $allianceId) {
            $alliance = Alliance::query()->whereKey($allianceId)->first();
            if (! $alliance instanceof Alliance) {
                continue;
            }

            try {
                $managerContext = $this->allianceAuthority->require($actor, $alliance, $permission);
            } catch (AuthorizationException) {
                continue;
            }

            $currentTarget = Player::query()
                ->whereKey($target->id)
                ->sharedLock()
                ->firstOrFail();

            $eligible = (string) $currentTarget->current_kingdom_id === (string) $managerContext->alliance->kingdom_id
                && AllianceRosterEntry::query()
                    ->where('alliance_id', $managerContext->alliance->id)
                    ->where('player_id', $currentTarget->id)
                    ->where('state', RosterState::Active->value)
                    ->sharedLock()
                    ->exists();

            if ($eligible) {
                return new EventMutationContext(
                    $event,
                    $typeScope,
                    $managerContext->actor,
                    $currentTarget,
                );
            }
        }

        throw new AuthorizationException;
    }
}
