<?php

declare(strict_types=1);

namespace App\Contexts\Operations\Access\Enums;

enum OperationsPermission: string
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
    case EventTypesManage = 'events.types.manage';
    case TerritoryAllianceView = 'territory.alliance.view';
    case TerritoryAllianceManage = 'territory.alliance.manage';
    case TerritoryKingdomView = 'territory.kingdom.view';
    case TerritoryKingdomManage = 'territory.kingdom.manage';

    public function key(): string
    {
        return $this->value;
    }

    public function description(): string
    {
        return match ($this) {
            self::TerritoryAllianceView => 'View Alliance territory and hive plans.',
            self::TerritoryAllianceManage => 'Manage Alliance territory and hive plans.',
            self::TerritoryKingdomView => 'View Kingdom multi-Alliance territory plans.',
            self::TerritoryKingdomManage => 'Manage Kingdom multi-Alliance territory plans.',
            default => ucfirst(str_replace(['.', '_'], ' ', $this->value)).'.',
        };
    }
}
