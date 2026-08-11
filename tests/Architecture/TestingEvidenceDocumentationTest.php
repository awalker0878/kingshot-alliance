<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;

final class TestingEvidenceDocumentationTest extends TestCase
{
    public function test_every_code_domain_has_a_living_testing_and_evidence_profile(): void
    {
        $codeDomains = $this->directories($this->root().'/app/Domain');

        foreach ($codeDomains as $domain) {
            $documentationDomain = $this->kebabCase($domain);
            $path = $this->root().'/docs/domains/'.$documentationDomain.'/testing/README.md';

            self::assertFileExists(
                $path,
                sprintf('Missing domain testing/evidence profile: docs/domains/%s/testing/README.md', $documentationDomain),
            );

            $contents = file_get_contents($path);
            self::assertIsString($contents);
            self::assertStringContainsString('**Document type:** Living domain testing and evidence profile', $contents, $this->relativePath($path));
            self::assertStringContainsString('**Status:** Current', $contents, $this->relativePath($path));
            self::assertStringContainsString('**Owning domain:** '.$domain, $contents, $this->relativePath($path));
            self::assertStringContainsString('**Code owner:** `app/Domain/'.$domain.'`', $contents, $this->relativePath($path));
            self::assertStringContainsString('**Primary validation boundary:**', $contents, $this->relativePath($path));
            self::assertStringContainsString('**P5 evidence decision:**', $contents, $this->relativePath($path));
            self::assertStringContainsString('../README.md', $contents, $this->relativePath($path));
            self::assertStringContainsString('../security/README.md', $contents, $this->relativePath($path));
            self::assertStringContainsString('../operations/README.md', $contents, $this->relativePath($path));
            self::assertStringContainsString('../interfaces/README.md', $contents, $this->relativePath($path));
            self::assertStringContainsString('../../../product/testing-evidence-standard.md', $contents, $this->relativePath($path));
            self::assertStringContainsString('../../../product/testing-evidence-coverage-matrix.md', $contents, $this->relativePath($path));

            $this->assertHeadingsAppearInOrder($contents, [
                '## 1. Critical claims and validation ownership',
                '## 2. Executable suite mapping',
                '## 3. Architecture and domain-boundary validation',
                '## 4. Authorization, tenancy, security and privacy validation',
                '## 5. Feature, interface and integration validation',
                '## 6. Idempotency, concurrency and asynchronous validation',
                '## 7. Persistence, migration, rollback and recovery evidence',
                '## 8. Performance, query and capacity evidence',
                '## 9. Accessibility and frontend evidence',
                '## 10. Historical accepted evidence',
                '## 11. Evidence identity, retention and supersession',
                '## 12. Gaps, non-capabilities and related documentation',
            ], $path);
        }

        self::assertCount(14, $codeDomains, 'The frozen DCP-P5 inventory expects exactly 14 canonical code domains.');
    }

    public function test_p5_matrix_represents_the_exact_phpunit_suite_contract(): void
    {
        $matrix = $this->matrix();

        foreach ([
            'Architecture' => 'tests/Architecture',
            'Feature' => 'tests/Feature',
            'Integration' => 'tests/Integration',
            'Performance' => 'tests/Performance',
            'TenantIsolation' => 'tests/TenantIsolation',
            'Unit' => 'tests/Unit',
        ] as $suite => $directory) {
            self::assertStringContainsString('`'.$suite.'`', $matrix, sprintf('P5 matrix must name PHPUnit suite %s.', $suite));
            self::assertStringContainsString('`'.$directory.'`', $matrix, sprintf('P5 matrix must name PHPUnit directory %s.', $directory));
            self::assertDirectoryExists($this->root().'/'.$directory);
        }

        $phpunit = file_get_contents($this->root().'/phpunit.xml');
        self::assertIsString($phpunit);

        foreach (['Architecture', 'Feature', 'Integration', 'Performance', 'TenantIsolation', 'Unit'] as $suite) {
            self::assertStringContainsString('name="'.$suite.'"', $phpunit);
        }
    }

    public function test_p5_matrix_represents_backend_frontend_and_protected_evidence_classes(): void
    {
        $matrix = $this->matrix();

        foreach (['`composer check`', '`npm run check`', 'Dependency Review', 'CodeQL', 'CI'] as $evidence) {
            self::assertStringContainsString($evidence, $matrix, sprintf('P5 matrix is missing evidence class: %s', $evidence));
        }

        foreach ([
            'PostgreSQL migrations',
            'immutable production-image build',
            'ephemeral staging',
            'backup/restore',
            'image vulnerability scan',
        ] as $evidence) {
            self::assertStringContainsString($evidence, $matrix, sprintf('P5 matrix is missing CI evidence: %s', $evidence));
        }
    }

