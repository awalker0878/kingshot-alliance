<?php

declare(strict_types=1);

namespace App\Contexts\Operations\Events\Services;

use App\Contexts\Alliance\Lifecycle\Queries\AllianceReferenceQuery;
use App\Contexts\Alliance\Lifecycle\ValueObjects\AllianceReference;
use App\Contexts\GameWorld\Kingdoms\Queries\KingdomReferenceQuery;
use App\Contexts\GameWorld\Players\Queries\PlayerReferenceQuery;
use App\Contexts\Operations\Events\Enums\EventScope;
use App\Contexts\Operations\Events\Models\Event;
use App\Contexts\Operations\Events\Models\EventTemplate;
use App\Contexts\Operations\Events\ValueObjects\EventTargetReference;
use LogicException;

final readonly class EventTargetResolver
{
    public function __construct(
        private AllianceReferenceQuery $alliances,
        private KingdomReferenceQuery $kingdoms,
        private PlayerReferenceQuery $players,
    ) {}

    public function resolve(EventScope $scope, string $targetId): EventTargetReference
    {
        return match ($scope) {
            EventScope::Alliance => $this->allianceTarget($this->alliances->require($targetId)),
            EventScope::Kingdom => $this->kingdomTarget($this->kingdoms->require($targetId)),
            EventScope::Player => $this->playerTarget($this->players->require($targetId)),
        };
    }

    public function lockCurrent(EventScope $scope, string $targetId): EventTargetReference
    {
        return match ($scope) {
            EventScope::Alliance => $this->allianceTarget($this->alliances->lockCurrent($targetId)),
            EventScope::Kingdom => $this->kingdomTarget($this->kingdoms->lockCurrent($targetId)),
            EventScope::Player => $this->playerTarget($this->players->lockCurrent($targetId)),
        };
    }

    public function forEvent(Event $event): EventTargetReference
    {
        return $this->resolve($event->scopeEnum(), $this->targetIdForRecord($event, $event->scopeEnum()));
    }

    public function forTemplate(EventTemplate $template): EventTargetReference
    {
        return $this->resolve($template->scopeEnum(), $this->targetIdForRecord($template, $template->scopeEnum()));
    }

    public function label(EventTargetReference $target): string
    {
        return $target->displayName;
    }

    private function allianceTarget(AllianceReference $alliance): EventTargetReference
    {
        $kingdom = $this->kingdoms->require($alliance->kingdomId);

        return new EventTargetReference(
            scope: EventScope::Alliance,
            targetId: $alliance->allianceId,
            allianceId: $alliance->allianceId,
            kingdomId: $alliance->kingdomId,
            playerId: null,
            displayName: $alliance->name,
            secondaryLabel: 'Kingdom #'.$kingdom->number,
            timezone: $alliance->timezone,
        );
    }

    private function kingdomTarget(\App\Contexts\GameWorld\Kingdoms\ValueObjects\KingdomReference $kingdom): EventTargetReference
    {
        return new EventTargetReference(
            scope: EventScope::Kingdom,
            targetId: $kingdom->kingdomId,
            allianceId: null,
            kingdomId: $kingdom->kingdomId,
            playerId: null,
            displayName: 'Kingdom #'.$kingdom->number,
            secondaryLabel: null,
            timezone: 'UTC',
        );
    }

    private function playerTarget(\App\Contexts\GameWorld\Players\ValueObjects\PlayerReference $player): EventTargetReference
    {
        return new EventTargetReference(
            scope: EventScope::Player,
            targetId: $player->playerId,
            allianceId: null,
            kingdomId: $player->kingdomId,
            playerId: $player->playerId,
            displayName: $player->currentName,
            secondaryLabel: $player->kingdomNumber === null ? null : 'Kingdom #'.$player->kingdomNumber,
            timezone: 'UTC',
        );
    }

    private function targetIdForRecord(Event|EventTemplate $record, EventScope $scope): string
    {
        $targetId = match ($scope) {
            EventScope::Alliance => $record->alliance_id,
            EventScope::Kingdom => $record->kingdom_id,
            EventScope::Player => $record->player_id,
        };

        if (! is_string($targetId) || $targetId === '') {
            throw new LogicException('An event record must contain exactly one valid target identity.');
        }

        return $targetId;
    }
}
