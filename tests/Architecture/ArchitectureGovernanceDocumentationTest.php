<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;

final class ArchitectureGovernanceDocumentationTest extends TestCase
{
    public function test_required_p6_architecture_governance_artifacts_exist(): void
    {
        foreach ([
            'docs/product/architecture-governance-standard.md',
            'docs/product/architecture-governance-coverage-matrix.md',
            'docs/product/cross-domain-dependency-map.md',
            'docs/product/glossary.md',
            'docs/product/current-capability-matrix.md',
            'docs/product/repository-structure-audit.md',
            'docs/product/domain-boundary-audit.md',
            'docs/adr/README.md',
        ] as $path) {
            self::assertFileExists($this->root().'/'.$path, $path);
        }
    }

    public function test_all_numbered_adrs_are_indexed_and_use_allowed_statuses(): void
    {
        $index = $this->read('docs/adr/README.md');
        $allowed = ['Proposed', 'Accepted', 'Superseded', 'Rejected'];
        $files = glob($this->root().'/docs/adr/[0-9][0-9][0-9][0-9]-*.md') ?: [];

        self::assertNotSame([], $files);

        foreach ($files as $file) {
            $source = file_get_contents($file);
            self::assertIsString($source);

            self::assertMatchesRegularExpression('/^- \*\*Status:\*\* (Proposed|Accepted|Superseded|Rejected)$/m', $source, $file);
            self::assertStringContainsString(basename($file), $index, basename($file).' is missing from the ADR index.');

            preg_match('/^- \*\*Status:\*\* ([^\r\n]+)$/m', $source, $matches);
            self::assertContains($matches[1] ?? null, $allowed, $file);
        }
    }

    public function test_adr_template_and_index_define_p6_lifecycle(): void
    {
        $template = $this->read('docs/adr/adr-template.md');
        $index = $this->read('docs/adr/README.md');

        self::assertStringContainsString('- **Status:** Proposed', $template);
        self::assertStringContainsString('Proposed', $index);
        self::assertStringContainsString('Accepted', $index);
        self::assertStringContainsString('Superseded', $index);
        self::assertStringContainsString('Rejected', $index);
        self::assertStringContainsString('Supersession handling', $template);
    }

    public function test_dependency_map_contains_every_canonical_code_domain_once(): void
    {
        $map = $this->read('docs/product/cross-domain-dependency-map.md');
        $domains = $this->canonicalDomains();

        preg_match_all('/^\| \*\*([A-Za-z]+)\*\* \|/m', $map, $matches);
        $documented = $matches[1] ?? [];
        sort($documented);

        self::assertSame($domains, $documented);

        foreach ($domains as $domain) {
            self::assertFileExists($this->root().'/app/Domain/'.$domain.'/README.md');
            self::assertFileExists($this->root().'/docs/domains/'.$this->kebab($domain).'/README.md');
        }
    }

    public function test_glossary_contains_high_risk_shared_terms(): void
    {
        $glossary = $this->read('docs/product/glossary.md');

        foreach ([
            '### Alliance',
            '### Active Alliance',
            '### Platform administrator',
            '### KingdomAlliance',
            '### TrackedKingdomAlliance',
            '### Transactional outbox',
            '### Internal domain/outbox event',
            '### Externally eligible webhook event',
            '### Living contract',
            '### Historical evidence',
            '### Supported contract',
            '### Persistence reach-through',
            '### Repository-controlled production hardening',
            '### Real production launch',
        ] as $term) {
            self::assertStringContainsString($term, $glossary, $term);
        }
    }

    public function test_current_architecture_audits_are_not_migration_candidate_records(): void
    {
        foreach ([
            'docs/product/repository-structure-audit.md',
            'docs/product/domain-boundary-audit.md',
        ] as $path) {
            $source = $this->read($path);
            self::assertStringContainsString('**Status:** Current', $source, $path);
            self::assertStringNotContainsString('**Status:** Current migration audit', $source, $path);
            self::assertStringContainsString('cross-domain dependency map', strtolower($source), $path);
        }
    }

    public function test_shared_indexes_keep_program_ownership_and_domain_navigation_explicit(): void
    {
        $product = $this->read('docs/product/README.md');
        $security = $this->read('docs/security/README.md');
        $operations = $this->read('docs/operations/README.md');

        self::assertStringContainsString('repository-wide product/program governance', $product);
        self::assertStringContainsString('docs/domains/<domain>/', $product);

        self::assertStringContainsString('repository-wide security policy and program evidence', $security);
        self::assertStringContainsString('docs/domains/<domain>/security/', $security);

        self::assertStringContainsString('shared repository/runtime operating model', $operations);
        self::assertStringContainsString('docs/domains/<domain>/operations/', $operations);
    }

    public function test_current_navigation_links_p6_architecture_surfaces(): void
    {
        foreach ([
            'docs/README.md',
            'docs/product/README.md',
            'docs/product/current-capability-matrix.md',
            'docs/adr/README.md',
        ] as $path) {
            $source = $this->read($path);
            self::assertStringContainsString('cross-domain-dependency-map.md', $source, $path);
            self::assertStringContainsString('glossary.md', $source, $path);
        }
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
