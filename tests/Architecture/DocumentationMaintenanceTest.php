<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;

final class DocumentationMaintenanceTest extends TestCase
{
    public function test_documentation_standard_defines_one_canonical_home_per_kind_of_truth(): void
    {
        $standard = $this->read('docs/governance/documentation-standard.md');

        foreach (['Architecture', 'Codebase', 'Operations', 'Product', 'Governance', 'Reference'] as $area) {
            self::assertStringContainsString($area, $standard);
        }

        self::assertStringContainsString('Every rule should have one authoritative home', $standard);
        self::assertStringContainsString('Git history is the archive', $standard);
        self::assertStringContainsString('Do not recreate top-level `domains/`, `adr/`, `security/`', $standard);
    }

    public function test_change_impact_guide_covers_material_change_classes(): void
    {
        $guide = $this->read('docs/governance/change-impact.md');

        foreach ([
            'New/changed business invariant',
            'Context ownership/dependency changes',
            'Player/User/permission semantics',
            'Transaction/locking behavior',
            'New async side effect/event',
            'New environment/dependency',
            'New user-facing capability',
            'Production status change',
        ] as $change) {
            self::assertStringContainsString($change, $guide);
        }
    }

    public function test_definition_of_done_uses_v2_ownership_and_documentation_rules(): void
    {
        $dod = $this->read('docs/governance/definition-of-done.md');

        foreach (['owning context/capability', 'Workflow', 'ReadModel', 'Shared remains business-neutral', 'obsolete live documentation is removed'] as $rule) {
            self::assertStringContainsString($rule, $dod);
        }
    }

    public function test_live_documentation_contains_no_dcp_or_phase_program_tree(): void
    {
        self::assertDirectoryDoesNotExist($this->root().'/docs/domains');
        self::assertDirectoryDoesNotExist($this->root().'/docs/adr');

        foreach (glob($this->root().'/docs/product/phase-*.md') ?: [] as $file) {
            self::fail('Historical phase documentation must not remain in the live tree: '.$file);
        }

        foreach (glob($this->root().'/docs/product/*documentation-program*.md') ?: [] as $file) {
            self::fail('DCP documentation must not remain in the live tree: '.$file);
        }
    }

    public function test_production_approval_remains_an_explicit_go_no_go_record(): void
    {
        $approval = strtolower($this->read('docs/governance/production-approval.md'));

        self::assertStringContainsString('not yet approved', $approval);
        self::assertStringContainsString('ci success never auto-approves production', $approval);
        self::assertStringContainsString('pending', $approval);
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
