<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

final class ArchitectureV3Test extends TestCase
{
    public function test_v3_architecture_invariants(): void
    {
        $process = new Process([PHP_BINARY, __DIR__.'/verify.php'], dirname(__DIR__, 2));
        $process->run();

        self::assertSame(0, $process->getExitCode(), $process->getErrorOutput().$process->getOutput());
    }
}
