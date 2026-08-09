<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;

final class KingdomRosterStructureTest extends TestCase
{
    public function test_kingdoms_increment_runtime_is_owned_by_the_kingdoms_domain(): void
    {
        $root = dirname(__DIR__, 2);

        foreach ([
            'Enums/RosterState.php',
            'Models/KingdomPlayer.php',
            'Models/AllianceRosterEntry.php',
            'Models/PlayerSnapshot.php',
            'Models/RosterImport.php',
            'Actions/ResolveKingdomPlayer.php',
            'Actions/SaveRosterEntry.php',
            'Actions/MarkRosterEntryLeft.php',
            'Actions/RecordPlayerSnapshot.php',
            'Actions/PreviewRosterCsvImport.php',
            'Actions/CommitRosterCsvImport.php',
            'Queries/RosterQuery.php',
            'Queries/PlayerSnapshotQuery.php',
            'Services/PowerMath.php',
            'Services/RosterIntelligence.php',
            'Services/RosterCsvParser.php',
            'Services/RosterCsvExporter.php',
            'Http/Controllers/RosterController.php',
            'Http/Controllers/PlayerSnapshotController.php',
            'Http/Controllers/RosterIntelligenceController.php',
            'Http/Controllers/RosterCsvController.php',
        ] as $path) {
            self::assertFileExists($root.'/app/Domain/Kingdoms/'.$path);
        }

        foreach ([
            'RosterHistory.vue',
            'RosterIntelligence.vue',
            'RosterImport.vue',
        ] as $page) {
            self::assertFileExists($root.'/resources/js/pages/Alliance/'.$page);
        }
    }

    public function test_unapproved_follow_on_kingdoms_runtime_is_not_introduced(): void
    {
        $root = dirname(__DIR__, 2);

        self::assertDirectoryDoesNotExist($root.'/app/Domain/Kingdoms/Transfers');
        self::assertDirectoryDoesNotExist($root.'/app/Domain/Kingdoms/Diplomacy');
        self::assertDirectoryDoesNotExist($root.'/app/Domain/Kingdoms/Ingestion');
        self::assertFileDoesNotExist($root.'/app/Domain/Kingdoms/Http/Controllers/KingdomApiController.php');
    }

    public function test_kingdoms_roster_and_intelligence_are_not_registered_as_public_api_contracts(): void
    {
        $root = dirname(__DIR__, 2);
        $apiRoutes = file_get_contents($root.'/routes/api.php');
        self::assertIsString($apiRoutes);

        self::assertStringNotContainsString('/kingdoms', $apiRoutes);
        self::assertStringNotContainsString('/roster', $apiRoutes);
        self::assertStringNotContainsString('kingdoms:', $apiRoutes);
    }

    public function test_uncontracted_kingdoms_outbox_events_are_explicitly_excluded_from_webhook_fanout(): void
    {
        $root = dirname(__DIR__, 2);
        $fanout = file_get_contents($root.'/app/Domain/Integrations/Actions/QueueWebhookDeliveries.php');
        self::assertIsString($fanout);

        self::assertStringContainsString("\$eventType !== 'alliance.kingdom_updated'", $fanout);
        self::assertStringContainsString("str_starts_with(\$eventType, 'kingdoms.')", $fanout);
    }
}
