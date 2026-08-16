<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;

final class AzureDeploymentDocumentationTest extends TestCase
{
    public function test_provider_deployment_stays_inside_system_operations(): void
    {
        self::assertFileExists($this->root().'/docs/operations/deployment/azure.md');
        self::assertDirectoryDoesNotExist($this->root().'/docs/deployment');
        self::assertStringContainsString('Azure/container deployment', $this->read('docs/operations/deployment/azure.md'));
    }

    public function test_azure_runtime_document_preserves_current_hosted_invariants(): void
    {
        $azure = $this->read('docs/operations/deployment/azure.md');

        foreach ([
            'immutable web application image',
            'PostgreSQL 18-compatible',
            'Redis service',
            'S3-compatible',
            'managed secret injection',
            'same immutable image identity',
            'trusted proxies',
            'webhook egress policy',
        ] as $invariant) {
            self::assertStringContainsString($invariant, $azure, $invariant);
        }
    }

    public function test_provider_documentation_contains_no_literal_guid_or_laravel_secret_material(): void
    {
        $azure = $this->read('docs/operations/deployment/azure.md');

        self::assertDoesNotMatchRegularExpression(
            '/\b[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}\b/',
            $azure,
        );
        self::assertDoesNotMatchRegularExpression('/base64:[A-Za-z0-9+\/=]{20,}/', $azure);
    }

    public function test_documentation_standard_keeps_provider_material_under_operations(): void
    {
        $standard = $this->read('docs/governance/documentation-standard.md');

        self::assertStringContainsString('Operations | How is the application deployed, monitored and recovered?', $standard);
        self::assertStringContainsString('Deployment', $this->read('docs/operations/README.md'));
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
