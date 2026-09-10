<?php

declare(strict_types=1);

namespace Tests\ReadModels\IntelligenceSignals\Architecture;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;
use Tests\Support\RepositoryPath;

final class IntelligenceChangeDetectionArchitectureV3Test extends TestCase
{
    public function test_change_detection_is_read_side_composition_without_new_bounded_context(): void
    {
        foreach (['IntelligenceChange', 'ChangeDetection', 'Signals'] as $context) {
            self::assertDirectoryDoesNotExist(RepositoryPath::fromRoot('app/Contexts/'.$context));
        }

        $sources = $this->phpSources(RepositoryPath::fromRoot('app/ReadModels/IntelligenceSignals'));
        self::assertNotEmpty($sources);

        foreach ([' extends Model', '\\Actions\\', '->save(', '->delete(', '::create(', '::insert(', '::upsert('] as $needle) {
            foreach ($sources as $path => $source) {
                self::assertStringNotContainsString(
                    $needle,
                    $source,
                    $path.' must not become a derived-signal persistence owner; forbidden token: '.$needle,
                );
            }
        }
    }

    public function test_no_authoritative_intelligence_signal_table_is_introduced(): void
    {
        $sources = $this->phpSources(RepositoryPath::fromRoot('database/migrations'));
        foreach (['intelligence_signals', 'derived_intelligence_signals', 'change_detection_signals'] as $table) {
            foreach ($sources as $path => $source) {
                self::assertStringNotContainsString(
                    $table,
                    $source,
                    $path.' must not persist recomputable Intelligence Change Detection truth.',
                );
            }
        }
    }

    public function test_owner_contexts_do_not_depend_on_intelligence_signal_read_model(): void
    {
        foreach ($this->phpSources(RepositoryPath::fromRoot('app/Contexts')) as $path => $source) {
            self::assertStringNotContainsString(
                'App\\ReadModels\\IntelligenceSignals',
                $source,
                $path.' must not import IntelligenceSignals read-side composition.',
            );
        }
    }

    public function test_signal_contract_preserves_factual_discipline_and_complete_source_gate(): void
    {
        $factory = file_get_contents(RepositoryPath::fromRoot('app/ReadModels/IntelligenceSignals/Services/IntelligenceSignalFactory.php'));
        self::assertIsString($factory);
        self::assertStringContainsString('bool $completeSource', $factory);
        self::assertStringContainsString('if (! $completeSource', $factory);
        self::assertStringNotContainsString('attack_risk', $factory);
        self::assertStringNotContainsString('good_recruit', $factory);
        self::assertStringNotContainsString('likely_transfer', $factory);

        $query = file_get_contents(RepositoryPath::fromRoot('app/ReadModels/IntelligenceSignals/Queries/IntelligenceSignalQuery.php'));
        self::assertIsString($query);
        self::assertStringContainsString("->where('alliance_id', \$allianceId)", $query);
        self::assertStringContainsString('complete_roster_capture', $query);
    }

    public function test_frontend_signal_feed_is_navigation_only_semantically_accessible_and_scope_gated(): void
    {
        $source = file_get_contents(RepositoryPath::fromRoot('resources/js/components/intelligence/IntelligenceSignalFeed.vue'));
        self::assertIsString($source);

        foreach (['router.post(', 'router.put(', 'router.patch(', 'router.delete(', 'fetch('] as $needle) {
            self::assertStringNotContainsString($needle, $source);
        }
        self::assertStringContainsString('<ol', $source);
        self::assertStringContainsString('aria-labelledby=', $source);
        self::assertStringContainsString('signal.sourceOwner', $source);
        self::assertStringContainsString('signal.canonicalUrl', $source);

        $layout = file_get_contents(RepositoryPath::fromRoot('resources/js/layouts/AppLayout.vue'));
        self::assertIsString($layout);
        self::assertStringContainsString("page.component === 'Dashboard/Home'", $layout);
        self::assertStringContainsString('? props.hasPlayerAlliance', $layout);
        self::assertStringContainsString("page.component === 'Intelligence/KingdomWatch/AllianceDossier'", $layout);
    }

    /** @return array<string,string> */
    private function phpSources(string $directory): array
    {
        $sources = [];
        if (! is_dir($directory)) {
            return $sources;
        }

        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($directory));
        foreach ($iterator as $file) {
            if (! $file instanceof SplFileInfo || ! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }
            $contents = file_get_contents($file->getPathname());
            self::assertIsString($contents);
            $sources[$file->getPathname()] = $contents;
        }
        ksort($sources);

        return $sources;
    }
}