    public function test_p5_matrix_indexes_phase_acceptance_and_accessibility_evidence(): void
    {
        $matrix = $this->matrix();

        foreach (range(0, 6) as $phase) {
            $exit = sprintf('phase-%d-exit', $phase);
            self::assertStringContainsString('Phase '.$phase.' exit', $matrix, sprintf('P5 matrix must index %s.', $exit));
            self::assertFileExists($this->root().'/docs/product/'.$exit.'-report.md');
        }

        foreach (range(1, 6) as $phase) {
            $filename = $phase === 1 ? 'phase-1-accessibility-review.md' : sprintf('phase-%d-accessibility.md', $phase);
            self::assertStringContainsString($filename, $matrix, sprintf('P5 matrix must index accessibility evidence: %s', $filename));
            self::assertFileExists($this->root().'/docs/product/'.$filename);
        }

        self::assertStringContainsString('docs/domains/kingdoms/product/README.md', $matrix);
        self::assertFileExists($this->root().'/docs/domains/kingdoms/product/README.md');
    }

    public function test_phase_five_and_six_exit_reports_retain_recovered_immutable_identity(): void
    {
        $phaseFive = file_get_contents($this->root().'/docs/product/phase-5-exit-report.md');
        self::assertIsString($phaseFive);

        foreach ([
            'c30aaab0ee3b03c65f27042a2700540bdebbf9c4',
            '31219686800',
            '31219686802',
            '31219686960',
        ] as $identity) {
            self::assertStringContainsString($identity, $phaseFive, sprintf('Phase 5 exit report lost recovered identity %s.', $identity));
        }

        $phaseSix = file_get_contents($this->root().'/docs/product/phase-6-exit-report.md');
        self::assertIsString($phaseSix);

        foreach ([
            'd1969889ffa044cd7690f263ba9ef70c63a425cb',
            '31235514849',
            '31235514858',
            '31235514843',
            '35979623d8231ee56b8fbcb75301e7e0732df0ca',
            '31252682835',
            '31252682836',
            '31252682853',
        ] as $identity) {
            self::assertStringContainsString($identity, $phaseSix, sprintf('Phase 6 exit report lost recovered identity %s.', $identity));
        }
    }

    public function test_domain_index_exposes_testing_standard_matrix_and_all_profiles(): void
    {
        $index = file_get_contents($this->root().'/docs/domains/README.md');
        self::assertIsString($index);
        self::assertStringContainsString('../product/testing-evidence-standard.md', $index);
        self::assertStringContainsString('../product/testing-evidence-coverage-matrix.md', $index);

        foreach ($this->directories($this->root().'/app/Domain') as $domain) {
            $documentationDomain = $this->kebabCase($domain);
            self::assertStringContainsString(
                $documentationDomain.'/testing/README.md',
                $index,
                sprintf('Domain index must expose testing/evidence profile for %s.', $domain),
            );
        }
    }

    private function matrix(): string
    {
        $matrix = file_get_contents($this->root().'/docs/product/testing-evidence-coverage-matrix.md');
        self::assertIsString($matrix);

        return $matrix;
    }

    /** @param list<string> $headings */
    private function assertHeadingsAppearInOrder(string $contents, array $headings, string $path): void
    {
        $lastPosition = null;

        foreach ($headings as $heading) {
            $position = strpos($contents, $heading);
            self::assertNotFalse($position, sprintf('Missing required heading "%s" in %s', $heading, $this->relativePath($path)));

            if ($lastPosition !== null) {
                self::assertGreaterThan($lastPosition, $position, sprintf('Required headings are out of order in %s', $this->relativePath($path)));
            }

            $lastPosition = $position;
        }
    }

    /** @return list<string> */
    private function directories(string $path): array
    {
        $entries = scandir($path);
        self::assertIsArray($entries);

        $directories = array_values(array_filter(
            $entries,
            static fn (string $entry): bool => $entry !== '.' && $entry !== '..' && is_dir($path.'/'.$entry),
        ));
        sort($directories);

        return $directories;
    }

    private function kebabCase(string $value): string
    {
        $kebab = preg_replace('/(?<!^)[A-Z]/', '-$0', $value);
        self::assertIsString($kebab);

        return strtolower($kebab);
    }

    private function relativePath(string $path): string
    {
        return ltrim(str_replace($this->root(), '', $path), '/');
    }

    private function root(): string
    {
        return dirname(__DIR__, 2);
    }
}
