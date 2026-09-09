<?php

declare(strict_types=1);

namespace Tests\Architecture;

use Tests\TestCase;

final class KingdomOperationalReadBoundaryV3Test extends TestCase
{
    public function test_current_operational_surfaces_use_active_kingdom_contracts(): void
    {
        $expectations = [
            'app/ReadModels/AllianceDashboard/Http/Controllers/AllianceOverviewController.php' => 'requireActive(',
            'app/ReadModels/BotCommands/Queries/AllianceCommandFeedQuery.php' => 'requireActive(',
            'app/Contexts/GameWorld/Governance/Http/Controllers/KingdomRoleController.php' => 'requireActive(',
            'app/Contexts/Intelligence/Ingestion/Http/Controllers/KingdomIngestionController.php' => 'requireActive(',
            'app/Contexts/Intelligence/Observations/Http/Controllers/KingdomAllianceController.php' => 'requireActive(',
            'app/Contexts/Intelligence/Diplomacy/Http/Controllers/KingdomAllianceDiplomacyContactController.php' => 'requireActiveCanonical(',
            'app/Contexts/GameWorld/KingdomTransfers/Access/Services/TransferAuthorization.php' => 'findActive(',
        ];

        foreach ($expectations as $path => $contract) {
            $source = file_get_contents(base_path($path));
            self::assertIsString($source, $path);
            self::assertStringContainsString($contract, $source, $path);
        }
    }

    public function test_explicit_history_surfaces_preserve_historical_kingdom_resolution(): void
    {
        foreach ([
            'app/ReadModels/KingdomGovernance/Http/Controllers/KingdomGovernanceHistoryController.php',
            'app/ReadModels/EventHistory/Http/Controllers/EventHistoryController.php',
            'app/ReadModels/Roster/Http/Controllers/PlayerSnapshotHistoryController.php',
            'app/Contexts/Intelligence/Observations/Http/Controllers/KingdomAllianceObservationController.php',
            'app/Contexts/Operations/Events/Services/EventTargetResolver.php',
        ] as $path) {
            $source = file_get_contents(base_path($path));
            self::assertIsString($source, $path);
            self::assertStringContainsString('->require(', $source, $path);
        }
    }

    public function test_transfer_evidence_identity_comparison_stays_behind_active_target_authorization(): void
    {
        $targetQuery = file_get_contents(base_path('app/Contexts/GameWorld/KingdomTransfers/Queries/TransferEvidenceTargetQuery.php'));
        $review = file_get_contents(base_path('app/Contexts/Intelligence/Evidence/Actions/SaveTransferEvidenceReview.php'));

        self::assertIsString($targetQuery);
        self::assertIsString($review);
        self::assertStringContainsString('lockActiveShared(', $targetQuery);
        self::assertStringContainsString('authorizeManage(', $review);
        self::assertStringContainsString('findByNumber(', $review);
    }
}
