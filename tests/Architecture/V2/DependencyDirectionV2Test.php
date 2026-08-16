<?php

declare(strict_types=1);

namespace Tests\Architecture\V2;

use PHPUnit\Framework\TestCase;
use Tests\Support\V2\ArchitectureCatalogue;
use Tests\Support\V2\RepositoryInspector;

final class DependencyDirectionV2Test extends TestCase
{
    public function test_context_dependencies_flow_only_toward_foundational_contexts(): void
    {
        foreach (ArchitectureCatalogue::forbiddenContextDependencies() as $context => $forbiddenContexts) {
            foreach (RepositoryInspector::phpFiles('app/Contexts/'.$context) as $file) {
                $source = RepositoryInspector::source($file);

                foreach ($forbiddenContexts as $forbiddenContext) {
                    self::assertStringNotContainsString(
                        'App\\Contexts\\'.$forbiddenContext.'\\',
                        $source,
                        RepositoryInspector::relative($file).' reaches downstream into '.$forbiddenContext.'.',
                    );
                }
            }
        }
    }

    public function test_shared_is_business_context_agnostic(): void
    {
        foreach (RepositoryInspector::phpFiles('app/Shared') as $file) {
            $source = RepositoryInspector::source($file);

            foreach (['App\\Contexts\\', 'App\\Workflows\\', 'App\\ReadModels\\', 'App\\Domain\\'] as $forbidden) {
                self::assertStringNotContainsString(
                    $forbidden,
                    $source,
                    RepositoryInspector::relative($file).' makes Shared depend on a business owner.',
                );
            }
        }
    }

    public function test_business_context_core_does_not_depend_on_orchestration_or_composed_read_models(): void
    {
        foreach (RepositoryInspector::phpFiles('app/Contexts') as $file) {
            $relative = RepositoryInspector::relative($file);
            $source = RepositoryInspector::source($file);

            self::assertStringNotContainsString(
                'App\\Workflows\\',
                $source,
                $relative.' depends on orchestration.',
            );

            if (! str_contains('/'.str_replace('\\', '/', $relative), '/Http/')) {
                self::assertStringNotContainsString(
                    'App\\ReadModels\\',
                    $source,
                    $relative.' depends on a composed read model outside the presentation boundary.',
                );
            }
        }
    }

    public function test_workflows_and_read_models_never_reach_legacy_code(): void
    {
        foreach (['app/Workflows', 'app/ReadModels'] as $directory) {
            foreach (RepositoryInspector::phpFiles($directory) as $file) {
                self::assertStringNotContainsString(
                    'App\\Domain\\',
                    RepositoryInspector::source($file),
                    RepositoryInspector::relative($file).' reaches the removed V1 tree.',
                );
            }
        }
    }

    public function test_final_runtime_contains_no_compatibility_namespaces_or_aliases(): void
    {
        foreach (['app/Contexts', 'app/Shared', 'app/Workflows', 'app/ReadModels'] as $directory) {
            foreach (RepositoryInspector::phpFiles($directory) as $file) {
                $relative = RepositoryInspector::relative($file);
                $source = RepositoryInspector::source($file);

                self::assertDoesNotMatchRegularExpression('#/(Legacy|Compatibility|Compat)(/|$)#', '/'.$relative);
                self::assertStringNotContainsString('class_alias(', $source, $relative.' introduces a compatibility alias.');
            }
        }
    }
}
