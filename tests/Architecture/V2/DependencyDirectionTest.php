<?php

declare(strict_types=1);

namespace Tests\Architecture\V2;

use PHPUnit\Framework\TestCase;
use Tests\Support\V2\ArchitectureCatalogue;
use Tests\Support\V2\RepositoryInspector;

final class DependencyDirectionTest extends TestCase
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

    public function test_business_contexts_do_not_depend_on_workflows_or_read_models(): void
    {
        foreach (RepositoryInspector::phpFiles('app/Contexts') as $file) {
            $source = RepositoryInspector::source($file);

            self::assertStringNotContainsString('App\\Workflows\\', $source, RepositoryInspector::relative($file).' depends on orchestration.');
            self::assertStringNotContainsString('App\\ReadModels\\', $source, RepositoryInspector::relative($file).' depends on a composed read model.');
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
