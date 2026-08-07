<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;

final class DomainBoundaryTest extends TestCase
{
    public function test_alliance_model_does_not_own_content_relationships(): void
    {
        $source = file_get_contents($this->root().'/app/Domain/Alliances/Models/Alliance.php');
        self::assertIsString($source);
        self::assertStringNotContainsString('App\\Domain\\Content\\Models', $source);
    }

    public function test_recruitment_does_not_import_membership_invitation_persistence(): void
    {
        foreach ($this->phpFiles($this->root().'/app/Domain/Recruitment') as $file) {
            $source = file_get_contents($file);
            self::assertIsString($source);
            self::assertStringNotContainsString('App\\Domain\\Memberships\\Models\\Invitation', $source, $file);
        }
    }

    public function test_feature_domains_use_platform_outbox_recorder_instead_of_duplicate_writers(): void
    {
        foreach (['ContentOutbox.php', 'EventOutbox.php', 'RecruitmentOutbox.php'] as $legacy) {
            $matches = glob($this->root().'/app/Domain/*/Services/'.$legacy) ?: [];
            self::assertSame([], $matches, $legacy.' must not be duplicated in a feature domain.');
        }

        self::assertFileExists($this->root().'/app/Domain/Platform/Services/OutboxRecorder.php');
    }

    /** @return list<string> */
    private function phpFiles(string $directory): array
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($directory));

        foreach ($iterator as $file) {
            if ($file instanceof \SplFileInfo && $file->isFile() && $file->getExtension() === 'php') {
                $files[] = $file->getPathname();
            }
        }

        return $files;
    }

    private function root(): string
    {
        return dirname(__DIR__, 2);
    }
}
