<?php

declare(strict_types=1);

namespace App\Contexts\Operations\Access\Enums;

use App\Shared\Access\Contracts\Permission;

enum OperationsPermission: string implements Permission
{
    case EventPlayerView = 'events.player.view';
    case EventPlayerCreate = 'events.player.create';
    case EventPlayerManage = 'events.player.manage';
    case EventAllianceView = 'events.alliance.view';
    case EventAllianceCreate = 'events.alliance.create';
    case EventAllianceManage = 'events.alliance.manage';
    case EventKingdomView = 'events.kingdom.view';
    case EventKingdomCreate = 'events.kingdom.create';
    case EventKingdomManage = 'events.kingdom.manage';
    case EventTypeManage = 'events.types.manage';

    public function key(): string
    {
        return $this->value;
    }

    public function description(): string
    {
        return match ($this) {
            self::EventPlayerView => 'View permitted player-scoped events.',
            self::EventPlayerCreate => 'Create permitted player-scoped events.',
            self::EventPlayerManage => 'Manage permitted player-scoped events.',
            self::EventAllianceView => 'View alliance-scoped events.',
            self::EventAllianceCreate => 'Create alliance-scoped events.',
            self::EventAllianceManage => 'Manage alliance-scoped events and event operations.',
            self::EventKingdomView => 'View permitted kingdom-scoped events.',
            self::EventKingdomCreate => 'Create permitted kingdom-scoped events.',
            self::EventKingdomManage => 'Manage permitted kingdom-scoped events.',
            self::EventTypeManage => 'Manage the event type catalogue and capability configuration.',
        };
    }
}
