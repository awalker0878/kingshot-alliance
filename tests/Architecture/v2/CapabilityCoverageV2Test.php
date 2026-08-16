<?php

declare(strict_types=1);

namespace Tests\Architecture\v2;

use PHPUnit\Framework\TestCase;

final class CapabilityCoverageV2Test extends TestCase
{
    public function test_every_final_v2_capability_is_exercised_by_v2_tests(): void
    {
        $source = '';
        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator(
                dirname(__DIR__, 2),
                \FilesystemIterator::SKIP_DOTS,
            ),
        );

        foreach ($iterator as $file) {
            if (! $file instanceof \SplFileInfo || ! $file->isFile()) {
                continue;
            }

            $path = $file->getPathname();

            if (
                ! str_ends_with($path, 'V2Test.php')
                || str_ends_with($path, 'CapabilityCoverageV2Test.php')
            ) {
                continue;
            }

            $contents = file_get_contents($path);

            if (is_string($contents)) {
                $source .= "\n".$contents;
            }
        }

        $capabilities = [
            'Accounts' => 'App\\Contexts\\Accounts\\',
            'GameWorld' => 'App\\Contexts\\GameWorld\\',
            'GameWorld Governance' => 'App\\Contexts\\GameWorld\\Governance\\',
            'Alliance Access' => 'App\\Contexts\\Alliance\\Access\\',
            'Alliance Core' => 'App\\Contexts\\Alliance\\Core\\',
            'Alliance Membership' => 'App\\Contexts\\Alliance\\Membership\\',
            'Alliance Content' => 'App\\Contexts\\Alliance\\Content\\',
            'Alliance Policies' => 'App\\Contexts\\Alliance\\Policies\\',
            'Alliance Recruitment' => 'App\\Contexts\\Alliance\\Recruitment\\',
            'Operations Access' => 'App\\Contexts\\Operations\\Access\\',
            'Operations BattlePlans' => 'App\\Contexts\\Operations\\BattlePlans\\',
            'Operations EventCore' => 'App\\Contexts\\Operations\\EventCore\\',
            'Operations KingPerks' => 'App\\Contexts\\Operations\\KingPerks\\',
            'Operations Participation' => 'App\\Contexts\\Operations\\Participation\\',
            'Operations Polls' => 'App\\Contexts\\Operations\\Polls\\',
            'Operations Rallies' => 'App\\Contexts\\Operations\\Rallies\\',
            'Operations Reminders' => 'App\\Contexts\\Operations\\Reminders\\',
            'Operations Results' => 'App\\Contexts\\Operations\\Results\\',
            'Operations Rosters' => 'App\\Contexts\\Operations\\Rosters\\',
            'Intelligence Access' => 'App\\Contexts\\Intelligence\\Access\\',
            'Intelligence Contributions' => 'App\\Contexts\\Intelligence\\Contributions\\',
            'Intelligence Diplomacy' => 'App\\Contexts\\Intelligence\\Diplomacy\\',
            'Intelligence EventAnalysis' => 'App\\Contexts\\Intelligence\\EventAnalysis\\',
            'Intelligence Ingestion' => 'App\\Contexts\\Intelligence\\Ingestion\\',
            'Intelligence Observations' => 'App\\Contexts\\Intelligence\\Observations\\',
            'Intelligence Roster' => 'App\\Contexts\\Intelligence\\Roster\\',
            'Intelligence Sharing' => 'App\\Contexts\\Intelligence\\Sharing\\',
            'Communications Reminders' => 'App\\Contexts\\Communications\\Reminders\\',
            'Platform' => 'App\\Contexts\\Platform\\',
            'Platform EventAdministration' => 'App\\Contexts\\Platform\\EventAdministration\\',
            'Platform Integrations' => 'App\\Contexts\\Platform\\Integrations\\',
            'Workflow KingdomGovernance' => 'App\\Workflows\\KingdomGovernance\\',
            'Workflow KingdomTransfer' => 'App\\Workflows\\KingdomTransfer\\',
            'Workflow PlayerContext' => 'App\\Workflows\\PlayerContext\\',
            'Workflow Registration' => 'App\\Workflows\\Registration\\',
            'ReadModel AllianceDashboard' => 'App\\ReadModels\\AllianceDashboard\\',
            'ReadModel EventCalendar' => 'App\\ReadModels\\EventCalendar\\',
            'ReadModel EventHistory' => 'App\\ReadModels\\EventHistory\\',
            'ReadModel EventManagement' => 'App\\ReadModels\\EventManagement\\',
            'ReadModel KingdomIntelligence' => 'App\\ReadModels\\KingdomIntelligence\\',
            'ReadModel KingdomSettings' => 'App\\ReadModels\\KingdomSettings\\',
            'ReadModel SharedKingdomIntelligence' => 'App\\ReadModels\\SharedKingdomIntelligence\\',
            'Shared AuditTrail' => 'App\\Shared\\Infrastructure\\AuditTrail\\',
            'Shared Outbox' => 'App\\Shared\\Infrastructure\\Messaging\\Outbox\\',
        ];

        foreach ($capabilities as $capability => $namespace) {
            self::assertStringContainsString(
                $namespace,
                $source,
                "No V2 test references final capability {$capability} ({$namespace}).",
            );
        }
    }
}
