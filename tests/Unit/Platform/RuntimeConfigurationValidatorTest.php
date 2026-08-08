<?php

declare(strict_types=1);

namespace Tests\Unit\Platform;

use App\Domain\Platform\Services\RuntimeConfigurationValidator;
use Tests\TestCase;

final class RuntimeConfigurationValidatorTest extends TestCase
{
    public function test_hosted_horizon_accepts_phase_six_partitioned_supervisors(): void
    {
        config()->set('horizon.environments.staging', [
            'core' => ['maxProcesses' => 3],
            'integrations' => ['maxProcesses' => 2],
            'maintenance' => ['maxProcesses' => 1],
        ]);

        $errors = app(RuntimeConfigurationValidator::class)->errors('staging');
        $horizonErrors = array_values(array_filter(
            $errors,
            static fn (string $error): bool => str_starts_with($error, 'Hosted Horizon'),
        ));

        self::assertSame([], $horizonErrors);
    }

    public function test_hosted_horizon_rejects_invalid_partition_worker_count(): void
    {
        config()->set('horizon.environments.staging', [
            'core' => ['maxProcesses' => 0],
            'integrations' => ['maxProcesses' => 2],
            'maintenance' => ['maxProcesses' => 1],
        ]);

        $errors = app(RuntimeConfigurationValidator::class)->errors('staging');

        self::assertContains(
            'Hosted Horizon supervisor [core] must run between 1 and 64 worker processes.',
            $errors,
        );
    }
}
