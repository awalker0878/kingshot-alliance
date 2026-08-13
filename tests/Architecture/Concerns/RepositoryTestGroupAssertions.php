<?php

declare(strict_types=1);

namespace Tests\Architecture\Concerns;

trait RepositoryTestGroupAssertions
{
    public function test_test_suite_uses_only_the_implementation_plan_groups(): void
    {
        $expected = ['Architecture', 'Feature', 'Integration', 'Performance', 'TenantIsolation', 'Unit', 'Visual'];

        self::assertSame($expected, $this->directories($this->root().'/tests'));
    }
}
