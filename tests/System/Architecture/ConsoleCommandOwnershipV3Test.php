<?php

declare(strict_types=1);

namespace Tests\System\Architecture;

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
    public function application_console_commands_are_class_based_and_registered_by_their_owning_provider(): void
    {
        $app = dirname(__DIR__, 3).'/app';
        $patterns = [
            $app.'/Contexts/*/*/Console/Commands/*Command.php',
            $app.'/Contexts/*/*/*/Console/Commands/*Command.php',
            $app.'/ReadModels/*/Console/Commands/*Command.php',
            $app.'/Workflows/*/Console/Commands/*Command.php',
        ];
        $commands = [];
        foreach ($patterns as $pattern) {
            $commands = array_merge($commands, glob($pattern) ?: []);
        }
        $commands = array_values(array_unique($commands));

        self::assertNotEmpty($commands);

        foreach ($commands as $commandPath) {
            $relative = substr($commandPath, strlen($app.'/'));
            self::assertIsString($relative);

            $class = 'App\\'.str_replace(['/', '.php'], ['\\', ''], $relative);
            self::assertTrue(
                is_subclass_of($class, Command::class),
                $class.' must extend '.Command::class,
            );

            $packageRoot = dirname(dirname(dirname($commandPath)));
            $providerFiles = glob($packageRoot.'/Providers/*ServiceProvider.php') ?: [];
            self::assertNotEmpty(
                $providerFiles,
                $class.' must be owned by a service provider in its application package.',
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
                $class.' must be registered by a provider inside '.$packageRoot.'/Providers.',
            );
        }
    }
}
