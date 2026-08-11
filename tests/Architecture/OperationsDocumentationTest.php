<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;

final class OperationsDocumentationTest extends TestCase
{
    public function test_every_code_domain_has_a_living_operations_profile(): void
    {
        $codeDomains = $this->directories($this->root().'/app/Domain');

        foreach ($codeDomains as $domain) {
            $documentationDomain = $this->kebabCase($domain);
            $path = $this->root().'/docs/domains/'.$documentationDomain.'/operations/README.md';

            self::assertFileExists($path, sprintf('Missing domain operations profile: docs/domains/%s/operations/README.md', $documentationDomain));

            $contents = file_get_contents($path);
            self::assertIsString($contents);
            self::assertStringContainsString('**Document type:** Living domain operations profile', $contents, $this->relativePath($path));
            self::assertStringContainsString('**Status:** Current', $contents, $this->relativePath($path));
            self::assertStringContainsString('**Owning domain:** '.$domain, $contents, $this->relativePath($path));
            self::assertStringContainsString('**Code owner:** `app/Domain/'.$domain.'`', $contents, $this->relativePath($path));
            self::assertStringContainsString('**Primary operational boundary:**', $contents, $this->relativePath($path));
            self::assertStringContainsString('../README.md', $contents, $this->relativePath($path));
            self::assertStringContainsString('../../../operations/README.md', $contents, $this->relativePath($path));

            $this->assertHeadingsAppearInOrder($contents, [
                '## 1. Operational purpose and runtime shape',
                '## 2. Persistent state and ownership',
                '## 3. Configuration and runtime dependencies',
                '## 4. Normal flow and background processing',
                '## 5. Health, observability and diagnostics',
                '## 6. Failure modes and diagnosis',
                '## 7. Recovery, replay and reconciliation',
                '## 8. Backup, restore, migration and rollback',
                '## 9. Capacity, query and performance boundaries',
                '## 10. External-service degradation',
                '## 11. Safe operator actions and stop conditions',
                '## 12. Evidence, focused runbooks and related documentation',
            ], $path);
        }

        self::assertCount(14, $codeDomains, 'The frozen DCP-P3 inventory expects exactly 14 canonical code domains.');
    }

    public function test_required_dcp_p3_focused_operations_runbooks_exist_and_are_indexed(): void
    {
        foreach ($this->requiredFocusedRunbooks() as $domain => $files) {
            $profilePath = $this->root().'/docs/domains/'.$domain.'/operations/README.md';
            $profile = file_get_contents($profilePath);
            self::assertIsString($profile);

            foreach ($files as $file) {
                $path = $this->root().'/docs/domains/'.$domain.'/operations/'.$file;
                self::assertFileExists($path, sprintf('Missing DCP-P3 focused operations runbook: docs/domains/%s/operations/%s', $domain, $file));
                self::assertStringContainsString($file, $profile, sprintf('Domain operations profile must index focused runbook: docs/domains/%s/operations/%s', $domain, $file));
            }
        }
    }

    public function test_new_dcp_p3_focused_runbooks_follow_the_operations_standard(): void
    {
        foreach ($this->requiredFocusedRunbooks() as $domain => $files) {
            foreach ($files as $file) {
                $path = $this->root().'/docs/domains/'.$domain.'/operations/'.$file;
                $contents = file_get_contents($path);

                self::assertIsString($contents);
                self::assertStringContainsString('**Document type:** Living capability operations runbook', $contents, $this->relativePath($path));
                self::assertStringContainsString('**Status:** Current', $contents, $this->relativePath($path));
                self::assertStringContainsString('**Owning domain:**', $contents, $this->relativePath($path));
                self::assertStringContainsString('**Capability:**', $contents, $this->relativePath($path));
                self::assertStringContainsString('**Code owner:** `app/Domain/', $contents, $this->relativePath($path));

                $this->assertHeadingsAppearInOrder($contents, [
                    '## 1. Scope, prerequisites and safety boundary',
                    '## 2. Runtime and persistent state',
                    '## 3. Healthy operating flow',
                    '## 4. Signals and diagnostics',
                    '## 5. Failure modes and triage',
                    '## 6. Recovery, replay and reconciliation',
                    '## 7. Capacity and dependency degradation',
                    '## 8. Backup, migration and rollback',
                    '## 9. Stop conditions and prohibited operator actions',
                    '## 10. Validation and evidence to retain',
                ], $path);
            }
        }
    }

    public function test_kingdoms_accepted_operations_guides_are_retained_and_indexed(): void
    {
        $profilePath = $this->root().'/docs/domains/kingdoms/operations/README.md';
        $profile = file_get_contents($profilePath);
        self::assertIsString($profile);

        foreach ([
            'kingdoms-roster-intelligence.md',
            'kingdoms-transfer-planning.md',
            'kingdoms-alliance-intelligence.md',
        ] as $file) {
            self::assertFileExists($this->root().'/docs/domains/kingdoms/operations/'.$file);
            self::assertStringContainsString($file, $profile, sprintf('Kingdoms operations profile must retain accepted guide: %s', $file));
        }
    }

    public function test_new_domain_operations_runbooks_are_not_misplaced_under_shared_operations(): void
    {
        foreach ($this->requiredFocusedRunbooks() as $files) {
            foreach ($files as $file) {
                self::assertFileDoesNotExist(
                    $this->root().'/docs/operations/'.$file,
                    sprintf('Domain-specific living operations runbook belongs under its owning domain: %s', $file),
                );
            }
        }
    }

    public function test_p3_standard_and_frozen_inventory_are_indexed_by_shared_operations(): void
    {
        $index = file_get_contents($this->root().'/docs/operations/README.md');
        self::assertIsString($index);
        self::assertStringContainsString('../product/operations-documentation-standard.md', $index);
        self::assertStringContainsString('../product/operations-coverage-matrix.md', $index);

        foreach ($this->directories($this->root().'/app/Domain') as $domain) {
            $documentationDomain = $this->kebabCase($domain);
            self::assertStringContainsString('../domains/'.$documentationDomain.'/operations/README.md', $index);
        }
    }

    /** @return array<string, list<string>> */
    private function requiredFocusedRunbooks(): array
    {
        return [
            'content' => ['scheduled-publishing-and-media.md'],
            'integrations' => ['webhook-delivery.md'],
            'notifications' => ['scheduled-delivery.md'],
            'platform' => ['lifecycle-retention.md', 'transactional-outbox.md'],
            'recruitment' => ['retention-and-anonymization.md'],
        ];
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
