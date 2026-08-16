<?php

declare(strict_types=1);

namespace Tests\v3\Architecture;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class CapabilityFirstSourceLayoutV3Test extends TestCase
{
    #[Test]
    public function only_the_seven_business_context_roots_exist(): void
    {
        $root = dirname(__DIR__, 3).'/app/Contexts';
        $actual = array_values(array_map('basename', array_filter(glob($root.'/*') ?: [], 'is_dir')));
        sort($actual);
        $expected = ['Accounts', 'Alliance', 'Communications', 'GameWorld', 'Intelligence', 'Operations', 'Platform'];
        sort($expected);
        self::assertSame($expected, $actual);
    }

    #[Test]
    public function context_roots_do_not_expose_technical_layer_buckets(): void
    {
        $forbidden = ['Actions', 'Catalog', 'Contracts', 'Enums', 'Http', 'Jobs', 'Listeners', 'Models', 'Policies', 'Providers', 'Queries', 'Services', 'ValueObjects'];

        foreach (glob(dirname(__DIR__, 3).'/app/Contexts/*', GLOB_ONLYDIR) ?: [] as $context) {
            foreach ($forbidden as $name) {
                self::assertDirectoryDoesNotExist(
                    $context.'/'.$name,
                    basename($context).' exposes root technical bucket '.$name,
                );
            }
        }
    }

    #[Test]
    public function removed_pre_v3_packages_do_not_exist(): void
    {
        $app = dirname(__DIR__, 3).'/app';

        foreach ([
            'Contexts/Alliance/Core',
            'Contexts/Alliance/Policies',
            'Contexts/Operations/EventCore',
            'Contexts/Intelligence/Http',
            'Contexts/Communications/Reminders',
        ] as $relative) {
            self::assertDirectoryDoesNotExist($app.'/'.$relative);
        }
    }

    #[Test]
    public function shared_has_only_the_infrastructure_package(): void
    {
        $root = dirname(__DIR__, 3).'/app/Shared';
        $directories = array_values(array_map('basename', array_filter(glob($root.'/*') ?: [], 'is_dir')));
        sort($directories);

        self::assertSame(['Infrastructure'], $directories);
    }
}
