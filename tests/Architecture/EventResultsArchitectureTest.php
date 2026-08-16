<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;

final class EventResultsArchitectureTest extends TestCase
{
    public function test_results_use_player_identity_and_separate_actor_identity(): void
    {
        $root = dirname(__DIR__, 2);
        $migration = file_get_contents($root.'/database/migrations/2026_08_13_070000_create_event_result_tables.php');
        $controller = file_get_contents($root.'/app/Contexts/Operations/Results/Http/Controllers/EventResultController.php');
        $intelligence = file_get_contents($root.'/app/Contexts/Intelligence/EventAnalysis/Queries/EventPlayerIntelligenceQuery.php');

        self::assertIsString($migration);
        self::assertIsString($controller);
        self::assertIsString($intelligence);
        self::assertStringContainsString("foreignUlid('player_id')", $migration);
        self::assertStringContainsString("foreignUlid('recorded_by_player_id')", $migration);
        self::assertStringNotContainsString('recorded_by_user_id', $migration);
        self::assertStringContainsString("unique(['occurrence_id', 'player_id'])", $migration);
        self::assertStringNotContainsString('membership_id', $migration);
        self::assertStringNotContainsString('membership_id', $controller);
        self::assertStringNotContainsString('user_id', $intelligence);
    }
}
