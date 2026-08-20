<?php

declare(strict_types=1);

namespace Tests\v3\Architecture;

use JsonException;
use PHPUnit\Framework\Attributes\Test;
use Tests\v3\TestCase;

final class PwaContractV3Test extends TestCase
{
    /**
     * @throws JsonException
     */
    #[Test]
    public function manifest_and_worker_preserve_the_offline_privacy_boundary(): void
    {
        $manifestPath = public_path('manifest.webmanifest');
        $workerPath = public_path('service-worker.js');

        self::assertFileExists($manifestPath);
        self::assertFileExists($workerPath);
        self::assertFileExists(public_path('offline.html'));
        self::assertFileExists(public_path('images/app-icons/icon-192.png'));
        self::assertFileExists(public_path('images/app-icons/icon-512.png'));

        $manifestContents = file_get_contents($manifestPath);
        self::assertIsString($manifestContents);
        $manifest = json_decode($manifestContents, true, flags: JSON_THROW_ON_ERROR);

        self::assertSame('standalone', $manifest['display'] ?? null);
        self::assertSame('/', $manifest['scope'] ?? null);
        self::assertSame('/dashboard', $manifest['start_url'] ?? null);
        self::assertCount(2, $manifest['icons'] ?? []);

        $worker = file_get_contents($workerPath);
        self::assertIsString($worker);
        self::assertStringContainsString("request.mode === 'navigate'", $worker);
        self::assertStringContainsString("PUBLIC_ASSET_PREFIXES = ['/build/assets/', '/images/']", $worker);
        self::assertStringContainsString("const OFFLINE_FALLBACK = '/offline.html'", $worker);
        self::assertStringNotContainsString("'/dashboard'", $worker);
        self::assertStringNotContainsString("'/api/", $worker);
        self::assertStringNotContainsString('X-Inertia', $worker);
    }
}
