<?php

declare(strict_types=1);

namespace Tests\Architecture\Concerns;

trait RepositoryDomainDocumentationAssertions
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
            'contributions' => ['event-history-composition.md'],
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
}
