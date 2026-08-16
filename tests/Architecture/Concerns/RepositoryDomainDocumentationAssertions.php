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

    public function test_historical_and_deferred_domain_documentation_inventory_is_preserved_until_p10(): void
    {
        $documentationDomains = $this->directories($this->root().'/docs/domains');

        self::assertSame($this->domainDocumentationInventory(), $documentationDomains);
        self::assertDirectoryDoesNotExist($this->root().'/app/Domain');

        foreach ($documentationDomains as $domain) {
            self::assertFileExists(
                $this->root().'/docs/domains/'.$domain.'/README.md',
                sprintf('Missing domain documentation index: docs/domains/%s/README.md', $domain),
            );
        }
    }

    public function test_deleted_v1_domain_runtime_is_not_required_for_historical_documentation(): void
    {
        self::assertDirectoryDoesNotExist($this->root().'/app/Domain');

        foreach ($this->historicalDomainDocumentation() as $domain) {
            self::assertFileExists($this->root().'/docs/domains/'.$domain.'/README.md');
        }
    }

    public function test_historical_domain_readmes_remain_indexed_until_p10_rewrites_them(): void
    {
        $index = file_get_contents($this->root().'/docs/domains/README.md');
        self::assertIsString($index);

        foreach ($this->historicalDomainDocumentation() as $domain) {
            $path = $this->root().'/docs/domains/'.$domain.'/README.md';
            $contents = file_get_contents($path);

            self::assertIsString($contents);
            self::assertStringContainsString('[← Domain documentation](../README.md)', $contents, $this->relativePath($path));
            self::assertStringContainsString($domain.'/', strtolower($index), sprintf('Domain index must retain %s historical evidence.', $domain));
        }
    }

    public function test_explicitly_living_capability_documents_follow_the_domain_contract_standard(): void
    {
        $validated = 0;

        foreach ($this->historicalDomainDocumentation() as $documentationDomain) {
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

                if (! str_contains($contents, '**Document type:** Living capability contract')) {
                    continue;
                }

                $validated++;
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

        self::assertGreaterThan(0, $validated, 'Expected at least one explicitly living capability contract.');
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
                    sprintf('Missing DCP-P1 capability evidence: docs/domains/%s/%s', $domain, $file),
                );
            }
        }
    }

    /** @return list<string> */
    private function domainDocumentationInventory(): array
    {
        $domains = [
            ...$this->historicalDomainDocumentation(),
            ...$this->deferredDomainDocumentation(),
        ];
        sort($domains);

        return $domains;
    }

    /** @return list<string> */
    private function deferredDomainDocumentation(): array
    {
        return ['king-perks'];
    }

    /** @return list<string> */
    private function historicalDomainDocumentation(): array
    {
        return [
            'alliances',
            'audit',
            'authorization',
            'content',
            'contributions',
            'events',
            'identity',
            'integrations',
            'kingdoms',
            'memberships',
            'notifications',
            'platform',
            'rallies',
            'recruitment',
        ];
    }
}
