<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Evidence\Enums;

enum EvidenceKind: string
{
    case Unknown = 'unknown';
    case BearHuntBattleReport = 'bear_hunt_battle_report';
    case TransferGovernorStatus = 'transfer_governor_status';
    case TransferScorePasses = 'transfer_score_passes';
    case TransferInvitation = 'transfer_invitation';
    case TransferTargetKingdomRules = 'transfer_target_kingdom_rules';
    case TransferOfficialGroup = 'transfer_official_group';
    case GovernorProfile = 'governor_profile';
    case GovernorHeroRoster = 'governor_hero_roster';
    case GovernorHeroDetail = 'governor_hero_detail';
    case GovernorHeroGear = 'governor_hero_gear';
    case GovernorGear = 'governor_gear';
    case GovernorCharms = 'governor_charms';
    case TerritoryMapObservation = 'territory_map_observation';

    public function isTransfer(): bool
    {
        return in_array($this, self::transferCases(), true);
    }

    public function isGovernorProgression(): bool
    {
        return in_array($this, self::governorProgressionCases(), true);
    }

    public function isTerritorySpatial(): bool
    {
        return $this === self::TerritoryMapObservation;
    }

    /** @return list<self> */
    public static function transferCases(): array
    {
        return [
            self::TransferGovernorStatus,
            self::TransferScorePasses,
            self::TransferInvitation,
            self::TransferTargetKingdomRules,
            self::TransferOfficialGroup,
        ];
    }

    /** @return list<self> */
    public static function governorProgressionCases(): array
    {
        return [
            self::GovernorProfile,
            self::GovernorHeroRoster,
            self::GovernorHeroDetail,
            self::GovernorHeroGear,
            self::GovernorGear,
            self::GovernorCharms,
        ];
    }

    /** @return list<self> */
    public static function territorySpatialCases(): array
    {
        return [self::TerritoryMapObservation];
    }
}
