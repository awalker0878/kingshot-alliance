<?php

declare(strict_types=1);

namespace Tests\Architecture;

use FilesystemIterator;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class RepositoryStructureTest extends TestCase
{
    public function test_documentation_uses_only_the_implementation_plan_groups(): void
    {
        self::assertSame(
            ['adr', 'domains', 'operations', 'product', 'security'],
            $this->directories($this->root().'/docs'),
        );
    }

    public function test_documentation_groups_have_navigation_indexes(): void
    {
        foreach ([
            'docs/README.md',
            'docs/adr/README.md',
            'docs/domains/README.md',
            'docs/operations/README.md',
            'docs/product/README.md',
            'docs/security/README.md',
        ] as $path) {
            self::assertFileExists($this->root().'/'.$path, sprintf('Missing documentation index: %s', $path));
        }
    }

    public function test_domain_documentation_directories_match_canonical_code_domains(): void
    {
        $codeDomains = array_map(
            fn (string $domain): string => $this->kebabCase($domain),
            $this->directories($this->root().'/app/Domain'),
        );
        $documentationDomains = $this->directories($this->root().'/docs/domains');

        sort($codeDomains);

        self::assertSame(
            $codeDomains,
            $documentationDomains,
            'Every app/Domain/<Domain> must have exactly one docs/domains/<domain>/ directory, and every docs domain directory must map to a code domain.',
        );

        foreach ($documentationDomains as $domain) {
            self::assertFileExists(
                $this->root().'/docs/domains/'.$domain.'/README.md',
                sprintf('Missing canonical domain documentation index: docs/domains/%s/README.md', $domain),
            );
        }
    }

    public function test_code_local_domain_readmes_follow_the_domain_contract_standard(): void
    {
        foreach ($this->directories($this->root().'/app/Domain') as $domain) {
            $path = $this->root().'/app/Domain/'.$domain.'/README.md';

            self::assertFileExists($path, sprintf('Missing code-local domain README: app/Domain/%s/README.md', $domain));

            $contents = file_get_contents($path);
            self::assertIsString($contents);
            self::assertStringStartsWith('# '.$domain.' domain', $contents, sprintf('Unexpected domain README title: %s', $this->relativePath($path)));

            $this->assertHeadingsAppearInOrder($contents, [
                '## Purpose',
                '## Owned code',
                '## Public contracts',
                '## Dependencies',
                '## Canonical documentation',
            ], $path);

            $canonicalDocumentation = '../../../docs/domains/'.$this->kebabCase($domain).'/README.md';
            self::assertStringContainsString(
                $canonicalDocumentation,
                $contents,
                sprintf('Code-local domain README must link its canonical documentation: %s', $this->relativePath($path)),
            );
        }
    }

    public function test_canonical_domain_readmes_follow_the_domain_contract_standard(): void
    {
        foreach ($this->directories($this->root().'/app/Domain') as $domain) {
            $documentationDomain = $this->kebabCase($domain);
            $path = $this->root().'/docs/domains/'.$documentationDomain.'/README.md';
            $contents = file_get_contents($path);

            self::assertIsString($contents);
            self::assertStringContainsString('**Document type:** Living domain contract', $contents, $this->relativePath($path));
            self::assertStringContainsString('**Status:**', $contents, $this->relativePath($path));
            self::assertStringContainsString('**Code owner:** `app/Domain/'.$domain.'`', $contents, $this->relativePath($path));
            self::assertStringContainsString('**Primary authorization boundary:**', $contents, $this->relativePath($path));

            $this->assertHeadingsAppearInOrder($contents, [
                '## 1. Purpose and ownership',
                '## 2. Scope',
                '## 3. Domain model',
                '## 4. Core invariants',
                '## 5. Lifecycles and workflows',
                '## 6. Authorization and tenancy',
                '## 7. Cross-domain contracts',
                '## 8. Persistence and data ownership',
                '## 9. Events, outbox and integrations',
                '## 10. HTTP, UI and API surfaces',
                '## 11. Background processing',
                '## 12. Failure, idempotency and concurrency',
                '## 13. Security and privacy',
                '## 14. Observability and operations',
                '## 15. Testing and architecture enforcement',
                '## 16. Explicit non-capabilities',
                '## 17. Capability documents',
                '## 18. Related documentation',
            ], $path);
        }
    }

    public function test_living_capability_documents_follow_the_domain_contract_standard(): void
    {
        foreach ($this->directories($this->root().'/docs/domains') as $documentationDomain) {
            $root = $this->root().'/docs/domains/'.$documentationDomain;
            $entries = scandir($root);

            self::assertIsArray($entries);

            foreach ($entries as $entry) {
                if ($entry === 'README.md' || str_ends_with($entry, '.md') === false || is_file($root.'/'.$entry) === false) {
                    continue;
                }

                $path = $root.'/'.$entry;
                $contents = file_get_contents($path);

                self::assertIsString($contents);
                self::assertStringContainsString('**Document type:** Living capability contract', $contents, $this->relativePath($path));
                self::assertStringContainsString('**Status:**', $contents, $this->relativePath($path));
                self::assertStringContainsString('**Owning domain:**', $contents, $this->relativePath($path));

                $this->assertHeadingsAppearInOrder($contents, [
                    '## 1. Purpose',
                    '## 2. Scope and non-scope',
                    '## 3. Model and state',
                    '## 4. Invariants',
                    '## 5. Workflows',
                    '## 6. Authorization, tenancy and privacy',
                    '## 7. Persistence and query semantics',
                    '## 8. Events',
                    '## 9. Failure, idempotency and concurrency',
                    '## 10. Operations and observability',
                    '## 11. Tests and validation',
                    '## 12. Related documentation',
                ], $path);

                self::assertStringContainsString(
                    'background processing',
                    strtolower($contents),
                    sprintf('Capability contract must address background processing: %s', $this->relativePath($path)),
                );
            }
        }
    }

    public function test_required_dcp_p1_domain_capability_contracts_exist(): void
    {
        $required = [
            'alliances' => ['tenant-context.md'],
            'content' => ['media.md'],
            'contributions' => ['event-reconciliation.md'],
            'events' => ['registration-and-attendance.md'],
            'identity' => ['mfa-and-recovery.md'],
            'integrations' => ['api.md', 'webhooks.md'],
            'kingdoms' => [
                'alliance-intelligence.md',
                'csv-migration.md',
                'intelligence.md',
                'roster.md',
                'snapshots.md',
                'transfer-planning.md',
            ],
            'memberships' => ['invitations.md'],
            'notifications' => ['event-reminders.md', 'scheduled-report-coordination.md'],
            'platform' => ['lifecycle-and-retention.md', 'transactional-outbox.md'],
            'recruitment' => ['application-intake.md'],
        ];

        foreach ($required as $domain => $files) {
            foreach ($files as $file) {
                self::assertFileExists(
                    $this->root().'/docs/domains/'.$domain.'/'.$file,
                    sprintf('Missing DCP-P1 capability contract: docs/domains/%s/%s', $domain, $file),
                );
            }
        }
    }

    public function test_domain_documentation_root_contains_only_the_navigation_index(): void
    {
        $root = $this->root().'/docs/domains';
        $entries = scandir($root);

        self::assertIsArray($entries);

        $markdownFiles = array_values(array_filter(
            $entries,
            static fn (string $entry): bool => $entry !== '.'
                && $entry !== '..'
                && is_file($root.'/'.$entry)
                && pathinfo($entry, PATHINFO_EXTENSION) === 'md',
        ));

        sort($markdownFiles);

        self::assertSame(
            ['README.md'],
            $markdownFiles,
            'docs/domains/ may contain only README.md at its root; living domain and capability documents must live inside the matching domain directory.',
        );
    }

    public function test_kingdoms_domain_specific_evidence_stays_with_the_domain(): void
    {
        foreach (['product', 'security', 'operations'] as $group) {
            $root = $this->root().'/docs/'.$group;
            $entries = scandir($root);

            self::assertIsArray($entries);

            $misplaced = array_values(array_filter(
                $entries,
                static fn (string $entry): bool => str_starts_with($entry, 'kingdoms-')
                    && str_ends_with($entry, '.md')
                    && is_file($root.'/'.$entry),
            ));

            sort($misplaced);

            self::assertSame(
                [],
                $misplaced,
                sprintf('Kingdoms-specific %s documentation belongs under docs/domains/kingdoms/%s/.', $group, $group),
            );
        }

        foreach (['product', 'security', 'operations'] as $group) {
            self::assertFileExists(
                $this->root().'/docs/domains/kingdoms/'.$group.'/README.md',
                sprintf('Missing Kingdoms %s documentation index.', $group),
            );
        }
    }

    public function test_documentation_filenames_are_predictable(): void
    {
        $invalid = [];

        foreach ($this->documentationFiles() as $path) {
            $basename = basename($path);

            if ($basename === 'README.md') {
                continue;
            }

            if (preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*\.md$/', $basename) !== 1) {
                $invalid[] = $this->relativePath($path);
            }
        }

        self::assertSame([], $invalid, 'Documentation filenames must use lowercase kebab-case; README.md is reserved for indexes.');
    }

    public function test_local_markdown_document_links_resolve(): void
    {
        $broken = [];
        $files = [
            $this->root().'/README.md',
            $this->root().'/CONTRIBUTING.md',
            ...$this->documentationFiles(),
        ];

        foreach ($files as $path) {
            $contents = file_get_contents($path);
            self::assertIsString($contents);

            preg_match_all('/\]\((?!https?:\/\/|mailto:)([^)#\s]+\.md)(?:#[^)]+)?\)/', $contents, $matches);

            foreach ($matches[1] ?? [] as $target) {
                if (is_string($target) === false) {
                    continue;
                }

                if (str_contains($target, '<') || str_contains($target, '>') || str_contains($target, '*')) {
                    continue;
                }

                $resolved = dirname($path).'/'.rawurldecode($target);

                if (is_file($resolved) === false) {
                    $broken[] = sprintf('%s -> %s', $this->relativePath($path), $target);
                }
            }
        }

        sort($broken);

        self::assertSame([], $broken, "Broken local Markdown links:\n".implode("\n", $broken));
    }

    public function test_documentation_contains_no_legacy_markdown_filenames(): void
    {
        $legacyNames = [];

        foreach ($this->documentationFiles() as $path) {
            $basename = basename($path);

            if ($basename === 'README.md') {
                continue;
            }

            $stem = substr($basename, 0, -3);
            $legacyNames[] = strtoupper(str_replace('-', '_', $stem)).'.md';
        }

        $legacyNames = array_values(array_unique($legacyNames));
        sort($legacyNames);

        $legacyReferences = [];
        $files = [
            $this->root().'/README.md',
            $this->root().'/CONTRIBUTING.md',
            ...$this->documentationFiles(),
        ];

        foreach ($files as $path) {
            $contents = file_get_contents($path);
            self::assertIsString($contents);

            foreach ($legacyNames as $target) {
                if (str_contains($contents, $target)) {
                    $legacyReferences[] = sprintf('%s -> %s', $this->relativePath($path), $target);
                }
            }
        }

        sort($legacyReferences);

        self::assertSame([], $legacyReferences, "Legacy Markdown filename references:\n".implode("\n", $legacyReferences));
    }

    public function test_test_suite_uses_only_the_implementation_plan_groups(): void
    {
        self::assertSame(
            ['Architecture', 'Feature', 'Integration', 'Performance', 'TenantIsolation', 'Unit'],
            $this->directories($this->root().'/tests'),
        );
    }

    /** @param list<string> $headings */
    private function assertHeadingsAppearInOrder(string $contents, array $headings, string $path): void
    {
        $lastPosition = null;

        foreach ($headings as $heading) {
            $position = strpos($contents, $heading);

            self::assertNotFalse(
                $position,
                sprintf('Missing required heading "%s" in %s', $heading, $this->relativePath($path)),
            );

            if ($lastPosition !== null) {
                self::assertGreaterThan(
                    $lastPosition,
                    $position,
                    sprintf('Required headings are out of order in %s', $this->relativePath($path)),
                );
            }

            $lastPosition = $position;
        }
    }

    /** @return list<string> */
    private function documentationFiles(): array
    {
        $files = [];
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->root().'/docs', FilesystemIterator::SKIP_DOTS),
        );

        foreach ($iterator as $file) {
            if (($file instanceof SplFileInfo) === false || $file->isFile() === false || $file->getExtension() !== 'md') {
                continue;
            }

            $files[] = $file->getPathname();
        }

        sort($files);

        return $files;
    }

    /** @return list<string> */
    private function directories(string $path): array
    {
        $entries = scandir($path);

        self::assertIsArray($entries);

        $directories = array_values(array_filter(
            $entries,
            static fn (string $entry): bool => $entry !== '.'
                && $entry !== '..'
                && is_dir($path.'/'.$entry),
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
