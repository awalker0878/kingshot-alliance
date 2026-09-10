<?php

declare(strict_types=1);

namespace Tests\Contexts\Alliance\Content\Architecture;

use PHPUnit\Framework\TestCase;

final class AllianceRulesLockBoundaryTest extends TestCase
{
    public function test_canonical_rules_save_uses_the_exclusive_alliance_aggregate_lock(): void
    {
        $path = dirname(__DIR__, 5).'/app/Contexts/Alliance/Content/Actions/SaveAllianceRules.php';
        self::assertFileExists($path);
        $source = file_get_contents($path);
        self::assertIsString($source);
        self::assertStringContainsString('->lockExclusiveScope($actorPlayerId, $allianceId)', $source);
        self::assertStringNotContainsString('->lockActiveScope($actorPlayerId, $allianceId)', $source);
    }
}
