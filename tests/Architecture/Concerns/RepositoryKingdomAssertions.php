<?php

declare(strict_types=1);

namespace Tests\Architecture\Concerns;

trait RepositoryKingdomAssertions
{
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

            self::assertSame([], $misplaced);
        }

        foreach (['product', 'security', 'operations'] as $group) {
            self::assertFileExists($this->root().'/docs/domains/kingdoms/'.$group.'/README.md');
        }
    }
}
