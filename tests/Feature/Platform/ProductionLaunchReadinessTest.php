<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use App\Domain\Identity\Models\User;
use App\Domain\Platform\Actions\ManagePlatformAdministrator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ProductionLaunchReadinessTest extends TestCase
{
    use RefreshDatabase;

    public function test_launch_check_fails_without_redundant_platform_administration(): void
    {
        $this->configureProductionRuntime();
        $administrator = User::factory()->create(['two_factor_confirmed_at' => now()]);
        $this->app->make(ManagePlatformAdministrator::class)->grant($administrator);

        $this->artisan('app:launch-check')
            ->expectsOutputToContain('[FAIL] platform_administrator_redundancy')
            ->assertExitCode(1);
    }

    public function test_launch_check_passes_repository_controlled_prerequisites(): void
    {
        $this->configureProductionRuntime();
        $manage = $this->app->make(ManagePlatformAdministrator::class);
        $first = User::factory()->create(['two_factor_confirmed_at' => now()]);
        $second = User::factory()->create(['two_factor_confirmed_at' => now()]);
        $manage->grant($first);
        $manage->grant($second, $first);

        $this->artisan('app:launch-check')
            ->expectsOutputToContain('[PASS] runtime_configuration')
            ->expectsOutputToContain('[PASS] platform_administrator_redundancy')
            ->expectsOutputToContain('[PASS] platform_administrator_mfa')
            ->expectsOutputToContain('[PASS] transactional_outbox_backlog')
            ->expectsOutputToContain('[PASS] failed_jobs')
            ->expectsOutputToContain('[PASS] webhook_delivery_health')
            ->assertExitCode(0);
    }

    private function configureProductionRuntime(): void
    {
        config([
            'app.key' => 'base64:Wb1PHh03m1vXbmyRxlI+Y96TqgK7Vyt8H/lkQ8o1SP0=',
            'app.url' => 'https://alliance.example.test',
            'app.debug' => false,
            'database.default' => 'pgsql',
            'database.connections.pgsql.host' => 'database.example.test',
            'database.connections.pgsql.database' => 'kingshot',
            'database.connections.pgsql.username' => 'kingshot',
            'database.connections.pgsql.password' => 'secret',
            'database.connections.pgsql.sslmode' => 'verify-full',
            'cache.default' => 'redis',
            'queue.default' => 'redis',
            'session.driver' => 'redis',
            'session.encrypt' => true,
            'session.same_site' => 'lax',
            'session.secure' => true,
            'filesystems.default' => 's3',
            'filesystems.disks.s3.bucket' => 'kingshot',
            'content.media_disk' => 's3',
            'pulse.enabled' => false,
            'horizon.environments.production' => [
                'core' => ['maxProcesses' => 8],
                'integrations' => ['maxProcesses' => 4],
                'maintenance' => ['maxProcesses' => 2],
            ],
            'operations.version' => 'v1.0.0',
            'operations.release_sha' => str_repeat('a', 40),
            'operations.trusted_proxies' => '',
            'operations.allow_trust_all_proxies' => false,
            'operations.launch.minimum_platform_administrators' => 2,
            'operations.launch.outbox_grace_minutes' => 15,
            'operations.launch.maximum_overdue_outbox' => 0,
            'operations.launch.maximum_failed_jobs' => 0,
            'operations.launch.webhook_failure_window_minutes' => 60,
            'operations.launch.maximum_recent_webhook_failures' => 25,
        ]);
    }
}
