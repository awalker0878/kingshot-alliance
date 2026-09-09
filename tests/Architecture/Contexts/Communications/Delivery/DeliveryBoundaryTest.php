<?php

declare(strict_types=1);

namespace Tests\Architecture\Contexts\Communications\Delivery;

use PHPUnit\Framework\Attributes\Test;
use PHPUnit\Framework\TestCase;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use Tests\Support\RepositoryPath;

final class DeliveryBoundaryTest extends TestCase
{
    #[Test]
    public function communications_contains_only_the_delivery_capability(): void
    {
        $root = RepositoryPath::fromRoot('app/Contexts/Communications');
        $directories = array_values(array_map('basename', array_filter(glob($root.'/*') ?: [], 'is_dir')));
        sort($directories);
        self::assertSame(['Delivery'], $directories);
    }

    #[Test]
    public function communications_does_not_encode_source_domain_reminder_semantics(): void
    {
        $root = RepositoryPath::fromRoot('app/Contexts/Communications');
        $iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($root));
        $violations = [];
        $forbidden = [
            'EventReminder',
            'KingPerkReminder',
            'MarkEventReminderSent',
            'MarkKingPerkReminderSent',
            'event.reminder.requested',
            'king_perks.reminder.requested',
            'App\\Contexts\\Operations\\',
        ];

        foreach ($iterator as $file) {
            if (! $file->isFile() || $file->getExtension() !== 'php') {
                continue;
            }
            $contents = file_get_contents($file->getPathname());
            self::assertIsString($contents);
            foreach ($forbidden as $term) {
                if (str_contains($contents, $term)) {
                    $violations[] = str_replace(RepositoryPath::fromRoot(''), '', $file->getPathname()).' ['.$term.']';
                }
            }
        }

        self::assertSame([], $violations, implode("\n", $violations));
    }
}
