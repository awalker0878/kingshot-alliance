<?php

declare(strict_types=1);

namespace App\Domain\Events\Services;

use App\Domain\Alliances\Enums\AllianceStatus;
use App\Domain\Alliances\Models\Alliance;
use App\Domain\Authorization\Enums\PermissionKey;
use App\Domain\Events\Enums\EventScope;
use App\Domain\Events\Models\Event;
use App\Domain\Kingdoms\Enums\RosterState;
use App\Domain\Kingdoms\Models\AllianceRosterEntry;
use App\Domain\Kingdoms\Models\Player;
use Illuminate\Auth\Access\AuthorizationException;

final readonly class EventParticipantAuthorization
{
    public function __construct(
        private EventAuthorization $eventAuthorization,
        private EventTargetResolver $targets,
    ) {}

    public function eligible(Event $event, Player $player): bool
    {
        return match ($event->scope) {
            EventScope::Player => (string) $event->player_id === (string) $player->id,
            EventScope::Alliance => $this->eligibleForAlliance($event, $player),
            EventScope::Kingdom => (string) $player->current_kingdom_id === (string) $event->kingdom_id,
        };
    }

    public function authorizeSelf(Player $actor, Event $event, Player $player): void
    {
        if ((string) $actor->id !== (string) $player->id || ! $this->eligible($event, $player)) {
            throw new AuthorizationException;
        }

        $event->loadMissing('typeScope');
        $target = $this->targets->forEvent($event);
        $this->eventAuthorization->authorize(
            $actor,
            $event->scope,
            $target,
            PermissionKey::from((string) $event->typeScope->view_permission_key),
        );
    }

    public function authorizeManager(Player $actor, Event $event): void
    {
        $event->loadMissing('typeScope');
        $target = $this->targets->forEvent($event);
        $permission = PermissionKey::from((string) $event->typeScope->manage_permission_key);
        $this->eventAuthorization->authorize($actor, $event->scope, $target, $permission);
    }

    private function eligibleForAlliance(Event $event, Player $player): bool
    {
        $alliance = Alliance::query()->whereKey($event->alliance_id)->first();
        if (! $alliance instanceof Alliance
            || $alliance->status !== AllianceStatus::Active
            || (string) $alliance->kingdom_id !== (string) $player->current_kingdom_id) {
            return false;
        }

        return AllianceRosterEntry::query()
            ->where('alliance_id', $alliance->id)
            ->where('player_id', $player->id)
            ->where('state', RosterState::Active->value)
            ->exists();
    }
}
