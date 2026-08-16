<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;

final class KingdomRosterStructureTest extends TestCase
{
    public function test_roster_runtime_is_owned_by_game_world_and_intelligence(): void
    {
        $root = dirname(__DIR__, 2);

        self::assertFileExists($root.'/app/Contexts/GameWorld/Models/Player.php');
        self::assertDirectoryExists($root.'/app/Contexts/Intelligence/Roster/Actions');
        self::assertDirectoryExists($root.'/app/Contexts/Intelligence/Roster/Models');
        self::assertDirectoryExists($root.'/app/Contexts/Intelligence/Roster/Queries');
        self::assertDirectoryExists($root.'/app/Contexts/Intelligence/Roster/Services');
        self::assertDirectoryExists($root.'/app/Contexts/Intelligence/Roster/Http');

        foreach ([
            'ResolvePlayer.php',
            'SaveRosterEntry.php',
            'MarkRosterEntryLeft.php',
            'RecordPlayerSnapshot.php',
            'PreviewRosterCsvImport.php',
            'CommitRosterCsvImport.php',
        ] as $action) {
            self::assertFileExists($root.'/app/Contexts/Intelligence/Roster/Actions/'.$action);
        }

        self::assertFileDoesNotExist($root.'/app/Domain/Kingdoms/Models/Player.php');
        self::assertFileDoesNotExist($root.'/app/Domain/Kingdoms/Actions/SaveRosterEntry.php');
    }

    public function test_unapproved_follow_on_kingdoms_runtime_is_not_reintroduced(): void
    {
        $root = dirname(__DIR__, 2);

        self::assertDirectoryDoesNotExist($root.'/app/Domain/Kingdoms/Diplomacy');
        self::assertDirectoryDoesNotExist($root.'/app/Domain/Kingdoms/Ingestion');
        self::assertFileDoesNotExist($root.'/app/Domain/Kingdoms/Http/Controllers/KingdomApiController.php');
    }

    public function test_roster_and_intelligence_are_not_registered_as_public_api_contracts(): void
    {
        $root = dirname(__DIR__, 2);
        $apiRoutes = file_get_contents($root.'/routes/api.php');
        self::assertIsString($apiRoutes);

        self::assertStringNotContainsString('/kingdoms', $apiRoutes);
        self::assertStringNotContainsString('/roster', $apiRoutes);
        self::assertStringNotContainsString('kingdoms:', $apiRoutes);
    }

    public function test_internal_events_are_deny_by_default_for_webhook_fanout(): void
    {
        $root = dirname(__DIR__, 2);
        $fanout = file_get_contents($root.'/app/Contexts/Platform/Integrations/Actions/QueueWebhookDeliveries.php');
        $catalog = file_get_contents($root.'/app/Contexts/Platform/Integrations/Contracts/WebhookEventCatalog.php');
        self::assertIsString($fanout);
        self::assertIsString($catalog);

        self::assertStringContainsString('WebhookEventCatalog::isPublic($eventType)', $fanout);
        self::assertStringContainsString("'alliance.created'", $catalog);
        self::assertStringContainsString("'member.joined'", $catalog);
        self::assertStringNotContainsString("'kingdoms.", $catalog);
        self::assertStringNotContainsString("'intelligence.", $catalog);
    }
}
