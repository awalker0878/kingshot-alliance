<?php

declare(strict_types=1);

namespace Tests\v2\Architecture;

use Tests\v2\TestCase;

final class CapabilityCoverageV2Test extends TestCase
{
    public function test_every_documented_v2_capability_has_a_dedicated_new_contract(): void
    {
        $capabilities = [
            ['capability' => 'Contexts/Accounts/AccountSecurity', 'sources' => ['app/Contexts/Accounts'], 'documentation' => 'docs/architecture/contexts/accounts/account-security.md', 'test' => 'tests/v2/Contexts/Accounts/AccountSecurity/CapabilitySurfaceV2Test.php'],
            ['capability' => 'Contexts/GameWorld/Identity', 'sources' => ['app/Contexts/GameWorld/Actions', 'app/Contexts/GameWorld/Enums', 'app/Contexts/GameWorld/Models', 'app/Contexts/GameWorld/Services'], 'documentation' => 'docs/architecture/contexts/game-world/player-context.md', 'test' => 'tests/v2/Contexts/GameWorld/Identity/CapabilitySurfaceV2Test.php'],
            ['capability' => 'Contexts/GameWorld/Governance', 'sources' => ['app/Contexts/GameWorld/Governance'], 'documentation' => 'docs/architecture/contexts/game-world/kingdom-governance.md', 'test' => 'tests/v2/Contexts/GameWorld/Governance/CapabilitySurfaceV2Test.php'],
            ['capability' => 'Contexts/Alliance/Core', 'sources' => ['app/Contexts/Alliance/Core'], 'documentation' => 'docs/architecture/contexts/alliance/lifecycle-and-settings.md', 'test' => 'tests/v2/Contexts/Alliance/Core/CapabilitySurfaceV2Test.php'],
            ['capability' => 'Contexts/Alliance/Membership', 'sources' => ['app/Contexts/Alliance/Membership'], 'documentation' => 'docs/architecture/contexts/alliance/membership-and-authority.md', 'test' => 'tests/v2/Contexts/Alliance/Membership/CapabilitySurfaceV2Test.php'],
            ['capability' => 'Contexts/Alliance/Access', 'sources' => ['app/Contexts/Alliance/Access'], 'documentation' => 'docs/architecture/contexts/alliance/membership-and-authority.md', 'test' => 'tests/v2/Contexts/Alliance/Access/CapabilitySurfaceV2Test.php'],
            ['capability' => 'Contexts/Alliance/Recruitment', 'sources' => ['app/Contexts/Alliance/Recruitment'], 'documentation' => 'docs/architecture/contexts/alliance/recruitment.md', 'test' => 'tests/v2/Contexts/Alliance/Recruitment/CapabilitySurfaceV2Test.php'],
            ['capability' => 'Contexts/Alliance/Content', 'sources' => ['app/Contexts/Alliance/Content'], 'documentation' => 'docs/architecture/contexts/alliance/content.md', 'test' => 'tests/v2/Contexts/Alliance/Content/CapabilitySurfaceV2Test.php'],
            ['capability' => 'Contexts/Operations/EventCore', 'sources' => ['app/Contexts/Operations/EventCore'], 'documentation' => 'docs/architecture/contexts/operations/event-core.md', 'test' => 'tests/v2/Contexts/Operations/EventCore/CapabilitySurfaceV2Test.php'],
            ['capability' => 'Contexts/Operations/Participation', 'sources' => ['app/Contexts/Operations/Participation'], 'documentation' => 'docs/architecture/contexts/operations/participation.md', 'test' => 'tests/v2/Contexts/Operations/Participation/CapabilitySurfaceV2Test.php'],
            ['capability' => 'Contexts/Operations/Polls', 'sources' => ['app/Contexts/Operations/Polls'], 'documentation' => 'docs/architecture/contexts/operations/planning.md', 'test' => 'tests/v2/Contexts/Operations/Polls/CapabilitySurfaceV2Test.php'],
            ['capability' => 'Contexts/Operations/Rosters', 'sources' => ['app/Contexts/Operations/Rosters'], 'documentation' => 'docs/architecture/contexts/operations/planning.md', 'test' => 'tests/v2/Contexts/Operations/Rosters/CapabilitySurfaceV2Test.php'],
            ['capability' => 'Contexts/Operations/BattlePlans', 'sources' => ['app/Contexts/Operations/BattlePlans'], 'documentation' => 'docs/architecture/contexts/operations/planning.md', 'test' => 'tests/v2/Contexts/Operations/BattlePlans/CapabilitySurfaceV2Test.php'],
            ['capability' => 'Contexts/Operations/Results', 'sources' => ['app/Contexts/Operations/Results'], 'documentation' => 'docs/architecture/contexts/operations/results.md', 'test' => 'tests/v2/Contexts/Operations/Results/CapabilitySurfaceV2Test.php'],
            ['capability' => 'Contexts/Operations/Rallies', 'sources' => ['app/Contexts/Operations/Rallies'], 'documentation' => 'docs/architecture/contexts/operations/rallies.md', 'test' => 'tests/v2/Contexts/Operations/Rallies/CapabilitySurfaceV2Test.php'],
            ['capability' => 'Contexts/Operations/KingPerks', 'sources' => ['app/Contexts/Operations/KingPerks'], 'documentation' => 'docs/architecture/contexts/operations/king-perks.md', 'test' => 'tests/v2/Contexts/Operations/KingPerks/CapabilitySurfaceV2Test.php'],
            ['capability' => 'Contexts/Operations/Reminders', 'sources' => ['app/Contexts/Operations/Reminders'], 'documentation' => 'docs/architecture/contexts/operations/reminders.md', 'test' => 'tests/v2/Contexts/Operations/Reminders/CapabilitySurfaceV2Test.php'],
            ['capability' => 'Contexts/Intelligence/Observations', 'sources' => ['app/Contexts/Intelligence/Observations'], 'documentation' => 'docs/architecture/contexts/intelligence/observations-and-ingestion.md', 'test' => 'tests/v2/Contexts/Intelligence/Observations/CapabilitySurfaceV2Test.php'],
            ['capability' => 'Contexts/Intelligence/Ingestion', 'sources' => ['app/Contexts/Intelligence/Ingestion'], 'documentation' => 'docs/architecture/contexts/intelligence/observations-and-ingestion.md', 'test' => 'tests/v2/Contexts/Intelligence/Ingestion/CapabilitySurfaceV2Test.php'],
            ['capability' => 'Contexts/Intelligence/Roster', 'sources' => ['app/Contexts/Intelligence/Roster'], 'documentation' => 'docs/architecture/contexts/intelligence/roster-and-contributions.md', 'test' => 'tests/v2/Contexts/Intelligence/Roster/CapabilitySurfaceV2Test.php'],
            ['capability' => 'Contexts/Intelligence/Contributions', 'sources' => ['app/Contexts/Intelligence/Contributions'], 'documentation' => 'docs/architecture/contexts/intelligence/roster-and-contributions.md', 'test' => 'tests/v2/Contexts/Intelligence/Contributions/CapabilitySurfaceV2Test.php'],
            ['capability' => 'Contexts/Intelligence/EventAnalysis', 'sources' => ['app/Contexts/Intelligence/EventAnalysis'], 'documentation' => 'docs/architecture/contexts/intelligence/event-analysis.md', 'test' => 'tests/v2/Contexts/Intelligence/EventAnalysis/CapabilitySurfaceV2Test.php'],
            ['capability' => 'Contexts/Intelligence/Diplomacy', 'sources' => ['app/Contexts/Intelligence/Diplomacy'], 'documentation' => 'docs/architecture/contexts/intelligence/diplomacy-and-sharing.md', 'test' => 'tests/v2/Contexts/Intelligence/Diplomacy/CapabilitySurfaceV2Test.php'],
            ['capability' => 'Contexts/Intelligence/Sharing', 'sources' => ['app/Contexts/Intelligence/Sharing'], 'documentation' => 'docs/architecture/contexts/intelligence/diplomacy-and-sharing.md', 'test' => 'tests/v2/Contexts/Intelligence/Sharing/CapabilitySurfaceV2Test.php'],
            ['capability' => 'Contexts/Communications/Reminders', 'sources' => ['app/Contexts/Communications/Reminders'], 'documentation' => 'docs/architecture/contexts/communications/reminder-delivery.md', 'test' => 'tests/v2/Contexts/Communications/Reminders/CapabilitySurfaceV2Test.php'],
            ['capability' => 'Contexts/Platform/Access', 'sources' => ['app/Contexts/Platform/Access'], 'documentation' => 'docs/architecture/contexts/platform/administration-and-lifecycle.md', 'test' => 'tests/v2/Contexts/Platform/Access/CapabilitySurfaceV2Test.php'],
            ['capability' => 'Contexts/Platform/EventAdministration', 'sources' => ['app/Contexts/Platform/EventAdministration'], 'documentation' => 'docs/architecture/contexts/platform/event-administration.md', 'test' => 'tests/v2/Contexts/Platform/EventAdministration/CapabilitySurfaceV2Test.php'],
            ['capability' => 'Contexts/Platform/Integrations', 'sources' => ['app/Contexts/Platform/Integrations'], 'documentation' => 'docs/architecture/contexts/platform/integrations.md', 'test' => 'tests/v2/Contexts/Platform/Integrations/CapabilitySurfaceV2Test.php'],
            ['capability' => 'Contexts/Platform/Lifecycle', 'sources' => ['app/Contexts/Platform/Actions', 'app/Contexts/Platform/Services'], 'documentation' => 'docs/architecture/contexts/platform/administration-and-lifecycle.md', 'test' => 'tests/v2/Contexts/Platform/Lifecycle/CapabilitySurfaceV2Test.php'],
            ['capability' => 'Workflows/Registration', 'sources' => ['app/Workflows/Registration'], 'documentation' => 'docs/codebase/module-map.md', 'test' => 'tests/v2/Workflows/Registration/CapabilitySurfaceV2Test.php'],
            ['capability' => 'Workflows/PlayerContext', 'sources' => ['app/Workflows/PlayerContext'], 'documentation' => 'docs/architecture/contexts/game-world/player-context.md', 'test' => 'tests/v2/Workflows/PlayerContext/CapabilitySurfaceV2Test.php'],
            ['capability' => 'Workflows/KingdomGovernance', 'sources' => ['app/Workflows/KingdomGovernance'], 'documentation' => 'docs/architecture/contexts/game-world/kingdom-governance.md', 'test' => 'tests/v2/Workflows/KingdomGovernance/CapabilitySurfaceV2Test.php'],
            ['capability' => 'Workflows/KingdomTransfer', 'sources' => ['app/Workflows/KingdomTransfer'], 'documentation' => 'docs/codebase/module-map.md', 'test' => 'tests/v2/Workflows/KingdomTransfer/CapabilitySurfaceV2Test.php'],
            ['capability' => 'ReadModels/AllianceDashboard', 'sources' => ['app/ReadModels/AllianceDashboard'], 'documentation' => 'docs/codebase/module-map.md', 'test' => 'tests/v2/ReadModels/AllianceDashboard/CapabilitySurfaceV2Test.php'],
            ['capability' => 'ReadModels/EventCalendar', 'sources' => ['app/ReadModels/EventCalendar'], 'documentation' => 'docs/codebase/module-map.md', 'test' => 'tests/v2/ReadModels/EventCalendar/CapabilitySurfaceV2Test.php'],
            ['capability' => 'ReadModels/EventHistory', 'sources' => ['app/ReadModels/EventHistory'], 'documentation' => 'docs/codebase/module-map.md', 'test' => 'tests/v2/ReadModels/EventHistory/CapabilitySurfaceV2Test.php'],
            ['capability' => 'ReadModels/EventManagement', 'sources' => ['app/ReadModels/EventManagement'], 'documentation' => 'docs/codebase/module-map.md', 'test' => 'tests/v2/ReadModels/EventManagement/CapabilitySurfaceV2Test.php'],
            ['capability' => 'ReadModels/KingdomIntelligence', 'sources' => ['app/ReadModels/KingdomIntelligence'], 'documentation' => 'docs/codebase/module-map.md', 'test' => 'tests/v2/ReadModels/KingdomIntelligence/CapabilitySurfaceV2Test.php'],
            ['capability' => 'ReadModels/KingdomSettings', 'sources' => ['app/ReadModels/KingdomSettings'], 'documentation' => 'docs/codebase/module-map.md', 'test' => 'tests/v2/ReadModels/KingdomSettings/CapabilitySurfaceV2Test.php'],
            ['capability' => 'ReadModels/SharedKingdomIntelligence', 'sources' => ['app/ReadModels/SharedKingdomIntelligence'], 'documentation' => 'docs/codebase/module-map.md', 'test' => 'tests/v2/ReadModels/SharedKingdomIntelligence/CapabilitySurfaceV2Test.php'],
            ['capability' => 'Shared/AuditTrail', 'sources' => ['app/Shared/Infrastructure/AuditTrail'], 'documentation' => 'docs/architecture/integration-model.md', 'test' => 'tests/v2/Shared/AuditTrail/CapabilitySurfaceV2Test.php'],
            ['capability' => 'Shared/Outbox', 'sources' => ['app/Shared/Infrastructure/Messaging/Outbox'], 'documentation' => 'docs/architecture/integration-model.md', 'test' => 'tests/v2/Shared/Outbox/CapabilitySurfaceV2Test.php'],
        ];

        self::assertCount(42, $capabilities);

        foreach ($capabilities as $capability) {
            self::assertFileExists(base_path($capability['documentation']), $capability['capability'].' documentation missing.');
            self::assertFileExists(base_path($capability['test']), $capability['capability'].' V2 contract missing.');
            foreach ($capability['sources'] as $source) {
                self::assertDirectoryExists(base_path($source), $capability['capability'].' source missing: '.$source);
            }
        }
    }
}
