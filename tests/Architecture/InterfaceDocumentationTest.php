<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;

final class InterfaceDocumentationTest extends TestCase
{
    public function test_reference_indexes_every_executable_route_file(): void
    {
        $reference = $this->read('docs/reference/routes.md');
        $entries = scandir($this->root().'/routes');
        self::assertIsArray($entries);

        $routes = array_values(array_filter(
            $entries,
            fn (string $entry): bool => str_ends_with($entry, '.php') && is_file($this->root().'/routes/'.$entry),
        ));

        self::assertNotSame([], $routes);

        foreach ($routes as $route) {
            self::assertStringContainsString('`routes/'.$route.'`', $reference, $route);
        }
    }

    public function test_api_reference_matches_current_v1_entry_points(): void
    {
        $api = $this->read('docs/reference/api/README.md');

        foreach ([
            'GET /v1/alliance',
            '`alliance:read`',
            'GET /v1/events',
            '`events:read`',
            'GET /v1/contributions',
            '`contributions:read`',
        ] as $contract) {
            self::assertStringContainsString($contract, $api);
        }
    }

    public function test_public_webhook_reference_is_not_confused_with_internal_events(): void
    {
        $events = $this->read('docs/reference/events.md');

        self::assertStringContainsString('alliance.created', $events);
        self::assertStringContainsString('member.joined', $events);
        self::assertStringContainsString('internal durable/outbox event', $events);
        self::assertStringContainsString('public webhook event', $events);
        self::assertStringContainsString('not automatically public API contracts', $events);
    }

    public function test_codebase_http_documentation_says_routes_are_not_bounded_contexts(): void
    {
        $http = $this->read('docs/codebase/routing-and-http.md');

        self::assertStringContainsString('A route filename is not a bounded context', $http);
        self::assertStringContainsString('Controllers/middleware', $http);
    }

    private function read(string $path): string
    {
        $source = file_get_contents($this->root().'/'.$path);
        self::assertIsString($source, $path);

        return $source;
    }

    private function root(): string
    {
        return dirname(__DIR__, 2);
    }
}
