<?php

declare(strict_types=1);

namespace Tests\v3\Architecture;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

final class FinalSourceCertificationV3Test extends TestCase
{
    public function test_non_visual_v3_source_certification(): void
    {
        $process = new Process([PHP_BINARY, __DIR__.'/verify-final-source.php'], dirname(__DIR__, 3));
        $process->run();

        self::assertSame(0, $process->getExitCode(), $process->getErrorOutput().$process->getOutput());
    }
}
