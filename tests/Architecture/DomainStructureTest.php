<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use SplFileInfo;

final class DomainStructureTest extends TestCase
{
    private const DOMAINS = [
        'Alliances',
        'Audit',
        'Authorization',
        'Content',
        'Contributions',
        'Events',
        'Identity',
        'Integrations',
        'Kingdoms',
        'Memberships',
        'Notifications',
        'Platform',
        'Rallies',
        'Recruitment',
    ];

    public function test_implementation_plan_domain_directories_are_present(): void
    {
        foreach (self::DOMAINS as $domain) {
            self::assertDirectoryExists($this->root().'/app/Domain/'.$domain);
        }
    }

    public function test_layer_first_legacy_app_directories_are_absent(): void
    {
        foreach (['Application', 'Http', 'Infrastructure', 'Models', 'Providers'] as $legacy) {
            self::assertDirectoryDoesNotExist($this->root().'/app/'.$legacy);
        }

        self::assertDirectoryDoesNotExist($this->root().'/app/Domain/Shared');
    }

    public function test_all_runtime_php_under_app_is_owned_by_a_canonical_domain(): void
    {
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($this->root().'/app'));

        foreach ($iterator as $file) {
            if (! $file instanceof SplFileInfo || ! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }

            $path = str_replace('\\', '/', $file->getPathname());
            self::assertMatchesRegularExpression(
                '#/app/Domain/('.implode('|', self::DOMAINS).')/#',
                $path,
                'Runtime PHP must be owned by a canonical domain: '.$path,
            );
        }
    }

    public function test_unimplemented_kingdoms_domain_contains_no_runtime_php(): void
    {
        $iterator = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($this->root().'/app/Domain/Kingdoms'),
        );

        foreach ($iterator as $file) {
            if ($file instanceof SplFileInfo && $file->isFile()) {
                self::assertNotSame('php', $file->getExtension(), 'Kingdoms must remain documentation-only until an approved phase.');
            }
        }
    }

    public function test_phase_six_integrations_domain_contains_runtime_php(): void
    {
        self::assertFileExists($this->root().'/app/Domain/Integrations/Models/ApiCredential.php');
        self::assertFileExists($this->root().'/app/Domain/Integrations/Models/WebhookSubscription.php');
        self::assertFileExists($this->root().'/app/Domain/Integrations/Jobs/DeliverWebhookJob.php');
    }

    private function root(): string
    {
        return dirname(__DIR__, 2);
    }
}
