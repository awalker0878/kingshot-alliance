<?php

declare(strict_types=1);

namespace Tests\Support\V2;

final class ArchitectureCatalogue
{
    /** @return array<string, list<string>> */
    public static function contextCapabilities(): array
    {
        return [
            'Accounts' => ['Core'],
            'GameWorld' => ['Core', 'Governance'],
            'Alliance' => ['Access', 'Content', 'Core', 'Membership', 'Policies', 'Recruitment'],
            'Operations' => [
                'Access',
                'BattlePlans',
                'EventCore',
                'KingPerks',
                'Participation',
                'Polls',
                'Rallies',
                'Reminders',
                'Results',
                'Rosters',
            ],
            'Intelligence' => [
                'Access',
                'Contributions',
                'Diplomacy',
                'EventAnalysis',
                'Ingestion',
                'Observations',
                'Roster',
                'Sharing',
            ],
            'Communications' => ['Reminders'],
            'Platform' => ['Core', 'Access', 'EventAdministration', 'Integrations'],
        ];
    }

    /** @return list<string> */
    public static function workflows(): array
    {
        return ['KingdomGovernance', 'KingdomTransfer', 'PlayerContext', 'Registration'];
    }

    /** @return list<string> */
    public static function readModels(): array
    {
        return [
            'AllianceDashboard',
            'EventCalendar',
            'EventHistory',
            'EventManagement',
            'KingdomIntelligence',
            'KingdomSettings',
            'SharedKingdomIntelligence',
        ];
    }

    /** @return list<string> */
    public static function sharedCapabilities(): array
    {
        return ['Access', 'Http', 'Infrastructure', 'Providers'];
    }

    /** @return array<string, list<string>> */
    public static function structuralChildren(): array
    {
        return [
            'Accounts' => ['Actions', 'Http', 'Models', 'Services'],
            'GameWorld' => ['Actions', 'Enums', 'Http', 'Models', 'Queries', 'Services'],
            'Alliance' => ['Core'],
            'Operations' => [],
            'Intelligence' => ['Http'],
            'Communications' => [],
            'Platform' => ['Actions', 'Core', 'Http', 'Models', 'Providers', 'Queries', 'Services'],
        ];
    }

    /** @return array<string, list<string>> */
    public static function forbiddenContextDependencies(): array
    {
        return [
            'Accounts' => ['GameWorld', 'Alliance', 'Operations', 'Intelligence', 'Communications', 'Platform'],
            'GameWorld' => ['Alliance', 'Operations', 'Intelligence', 'Communications', 'Platform'],
            'Alliance' => ['Operations', 'Intelligence', 'Communications', 'Platform'],
            'Operations' => ['Intelligence', 'Communications', 'Platform'],
            'Intelligence' => ['Communications', 'Platform'],
            'Communications' => ['Platform'],
            'Platform' => [],
        ];
    }

    public static function productionPath(string $context, string $capability): string
    {
        if ($capability === 'Core') {
            return 'app/Contexts/'.$context;
        }

        return 'app/Contexts/'.$context.'/'.$capability;
    }

    public static function featureTestPath(string $context, string $capability): string
    {
        return 'tests/Feature/'.$context.'/'.$capability.'/V2';
    }
}
