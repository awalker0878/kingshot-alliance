<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;

final class InterfaceDocumentationTest extends TestCase
{
    public function test_every_code_domain_has_a_living_interface_profile(): void
    {
        $codeDomains = $this->directories($this->root().'/app/Domain');

        foreach ($codeDomains as $domain) {
            $documentationDomain = $this->kebabCase($domain);
            $path = $this->root().'/docs/domains/'.$documentationDomain.'/interfaces/README.md';

            self::assertFileExists(
                $path,
                sprintf('Missing domain interface profile: docs/domains/%s/interfaces/README.md', $documentationDomain),
            );

            $contents = file_get_contents($path);
            self::assertIsString($contents);
            self::assertStringContainsString('**Document type:** Living domain interface profile', $contents, $this->relativePath($path));
            self::assertStringContainsString('**Status:** Current', $contents, $this->relativePath($path));
            self::assertStringContainsString('**Owning domain:** '.$domain, $contents, $this->relativePath($path));
            self::assertStringContainsString('**Code owner:** `app/Domain/'.$domain.'`', $contents, $this->relativePath($path));
            self::assertStringContainsString('**Primary boundary:**', $contents, $this->relativePath($path));
            self::assertStringContainsString('**P4 inventory decision:**', $contents, $this->relativePath($path));
            self::assertStringContainsString('../README.md', $contents, $this->relativePath($path));
            self::assertStringContainsString('../../../product/interface-documentation-standard.md', $contents, $this->relativePath($path));
            self::assertStringContainsString('../../../product/interface-coverage-matrix.md', $contents, $this->relativePath($path));

            $this->assertHeadingsAppearInOrder($contents, [
                '## 1. Boundary purpose and ownership',
                '## 2. Surface inventory',
                '## 3. Callers, authorization and tenancy',
                '## 4. Input and validation contracts',
                '## 5. Output and disclosure contracts',
                '## 6. Internal actions, queries and services',
                '## 7. Events, outbox and cross-domain consumers',
                '## 8. Commands, jobs and scheduled work',
                '## 9. Files, imports, exports and external dependencies',
                '## 10. Failure, idempotency, versioning and compatibility',
                '## 11. Explicit non-capabilities',
                '## 12. Focused contracts, evidence and related documentation',
            ], $path);
        }

        self::assertCount(14, $codeDomains, 'The frozen DCP-P4 inventory expects exactly 14 canonical code domains.');
    }

    public function test_required_new_p4_focused_interface_contracts_exist_and_are_indexed(): void
    {
        foreach ($this->requiredNewFocusedContracts() as $domain => $files) {
            $profilePath = $this->root().'/docs/domains/'.$domain.'/interfaces/README.md';
            $profile = file_get_contents($profilePath);
            self::assertIsString($profile);

            foreach ($files as $file) {
                $path = $this->root().'/docs/domains/'.$domain.'/interfaces/'.$file;
                self::assertFileExists(
                    $path,
                    sprintf('Missing DCP-P4 focused interface contract: docs/domains/%s/interfaces/%s', $domain, $file),
                );
                self::assertStringContainsString(
                    $file,
                    $profile,
                    sprintf('Domain interface profile must index focused contract: docs/domains/%s/interfaces/%s', $domain, $file),
                );
            }
        }
    }

    public function test_new_p4_focused_interface_contracts_follow_the_interface_standard(): void
    {
        foreach ($this->requiredNewFocusedContracts() as $domain => $files) {
            foreach ($files as $file) {
                $path = $this->root().'/docs/domains/'.$domain.'/interfaces/'.$file;
                $contents = file_get_contents($path);

                self::assertIsString($contents);
                self::assertStringContainsString('**Document type:** Living focused interface contract', $contents, $this->relativePath($path));
                self::assertStringContainsString('**Status:** Current', $contents, $this->relativePath($path));
                self::assertStringContainsString('**Owning domain:**', $contents, $this->relativePath($path));
                self::assertStringContainsString('**Capability:**', $contents, $this->relativePath($path));
                self::assertStringContainsString('**Code owner:** `app/Domain/', $contents, $this->relativePath($path));

                $this->assertHeadingsAppearInOrder($contents, [
                    '## 1. Contract scope and owner',
                    '## 2. Entry points and caller classes',
                    '## 3. Authorization, tenancy and rate limits',
                    '## 4. Request and input format',
                    '## 5. Response and output format',
                    '## 6. State changes, events and asynchronous behavior',
                    '## 7. Failure, idempotency and retry',
                    '## 8. Versioning and compatibility',
                    '## 9. Security, privacy and operational constraints',
                    '## 10. Tests, non-capabilities and related documentation',
                ], $path);
            }
        }
    }

