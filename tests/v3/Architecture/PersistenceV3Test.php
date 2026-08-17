<?php

declare(strict_types=1);

namespace Tests\v3\Architecture;

use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

final class PersistenceV3Test extends TestCase
{
    public function test_v3_persistence_invariants(): void
    {
        $process = new Process([PHP_BINARY, __DIR__.'/verify-persistence.php'], dirname(__DIR__, 3));
        $process->run();

        self::assertSame(0, $process->getExitCode(), $process->getErrorOutput().$process->getOutput());
    }
}
