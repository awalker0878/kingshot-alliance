<?php

declare(strict_types=1);

namespace Tests\v3\Contexts\GameWorld\Progression;

use App\Contexts\GameWorld\Progression\Exceptions\NoProgressionDatasetPublished;
use App\Contexts\GameWorld\Progression\Queries\ProgressionDatasetQuery;
use RuntimeException;
use Tests\v3\TestCase;

final class ProgressionDatasetAbsenceV3Test extends TestCase
{
    public function test_latest_uses_a_dedicated_exception_only_when_no_release_is_published(): void
    {
        $directory = base_path('resources/data/progression');
        $hidden = base_path('resources/data/.progression-hidden-'.bin2hex(random_bytes(6)));
        if (! rename($directory, $hidden)) {
            throw new RuntimeException('Unable to isolate progression fixtures for absence test.');
        }

        try {
            $this->expectException(NoProgressionDatasetPublished::class);
            app(ProgressionDatasetQuery::class)->latest();
        } finally {
            if (is_dir($hidden) && ! rename($hidden, $directory)) {
                throw new RuntimeException('Unable to restore progression fixtures after absence test.');
            }
        }
    }
}
