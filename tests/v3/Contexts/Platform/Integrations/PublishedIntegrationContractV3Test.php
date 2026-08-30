<?php

declare(strict_types=1);

namespace Tests\v3\Contexts\Platform\Integrations;

use App\Contexts\Platform\Integrations\Contracts\WebhookEventCatalog;
use PHPUnit\Framework\Attributes\Test;
use Tests\v3\TestCase;

final class PublishedIntegrationContractV3Test extends TestCase
{
    #[Test]
    public function openapi_paths_match_the_named_v1_routes_and_scopes(): void
    {
        $document = $this->jsonDocument('docs/reference/api/openapi.json');
        self::assertSame('3.1.0', $document['openapi'] ?? null);

        $expected = [
            'api.v1.alliance.show' => ['/api/v1/alliance', 'get', 'alliance:read'],
            'api.v1.events.index' => ['/api/v1/events', 'get', 'events:read'],
            'api.v1.contributions.index' => ['/api/v1/contributions', 'get', 'contributions:read'],
            'api.v1.commands.overview' => ['/api/v1/commands/overview', 'get', 'commands:read'],
            'api.v1.gift-codes.index' => ['/api/v1/gift-codes', 'get', 'gift-codes:read'],
            'api.v1.commands.knowledge' => ['/api/v1/commands/knowledge', 'get', 'content:read'],
            'api.v1.actor-links.claim' => ['/api/v1/actor-links/claims', 'post', 'actor-links:write'],
            'api.v1.me.events.response' => ['/api/v1/me/events/{occurrence}/response', 'put', 'event-participation:write'],
            'api.v1.me.events.registration' => ['/api/v1/me/events/{occurrence}/registration', 'put', 'event-participation:write'],
        ];

        foreach ($expected as $routeName => [$path, $method, $scope]) {
            $route = app('router')->getRoutes()->getByName($routeName);
            self::assertNotNull($route, $routeName);
            self::assertSame(ltrim($path, '/'), $route->uri());
            self::assertContains(strtoupper($method), $route->methods());
            self::assertSame($scope, $document['paths'][$path][$method]['x-required-scope'] ?? null);
        }
        self::assertEqualsCanonicalizing(
            array_column($expected, 0),
            array_keys($document['paths'] ?? []),
        );
    }

    #[Test]
    public function webhook_schema_event_and_payload_contracts_match_the_runtime_catalogue(): void
    {
        $schema = $this->jsonDocument('docs/reference/api/webhook-envelope.schema.json');
        self::assertSame('1.0', $schema['properties']['schema_version']['const'] ?? null);
        self::assertSame(
            WebhookEventCatalog::publicEvents(),
            $schema['properties']['event']['enum'] ?? null,
        );

        $schemaContracts = [];
        foreach ($schema['allOf'] ?? [] as $condition) {
            $event = $condition['if']['properties']['event']['const'] ?? null;
            $required = $condition['then']['properties']['data']['required'] ?? null;
            if (is_string($event) && is_array($required)) {
                $schemaContracts[$event] = array_values(array_map('strval', $required));
            }
        }

        $runtimeContracts = array_map(
            static fn (array $contract): array => $contract['required'],
            WebhookEventCatalog::contracts(),
        );
        self::assertSame($runtimeContracts, $schemaContracts);
    }

    /** @return array<string, mixed> */
    private function jsonDocument(string $relativePath): array
    {
        $contents = file_get_contents(dirname(__DIR__, 5).'/'.$relativePath);
        self::assertIsString($contents);
        $decoded = json_decode($contents, true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($decoded);

        return $decoded;
    }
}
