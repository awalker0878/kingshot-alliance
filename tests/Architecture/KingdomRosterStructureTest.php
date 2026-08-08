<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;

final class KingdomRosterStructureTest extends TestCase
{
    public function test_slice_b_runtime_is_owned_by_the_kingdoms_domain(): void
    {
        $root = dirname(__DIR__, 2);

        foreach ([
            'Enums/RosterState.php',
            'Models/KingdomPlayer.php',
            'Models/AllianceRosterEntry.php',
            'Actions/ResolveKingdomPlayer.php',
            'Actions/SaveRosterEntry.php',
            'Actions/MarkRosterEntryLeft.php',
            'Queries/RosterQuery.php',
            'Http/Controllers/RosterController.php',
        ] as $path) {
            self::assertFileExists($root.'/app/Domain/Kingdoms/'.$path);
        }
    }

    public function test_later_slice_runtime_is_not_introduced_early(): void
    {
        $root = dirname(__DIR__, 2);

        self::assertDirectoryDoesNotExist($root.'/app/Domain/Kingdoms/Imports');
        self::assertDirectoryDoesNotExist($root.'/app/Domain/Kingdoms/Exports');
        self::assertFileDoesNotExist($root.'/app/Domain/Kingdoms/Models/PlayerSnapshot.php');
        self::assertFileDoesNotExist($root.'/app/Domain/Kingdoms/Services/RosterIntelligence.php');
    }
}
