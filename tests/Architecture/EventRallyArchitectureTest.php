<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;

final class EventRallyArchitectureTest extends TestCase
{
    public function test_rally_schema_uses_player_identity_and_alliance_operating_context(): void
    {
        $migration = $this->read('database/migrations/2026_08_13_050000_create_rally_operations_tables.php');

        self::assertStringContainsString("foreignUlid('player_id')->constrained('players')", $migration);
        self::assertStringContainsString("foreignUlid('alliance_id')->constrained('alliances')", $migration);
        self::assertStringContainsString('rally_assignments_active_lead_unique', $migration);
        self::assertStringContainsString('rally_assignments_active_slot_unique', $migration);
    }

    public function test_self_routes_derive_player_identity_from_active_player_context(): void
    {
        $routes = $this->read('routes/web.php');
        $controller = $this->read('app/Contexts/Operations/Rallies/Http/Controllers/EventRallyController.php');

        self::assertStringContainsString('/player/formations', $routes);
        self::assertStringContainsString('/events/{occurrence}/rally-assignments/{assignment}/response', $routes);
        self::assertStringContainsString('private readonly PlayerContext $playerContext', $controller);
        self::assertStringContainsString('$actor = $this->player();', $controller);
        self::assertStringContainsString('$this->playerContext->playerOrNull()', $controller);
        self::assertStringNotContainsString('/rally-assignments/{assignment}/players/{player}/response', $routes);
    }

    public function test_custom_kingdom_events_enable_rally_guidance_and_formations_for_multi_alliance_plans(): void
    {
        $catalogue = $this->read('app/Contexts/Operations/EventCore/Catalog/KingShotEventTypeCatalog.php');

        self::assertStringContainsString('EventScope::Kingdom, 60, null', $catalogue);
        self::assertStringContainsString('EventCapability::RallyGuidance', $catalogue);
        self::assertStringContainsString('EventCapability::Formations', $catalogue);
    }

    public function test_event_surfaces_render_rally_modules_by_capability(): void
    {
        $show = $this->read('resources/js/pages/Events/Show.vue');
        $manage = $this->read('resources/js/pages/Events/Manage.vue');

        self::assertStringContainsString("event.capabilities.includes('rally_guidance')", $show);
        self::assertStringContainsString("event.capabilities.includes('formations')", $show);
        self::assertStringContainsString('rallyOperations', $manage);
        self::assertStringContainsString('/player/formations', $show);
    }

    private function read(string $path): string
    {
        $source = file_get_contents(dirname(__DIR__, 2).'/'.$path);
        self::assertIsString($source, $path);

        return $source;
    }
}
