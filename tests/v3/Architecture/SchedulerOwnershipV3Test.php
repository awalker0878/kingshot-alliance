<?php

declare(strict_types=1);

namespace Tests\v3\Architecture;

use Illuminate\Console\Scheduling\CallbackEvent;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Artisan;
use Symfony\Component\Console\Input\StringInput;
use Tests\v3\TestCase;

final class SchedulerOwnershipV3Test extends TestCase
{
    public function test_booted_schedule_has_one_coordinated_command_per_workload(): void
    {
        $commands = Artisan::all();
        $events = app(Schedule::class)->events();
        self::assertNotEmpty($events);
        $workloads = [];
        $mutexes = [];

        foreach ($events as $event) {
            self::assertNotInstanceOf(CallbackEvent::class, $event, 'Schedule owner commands instead of parallel callbacks.');
            self::assertIsString($event->command);
            self::assertSame(1, preg_match('/\b([a-z][a-z0-9-]*:[a-z][a-z0-9-]*)\b/', $event->command, $matches));
            $name = $matches[1];
            self::assertArrayHasKey($name, $commands, 'Scheduled command must be registered: '.$name);
            $position = strpos($event->command, $name);
            self::assertIsInt($position);
            $input = new StringInput(substr($event->command, $position + strlen($name)));
            $input->bind($commands[$name]->getDefinition());
            $input->validate();

            $group = '';
            if ($name === 'notifications:queue-officer-briefs') {
                self::assertSame(1, preg_match('/--group=(daily|event)\b/', $event->command, $groups));
                $group = ':'.$groups[1];
            }

            $workload = $name.$group;
            self::assertArrayNotHasKey($workload, $workloads, 'Duplicate scheduled workload: '.$workload);
            self::assertArrayNotHasKey($event->mutexName(), $mutexes, 'Independent workloads must not share a mutex.');
            self::assertTrue($event->onOneServer, $workload.' needs distributed coordination.');
            self::assertTrue($event->withoutOverlapping, $workload.' needs overlap protection.');
            $workloads[$workload] = $event;
            $mutexes[$event->mutexName()] = true;
        }

        foreach ([
            'events:queue-reminders' => ['* * * * *', '--limit=100'],
            'notifications:deliver' => ['* * * * *', '--limit=100'],
            'gift-codes:reconcile-sources' => ['*/15 * * * *', '--limit=25'],
            'gift-codes:backfill-sources' => ['0 * * * *', '--limit=5'],
            'king-perks:queue-reminders' => ['* * * * *', '--limit=100'],
            'gift-codes:queue-personal-reminders' => ['* * * * *', '--limit=100'],
            'gift-codes:queue-workspace-notifications' => ['*/15 * * * *', '--limit=100'],
            'gift-codes:source-operational-alerts' => ['*/5 * * * *', '--limit=100'],
            'gift-codes:rebuild-contributor-projections' => ['0 * * * *', '--limit=100'],
            'gift-codes:rebuild-acquisition-intelligence' => ['0 * * * *', '--cluster-limit=500 --source-limit=100'],
            'kingdom-governance:expire-delegations' => ['0 * * * *', '--limit=250'],
            'evidence:enforce-retention' => ['20 3 * * *', '--limit=250'],
            'auth:clear-resets' => ['0 * * * *', 'auth:clear-resets'],
        ] as $workload => [$expression, $options]) {
            self::assertArrayHasKey($workload, $workloads, 'Required scheduled workload is missing: '.$workload);
            self::assertSame($expression, $workloads[$workload]->expression, $workload);
            self::assertStringContainsString($options, (string) $workloads[$workload]->command, $workload);
        }
    }

    public function test_bootstrap_and_providers_do_not_register_a_second_schedule(): void
    {
        $paths = [base_path('bootstrap/app.php')];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(app_path()));
        foreach ($iterator as $file) {
            if ($file->isFile() && str_ends_with($file->getFilename(), 'ServiceProvider.php')) {
                $paths[] = $file->getPathname();
            }
        }

        foreach ($paths as $path) {
            $source = file_get_contents($path);
            self::assertIsString($source);
            self::assertStringNotContainsString('withSchedule(', $source, $path);
            self::assertStringNotContainsString('Illuminate\\Console\\Scheduling\\Schedule', $source, $path);
            self::assertStringNotContainsString('Illuminate\\Support\\Facades\\Schedule', $source, $path);
        }
    }
}
