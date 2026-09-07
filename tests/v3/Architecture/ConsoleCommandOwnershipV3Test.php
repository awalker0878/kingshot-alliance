<?php

declare(strict_types=1);

namespace Tests\v3\Architecture;

use Illuminate\Console\Command;
use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;

final class ConsoleCommandOwnershipV3Test extends TestCase
{
    #[Test]
    public function console_routes_only_define_explicit_application_wide_closure_commands(): void
    {
        $path = dirname(__DIR__, 3).'/routes/console.php';
        $source = file_get_contents($path);
        self::assertIsString($source);

        preg_match_all(
            "/Artisan::command\\(\\s*['\"]([^'\"]+)['\"]/",
            $source,
            $matches,
        );

        self::assertSame([
            'app:config-check',
            'app:launch-check {--json}',
        ], $matches[1] ?? []);

        self::assertStringNotContainsString('use App\\Contexts\\', $source);
    }

    #[Test]
    public function capability_console_commands_are_class_based_and_registered_by_their_capability_provider(): void
    {
        $root = dirname(__DIR__, 3).'/app/Contexts';
        $commands = glob($root.'/*/*/Console/Commands/*Command.php') ?: [];
        $commands = array_merge(
            $commands,
            glob($root.'/*/*/*/Console/Commands/*Command.php') ?: [],
        );

        self::assertNotEmpty($commands);

        foreach ($commands as $commandPath) {
            $relative = substr($commandPath, strlen(dirname(__DIR__, 3).'/app/'));
            self::assertIsString($relative);

            $class = 'App\\'.str_replace(['/', '.php'], ['\\', ''], $relative);
            self::assertTrue(
                is_subclass_of($class, Command::class),
                $class.' must extend '.Command::class,
            );

            $capabilityRoot = dirname(dirname(dirname($commandPath)));
            $providerFiles = glob($capabilityRoot.'/Providers/*ServiceProvider.php') ?: [];
            self::assertNotEmpty(
                $providerFiles,
                $class.' must be owned by a capability service provider.',
            );

            $registered = false;
            foreach ($providerFiles as $providerFile) {
                $providerSource = file_get_contents($providerFile);
                $separator = strrpos($class, '\\');
                $shortClass = $separator === false ? $class : substr($class, $separator + 1);
                if (is_string($providerSource) && str_contains($providerSource, $shortClass.'::class')) {
                    $registered = true;
                    break;
                }
            }

            self::assertTrue(
                $registered,
                $class.' must be registered by a provider inside '.$capabilityRoot.'/Providers.',
            );
        }
    }
}
