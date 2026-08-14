<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;

final class EventParticipationArchitectureTest extends TestCase
{
    public function test_self_participation_routes_resolve_player_from_server_context(): void
    {
        $routes = $this->read('routes/web.php');
        self::assertStringContainsString("Route::post('/events/{occurrence}/responses'", $routes);
        self::assertStringContainsString("Route::post('/events/{occurrence}/registrations'", $routes);
        self::assertStringContainsString("Route::delete('/events/{occurrence}/registrations'", $routes);

        $controller = $this->read('app/Domain/Events/Http/Controllers/EventParticipationController.php');
        self::assertStringContainsString('PlayerContext $context', $controller);
        self::assertStringContainsString('$this->activePlayer($user, $context)', $controller);
    }

    public function test_participation_persistence_is_player_keyed(): void
    {
        $migration = $this->read('database/migrations/2026_08_13_020000_create_event_participation_tables.php');
        foreach (['event_responses', 'event_registrations', 'event_attendance', 'event_reminder_deliveries'] as $table) {
            self::assertStringContainsString("Schema::create('{$table}'", $migration);
        }
        self::assertStringContainsString("unique(['occurrence_id', 'player_id'])", $migration);
        self::assertStringContainsString("unique(['rule_id', 'occurrence_id', 'player_id']", $migration);
    }

    private function read(string $path): string
    {
        $source = file_get_contents(dirname(__DIR__, 2).'/'.$path);
        self::assertIsString($source, $path);

        return $source;
    }
}
