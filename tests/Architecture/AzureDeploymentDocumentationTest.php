<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;

final class AzureDeploymentDocumentationTest extends TestCase
{
    /** @return list<string> */
    private function azureDeploymentFiles(): array
    {
        return [
            'docs/operations/deployment/README.md',
            'docs/operations/deployment/azure/README.md',
            'docs/operations/deployment/azure/bootstrap.md',
            'docs/operations/deployment/azure/networking.md',
            'docs/operations/deployment/azure/data-services.md',
            'docs/operations/deployment/azure/container-apps.md',
            'docs/operations/deployment/azure/application-configuration.md',
            'docs/operations/deployment/azure/github-actions.md',
            'docs/operations/deployment/azure/validation-and-recovery.md',
        ];
    }

    public function test_provider_deployment_lives_under_shared_operations(): void
    {
        self::assertDirectoryDoesNotExist(
            $this->root().'/docs/deployment',
            'Provider deployment documentation belongs under docs/operations/deployment/, not a new top-level docs group.',
        );

        foreach ($this->azureDeploymentFiles() as $path) {
            self::assertFileExists($this->root().'/'.$path, $path);
        }
    }

    public function test_azure_deployment_index_links_every_required_procedure(): void
    {
        $index = $this->read('docs/operations/deployment/azure/README.md');

        foreach ([
            'bootstrap.md',
            'networking.md',
            'data-services.md',
            'container-apps.md',
            'application-configuration.md',
            'github-actions.md',
            'validation-and-recovery.md',
        ] as $file) {
            self::assertStringContainsString($file, $index, $file);
        }

        $operations = $this->read('docs/operations/README.md');
        self::assertStringContainsString('deployment/README.md', $operations);
        self::assertStringContainsString('deployment/azure/README.md', $operations);

        $standard = $this->read('docs/product/documentation-standard.md');
        self::assertStringContainsString('docs/operations/deployment/<provider>/', $standard);
        self::assertStringContainsString('`docs/deployment/`', $standard);
    }

    public function test_azure_deployment_examples_remain_placeholder_only_for_sensitive_identifiers(): void
    {
        $combined = implode("\n", array_map(
            fn (string $path): string => $this->read($path),
            $this->azureDeploymentFiles(),
        ));

        foreach ([
            '<AZURE-SUBSCRIPTION-ID>',
            '<AZURE-REGION>',
            '<APP-PREFIX>',
            '<GITHUB-OWNER>',
            '<GITHUB-REPOSITORY>',
        ] as $placeholder) {
            self::assertStringContainsString($placeholder, $combined, $placeholder);
        }

        self::assertDoesNotMatchRegularExpression(
            '/\b[0-9a-fA-F]{8}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{4}-[0-9a-fA-F]{12}\b/',
            $combined,
            'Provider documentation must not contain literal Azure/Entra GUID-shaped identifiers.',
        );

        self::assertDoesNotMatchRegularExpression(
            '/base64:[A-Za-z0-9+\/=]{20,}/',
            $combined,
            'Provider documentation must not contain literal Laravel-style base64 secret material.',
        );
    }

    public function test_azure_blueprint_documents_immutable_and_private_runtime_invariants(): void
    {
        $index = $this->read('docs/operations/deployment/azure/README.md');
        $containers = $this->read('docs/operations/deployment/azure/container-apps.md');
        $data = $this->read('docs/operations/deployment/azure/data-services.md');
        $configuration = $this->read('docs/operations/deployment/azure/application-configuration.md');

        self::assertStringContainsString('same immutable image', $index);
        self::assertStringContainsString('127.0.0.1:9000', $index);
        self::assertStringContainsString('allowInsecure: false', $containers);
        self::assertStringContainsString('schedule:run', $containers);
        self::assertStringContainsString('migrate --force', $containers);
        self::assertStringContainsString('REDIS_PORT=10000', $data);
        self::assertStringContainsString('REDIS_SCHEME=tls', $data);
        self::assertStringContainsString('PULSE_ENABLED=false', $configuration);
        self::assertStringContainsString('SESSION_SECURE_COOKIE=true', $configuration);
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
