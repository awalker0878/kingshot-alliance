<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;

final class EventPhasePollArchitectureTest extends TestCase
{
    public function test_phase_poll_schema_is_occurrence_and_player_scoped(): void
    {
        $migration = $this->read('database/migrations/2026_08_13_030000_create_event_phase_and_poll_tables.php');

        self::assertStringContainsString("Schema::create('event_phases'", $migration);
        self::assertStringContainsString("Schema::create('event_polls'", $migration);
        self::assertStringContainsString("Schema::create('event_poll_options'", $migration);
        self::assertStringContainsString("Schema::create('event_poll_votes'", $migration);
        self::assertStringContainsString("foreignUlid('occurrence_id')", $migration);
        self::assertStringContainsString("foreignUlid('player_id')", $migration);
        self::assertStringContainsString("unique(['poll_id', 'option_id', 'player_id']", $migration);
        self::assertStringContainsString("foreign(['option_id', 'poll_id']", $migration);
        self::assertStringContainsString("foreignUlid('poll_id')->nullable()", $migration);
        self::assertStringContainsString('before_poll_close', $migration);
    }

    public function test_vote_route_uses_active_player_context_without_player_route_identity(): void
    {
        $routes = $this->read('routes/web.php');
        $controller = $this->read('app/Contexts/Operations/EventCore/Http/Controllers/EventOperationsController.php');

        self::assertStringContainsString("Route::put('/events/{occurrence}/polls/{poll}/vote'", $routes);
        self::assertStringContainsString('private readonly PlayerContext $playerContext', $controller);
        self::assertStringContainsString('$actor = $this->player();', $controller);
        self::assertStringContainsString('$this->playerContext->playerOrNull()', $controller);
        self::assertStringContainsString('CastEventPollVote', $controller);
    }

    public function test_swordland_operations_are_catalogue_driven(): void
    {
        $catalogue = $this->read('app/Contexts/Operations/EventCore/Catalog/KingShotEventTypeCatalog.php');

        self::assertStringContainsString("self::type('swordland-showdown'", $catalogue);
        self::assertStringContainsString("'default_phases'", $catalogue);
        self::assertStringContainsString("'battle-time'", $catalogue);
        self::assertStringContainsString("'poll_type' => 'time_vote'", $catalogue);
        self::assertStringContainsString("'deadline_reminder_minutes' => 60", $catalogue);
    }

    public function test_show_and_manage_are_capability_driven_for_phases_and_polls(): void
    {
        $show = $this->read('resources/js/pages/Events/Show.vue');
        $manage = $this->read('resources/js/pages/Events/Manage.vue');

        self::assertStringContainsString('event.operations.phases.length', $show);
        self::assertStringContainsString('event.operations.polls.length', $show);
        self::assertStringContainsString("event.capabilities.includes('phases')", $manage);
        self::assertStringContainsString("event.capabilities.includes('polls')", $manage);
        self::assertStringContainsString('saveVote', $show);
        self::assertStringContainsString('savePhase', $manage);
        self::assertStringContainsString('savePoll', $manage);
    }

    private function read(string $path): string
    {
        $source = file_get_contents(dirname(__DIR__, 2).'/'.$path);
        self::assertIsString($source, $path);

        return $source;
    }
}
