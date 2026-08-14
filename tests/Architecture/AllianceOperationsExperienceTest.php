<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;

final class AllianceOperationsExperienceTest extends TestCase
{
    public function test_alliance_overview_links_to_global_scoped_events_surface(): void
    {
        $source = $this->read('resources/js/pages/Alliance/Overview.vue');

        self::assertStringContainsString('href="/events"', $source);
        self::assertStringContainsString('href="/events/create"', $source);
    }

    public function test_scoped_events_surfaces_use_shared_application_shell(): void
    {
        foreach (['Index', 'Create', 'Show', 'Manage'] as $page) {
            $source = $this->read("resources/js/pages/Events/{$page}.vue");
            self::assertStringContainsString("import AppLayout from '../../layouts/AppLayout.vue';", $source);
            self::assertStringContainsString('<AppLayout', $source);
        }
    }

    public function test_events_routes_are_global_and_scope_is_domain_data(): void
    {
        $routes = $this->read('routes/web.php');

        foreach (['/events', '/events/create', '/events/export.csv', '/events/feed.ics'] as $route) {
            self::assertStringContainsString($route, $routes);
        }

    }

    private function read(string $path): string
    {
        $source = file_get_contents(dirname(__DIR__, 2).'/'.$path);
        self::assertIsString($source, $path);

        return $source;
    }
}
