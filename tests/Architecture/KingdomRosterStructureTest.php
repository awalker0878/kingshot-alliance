<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;

final class KingdomRosterStructureTest extends TestCase
{
    public function test_slice_b_c1_and_c2_runtime_is_owned_by_the_kingdoms_domain(): void
    {
        $root = dirname(__DIR__, 2);

        foreach ([
            'Enums/RosterState.php',
            'Models/KingdomPlayer.php',
            'Models/AllianceRosterEntry.php',
            'Models/PlayerSnapshot.php',
            'Actions/ResolveKingdomPlayer.php',
            'Actions/SaveRosterEntry.php',
            'Actions/MarkRosterEntryLeft.php',
            'Actions/RecordPlayerSnapshot.php',
            'Queries/RosterQuery.php',
            'Queries/PlayerSnapshotQuery.php',
            'Services/PowerMath.php',
            'Services/RosterIntelligence.php',
            'Http/Controllers/RosterController.php',
            'Http/Controllers/PlayerSnapshotController.php',
            'Http/Controllers/RosterIntelligenceController.php',
        ] as $path) {
            self::assertFileExists($root.'/app/Domain/Kingdoms/'.$path);
        }

        self::assertFileExists($root.'/resources/js/pages/Alliance/RosterHistory.vue');
        self::assertFileExists($root.'/resources/js/pages/Alliance/RosterIntelligence.vue');
    }

    public function test_slice_d_runtime_is_not_introduced_early(): void
    {
        $root = dirname(__DIR__, 2);

        self::assertDirectoryDoesNotExist($root.'/app/Domain/Kingdoms/Imports');
        self::assertDirectoryDoesNotExist($root.'/app/Domain/Kingdoms/Exports');
        self::assertFileDoesNotExist($root.'/resources/js/pages/Alliance/RosterImport.vue');
    }
}
