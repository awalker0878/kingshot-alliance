<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;

final class DocumentationMaintenanceTest extends TestCase
{
    public function test_all_specialized_dcp_standards_exist_are_current_and_are_indexed_by_actual_filename(): void
    {
        $standards = [
            'domain-contract-standard.md',
            'security-documentation-standard.md',
            'operations-documentation-standard.md',
            'interface-documentation-standard.md',
            'testing-evidence-standard.md',
            'architecture-governance-standard.md',
            'documentation-maintenance-standard.md',
        ];

        $plan = $this->read('docs/product/documentation-program-plan.md');
        $product = $this->read('docs/product/README.md');

        foreach ($standards as $standard) {
            $path = 'docs/product/'.$standard;
            self::assertFileExists($this->root().'/'.$path, $path);
            self::assertStringContainsString('**Status:** Current', $this->read($path), $path);
            self::assertStringContainsString($standard, $plan, $standard.' missing from DCP standards catalog.');
            self::assertStringContainsString($standard, $product, $standard.' missing from product navigation.');
        }

        self::assertStringNotContainsString('testing-evidence-documentation-standard.md', $plan);
    }

    public function test_every_code_domain_keeps_canonical_contract_and_all_living_profile_families(): void
    {
        foreach ($this->canonicalDomains() as $domain) {
            $slug = $this->kebab($domain);
            $codeReadme = 'app/Domain/'.$domain.'/README.md';
            $docsRoot = 'docs/domains/'.$slug;

            self::assertFileExists($this->root().'/'.$codeReadme);
            self::assertFileExists($this->root().'/'.$docsRoot.'/README.md');

            foreach (['security', 'operations', 'interfaces', 'testing'] as $profile) {
                self::assertFileExists($this->root().'/'.$docsRoot.'/'.$profile.'/README.md', $domain.' '.$profile);
            }

            $source = $this->read($codeReadme);
            self::assertStringContainsString('../../../docs/domains/'.$slug.'/README.md', $source, $codeReadme);
        }
    }

    public function test_final_program_artifacts_and_navigation_are_present(): void
    {
        foreach ([
            'docs/product/documentation-maintenance-standard.md',
            'docs/product/documentation-maintenance-coverage-matrix.md',
            'docs/product/documentation-completion-program-exit-report.md',
            'docs/product/documentation-program-status.md',
            'docs/product/definition-of-done.md',
        ] as $path) {
            self::assertFileExists($this->root().'/'.$path, $path);
        }

        foreach (['docs/README.md', 'docs/product/README.md', 'docs/product/definition-of-done.md'] as $path) {
            self::assertStringContainsString('documentation-maintenance-standard.md', $this->read($path), $path);
        }

        $status = $this->read('docs/product/documentation-program-status.md');
        self::assertStringContainsString('DCP-P7', $status);
        self::assertStringContainsString('no `dcp-p8`', strtolower($status));
    }

    public function test_prior_dcp_phase_governance_remains_discoverable(): void
    {
        $required = [
            ['domain-contract-standard.md', 'domain-coverage-matrix.md', 'domain-contract-completeness-exit-report.md'],
            ['security-documentation-standard.md', 'security-coverage-matrix.md', 'security-completeness-exit-report.md'],
            ['operations-documentation-standard.md', 'operations-coverage-matrix.md', 'operations-completeness-exit-report.md'],
            ['interface-documentation-standard.md', 'interface-coverage-matrix.md', 'interface-completeness-exit-report.md'],
            ['testing-evidence-standard.md', 'testing-evidence-coverage-matrix.md', 'testing-evidence-completeness-exit-report.md'],
            ['architecture-governance-standard.md', 'architecture-governance-coverage-matrix.md', 'architecture-governance-completeness-exit-report.md'],
            ['documentation-maintenance-standard.md', 'documentation-maintenance-coverage-matrix.md', 'documentation-completion-program-exit-report.md'],
        ];

        foreach ($required as $phase) {
            foreach ($phase as $file) {
                self::assertFileExists($this->root().'/docs/product/'.$file, $file);
            }
        }
    }

    public function test_maintenance_standard_encodes_change_driven_non_brittle_governance(): void
    {
        $standard = $this->read('docs/product/documentation-maintenance-standard.md');

        foreach ([
            'Documentation changes are **impact-driven**, not file-count-driven.',
            '## 3. Change classification and required documentation',
            '## 8. Evidence lifecycle',
            '## 9. Status vocabulary maintenance',
            '## 11. Review and archival lifecycle',
            '## 12. CI automation principles',
            '## 13. Final DCP completeness protection',
            'harmless refactors',
            'raw dependency counts',
            'historical test counts',
        ] as $contract) {
            self::assertStringContainsString($contract, $standard, $contract);
        }
    }

    public function test_program_completion_never_implies_real_production_approval(): void
    {
        $status = $this->read('docs/product/documentation-program-status.md');
        $exit = $this->read('docs/product/documentation-completion-program-exit-report.md');
        $approval = $this->read('docs/product/production-launch-approval.md');

        self::assertStringContainsString('real production', strtolower($status));
        self::assertStringContainsString('real production', strtolower($exit));
        self::assertStringContainsString('not yet approved', strtolower($approval));
    }

    /** @return list<string> */
    private function canonicalDomains(): array
    {
        $directories = glob($this->root().'/app/Domain/*', GLOB_ONLYDIR) ?: [];
        $domains = array_map(static fn (string $path): string => basename($path), $directories);
        sort($domains);

        return $domains;
    }

    private function kebab(string $name): string
    {
        return strtolower((string) preg_replace('/(?<!^)[A-Z]/', '-$0', $name));
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