    public function test_p4_profiles_index_reused_accepted_capability_contracts(): void
    {
        foreach ($this->reusedCapabilityContracts() as $domain => $files) {
            $profilePath = $this->root().'/docs/domains/'.$domain.'/interfaces/README.md';
            $profile = file_get_contents($profilePath);
            self::assertIsString($profile);

            foreach ($files as $file) {
                self::assertFileExists(
                    $this->root().'/docs/domains/'.$domain.'/'.$file,
                    sprintf('Missing accepted capability contract reused by P4: docs/domains/%s/%s', $domain, $file),
                );
                self::assertStringContainsString(
                    '../'.$file,
                    $profile,
                    sprintf('Interface profile must index accepted P4 capability contract: docs/domains/%s/%s', $domain, $file),
                );
            }
        }
    }

    public function test_every_executable_route_file_is_present_in_the_frozen_p4_inventory(): void
    {
        $matrixPath = $this->root().'/docs/product/interface-coverage-matrix.md';
        $matrix = file_get_contents($matrixPath);
        self::assertIsString($matrix);

        $entries = scandir($this->root().'/routes');
        self::assertIsArray($entries);

        $routeFiles = array_values(array_filter(
            $entries,
            static fn (string $entry): bool => str_ends_with($entry, '.php') && is_file(dirname(__DIR__, 2).'/routes/'.$entry),
        ));
        sort($routeFiles);

        foreach ($routeFiles as $file) {
            self::assertStringContainsString(
                'routes/'.$file,
                $matrix,
                sprintf('Frozen P4 inventory must cover executable route file: routes/%s', $file),
            );
        }

        self::assertNotEmpty($routeFiles, 'Expected executable PHP route files in routes/.');
        self::assertStringContainsString('bootstrap/app.php', $matrix);
        self::assertStringContainsString('/health/ready', $matrix);
    }

    public function test_domain_index_exposes_standard_matrix_and_all_interface_profiles(): void
    {
        $index = file_get_contents($this->root().'/docs/domains/README.md');
        self::assertIsString($index);
        self::assertStringContainsString('../product/interface-documentation-standard.md', $index);
        self::assertStringContainsString('../product/interface-coverage-matrix.md', $index);

        foreach ($this->directories($this->root().'/app/Domain') as $domain) {
            $documentationDomain = $this->kebabCase($domain);
            self::assertStringContainsString(
                $documentationDomain.'/interfaces/README.md',
                $index,
                sprintf('Domain index must expose interface profile for %s.', $domain),
            );
        }
    }

    /** @return array<string, list<string>> */
    private function requiredNewFocusedContracts(): array
    {
        return [
            'contributions' => ['report-exports.md'],
            'events' => ['calendar-exports.md'],
        ];
    }

    /** @return array<string, list<string>> */
    private function reusedCapabilityContracts(): array
    {
        return [
            'content' => ['media.md'],
            'contributions' => ['event-history-composition.md'],
            'events' => ['registration-and-attendance.md'],
            'identity' => ['mfa-and-recovery.md'],
            'integrations' => ['api.md', 'webhooks.md'],
            'kingdoms' => ['csv-migration.md'],
            'memberships' => ['invitations.md'],
            'platform' => ['lifecycle-and-retention.md', 'transactional-outbox.md'],
            'recruitment' => ['application-intake.md'],
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
