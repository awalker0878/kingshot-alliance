<?php

declare(strict_types=1);

use App\ReadModels\ProductionLaunch\ProductionLaunchReadiness;
use App\Shared\Infrastructure\Runtime\Services\RuntimeConfigurationValidator;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('app:config-check', function (RuntimeConfigurationValidator $validator): int {
    $errors = $validator->errors(app()->environment());
    if ($errors !== []) {
        foreach ($errors as $error) {
            $this->error($error);
        }

        return 1;
    }

    $this->info('Runtime configuration is valid.');

    return 0;
})->purpose('Validate required staging and production configuration');

Artisan::command('app:launch-check {--json}', function (ProductionLaunchReadiness $readiness): int {
    $checks = $readiness->checks();

    if ((bool) $this->option('json')) {
        $this->line(json_encode([
            'passed' => collect($checks)->every(static fn (array $check): bool => $check['passed']),
            'checks' => $checks,
        ], JSON_THROW_ON_ERROR));
    } else {
        foreach ($checks as $check) {
            $prefix = $check['passed'] ? '[PASS]' : '[FAIL]';
            $this->line(sprintf('%s %s: %s', $prefix, $check['key'], $check['detail']));
        }
    }

    return $readiness->passed() ? 0 : 1;
})->purpose('Validate repository-controlled production launch prerequisites and operational health');

Schedule::command('content:publish-scheduled --limit=100')->everyMinute()->onOneServer()->withoutOverlapping(10);
Schedule::command('content:queue-announcement-broadcasts --limit=25')->everyMinute()->onOneServer()->withoutOverlapping(10);
Schedule::command('events:queue-reminders --limit=100')->everyMinute()->onOneServer()->withoutOverlapping(10);
Schedule::command('notifications:queue-officer-briefs --group=daily --limit=1000 --cycle')->everyFifteenMinutes()->onOneServer()->withoutOverlapping(10);
Schedule::command('notifications:queue-officer-briefs --group=event --limit=1000 --cycle')->everyFifteenMinutes()->onOneServer()->withoutOverlapping(10);
Schedule::command('notifications:queue-intelligence-changes --limit=1000 --cycle')->everyFifteenMinutes()->onOneServer()->withoutOverlapping(10);
Schedule::command('notifications:deliver --limit=100')->everyMinute()->onOneServer()->withoutOverlapping(10);
Schedule::command('notifications:build-digests --limit=500')->everyMinute()->onOneServer()->withoutOverlapping(10);
Schedule::command('notifications:deliver-digests --limit=100')->everyMinute()->onOneServer()->withoutOverlapping(10);
Schedule::command('gift-codes:maintain --limit=500 --cycle')->everyFifteenMinutes()->onOneServer()->withoutOverlapping(30);
Schedule::command('gift-codes:ingest-approved-sources --limit=25 --cycle')->everyFifteenMinutes()->onOneServer()->withoutOverlapping(30);
Schedule::command('gift-codes:reconcile-sources --limit=25')->everyFifteenMinutes()->onOneServer()->withoutOverlapping(30);
Schedule::command('gift-codes:backfill-sources --limit=5')->hourly()->onOneServer()->withoutOverlapping(45);
Schedule::command('gift-codes:reconcile-source-policies --limit=500')->everyFiveMinutes()->onOneServer()->withoutOverlapping(30);
Schedule::command('contributions:queue-reports --limit=50')->everyMinute()->onOneServer()->withoutOverlapping(10);
Schedule::command('outbox:publish --limit=100')->everyMinute()->onOneServer()->withoutOverlapping(10);
Schedule::command('integrations:queue-webhooks --limit=100')->everyMinute()->onOneServer()->withoutOverlapping(10);
Schedule::command('kingdoms:queue-ingestion --limit=100')->everyMinute()->onOneServer()->withoutOverlapping(10);
Schedule::command('kingdoms:reconcile-ingestion-sources --limit=1000')->everyFiveMinutes()->onOneServer()->withoutOverlapping(10);
Schedule::command('kingdoms:enforce-ingestion-retention')->dailyAt('04:15')->onOneServer()->withoutOverlapping(60);
Schedule::command('kingdoms:enforce-sharing-retention --limit=500')->dailyAt('04:30')->onOneServer()->withoutOverlapping(60);
Schedule::command('platform:process-account-deletions --limit=100')->hourly()->onOneServer()->withoutOverlapping(30);
Schedule::command('platform:capture-usage --limit=2000')->hourly()->onOneServer()->withoutOverlapping(30);
Schedule::command('platform:enforce-retention')->dailyAt('03:45')->onOneServer()->withoutOverlapping(60);
Schedule::command('recruitment:purge-expired --limit=250')->dailyAt('03:15')->onOneServer()->withoutOverlapping(30);
Schedule::command('queue:prune-batches --hours=48')->daily()->onOneServer()->withoutOverlapping(60);
Schedule::command('queue:prune-failed --hours=168')->daily()->onOneServer()->withoutOverlapping(60);
Schedule::command('auth:clear-resets')->hourly()->onOneServer()->withoutOverlapping(10);

Schedule::command('king-perks:queue-reminders --limit=100')->everyMinute()->onOneServer()->withoutOverlapping(10);
Schedule::command('gift-codes:queue-personal-reminders --limit=100')->everyMinute()->onOneServer()->withoutOverlapping(10);
Schedule::command('gift-codes:queue-workspace-notifications --limit=100')->everyFifteenMinutes()->onOneServer()->withoutOverlapping(30);
Schedule::command('gift-codes:source-operational-alerts --limit=100')->everyFiveMinutes()->onOneServer()->withoutOverlapping(10);
Schedule::command('gift-codes:rebuild-contributor-projections --limit=100')->hourly()->onOneServer()->withoutOverlapping(30);
Schedule::command('gift-codes:rebuild-acquisition-intelligence --cluster-limit=500 --source-limit=100')->hourly()->onOneServer()->withoutOverlapping(30);
Schedule::command('kingdom-governance:expire-delegations --limit=250')->hourly()->onOneServer()->withoutOverlapping(30);
Schedule::command('evidence:enforce-retention --limit=250')->dailyAt('03:20')->onOneServer()->withoutOverlapping(60);
