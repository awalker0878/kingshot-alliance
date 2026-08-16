<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;

final class EventBattlePlanArchitectureTest extends TestCase
{
    public function test_battle_plan_uses_player_identity_and_exact_assignment_targets(): void
    {
        $root = dirname(__DIR__, 2);
        $migration = file_get_contents($root.'/database/migrations/2026_08_13_060000_create_event_battle_plan_tables.php');
        $controller = file_get_contents($root.'/app/Contexts/Operations/BattlePlans/Http/Controllers/EventBattlePlanController.php');

        self::assertIsString($migration);
        self::assertIsString($controller);
        self::assertStringContainsString("foreignUlid('player_id')", $migration);
        self::assertStringContainsString("foreignUlid('roster_id')", $migration);
        self::assertStringContainsString('objective assignment requires exactly one target', $migration);
        self::assertStringNotContainsString('membership_id', $migration);
        self::assertStringNotContainsString('membership_id', $controller);
    }
}
