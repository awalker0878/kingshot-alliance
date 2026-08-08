<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;

final class KingdomRosterStructureTest extends TestCase
{
    public function test_slice_b_through_d_runtime_is_owned_by_the_kingdoms_domain(): void
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

    public function test_unapproved_follow_on_kingdoms_runtime_is_not_introduced_by_slice_d(): void
    {
        $root = dirname(__DIR__, 2);

        self::assertDirectoryDoesNotExist($root.'/app/Domain/Kingdoms/Transfers');
        self::assertDirectoryDoesNotExist($root.'/app/Domain/Kingdoms/Diplomacy');
        self::assertDirectoryDoesNotExist($root.'/app/Domain/Kingdoms/Ingestion');
        self::assertFileDoesNotExist($root.'/app/Domain/Kingdoms/Http/Controllers/KingdomApiController.php');
    }
}
