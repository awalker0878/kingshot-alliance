<?php

declare(strict_types=1);

namespace Tests\V3\Architecture;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;

final class NamespaceLocationV3Test extends TestCase
{
    #[Test]
    public function app_php_files_do_not_reference_removed_architecture_namespaces(): void
    {
        $forbidden = [
            'App\\Contexts\\Accounts\\Models\\','App\\Contexts\\Accounts\\Actions\\','App\\Contexts\\Accounts\\Http\\','App\\Contexts\\Accounts\\Services\\',
            'App\\Contexts\\GameWorld\\Models\\','App\\Contexts\\GameWorld\\Actions\\','App\\Contexts\\GameWorld\\Enums\\','App\\Contexts\\GameWorld\\Queries\\','App\\Contexts\\GameWorld\\Services\\','App\\Contexts\\GameWorld\\Http\\',
            'App\\Contexts\\Alliance\\Core\\','App\\Contexts\\Alliance\\Policies\\','App\\Contexts\\Operations\\EventCore\\','App\\Contexts\\Intelligence\\Http\\','App\\Contexts\\Communications\\Reminders\\',
            'App\\Contexts\\Platform\\Access\\','App\\Contexts\\Platform\\Actions\\','App\\Contexts\\Platform\\Models\\','App\\Contexts\\Platform\\Services\\','App\\Contexts\\Platform\\Queries\\','App\\Contexts\\Platform\\Http\\','App\\Contexts\\Platform\\Providers\\',
            'App\\Shared\\Access\\','App\\Shared\\Http\\','App\\Shared\\Providers\\',
        ];
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(dirname(__DIR__, 3).'/app'));
        foreach ($iterator as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') continue;
            $contents = file_get_contents($file->getPathname());
            self::assertIsString($contents);
            foreach ($forbidden as $namespace) {
                self::assertStringNotContainsString($namespace, $contents, $file->getPathname().' references '.$namespace);
            }
        }
    }
}
