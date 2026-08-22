<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Evidence\Enums;

enum EvidenceKind: string
{
    case Unknown = 'unknown';
    case BearHuntBattleReport = 'bear_hunt_battle_report';
}
