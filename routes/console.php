<?php

declare(strict_types=1);

use App\Domain\Content\Actions\PublishScheduledContent;
use App\Domain\Identity\Models\User;
use App\Domain\Integrations\Actions\QueueDueWebhookDeliveries;
use App\Domain\Notifications\Actions\QueueDueContributionReports;
use App\Domain\Notifications\Actions\QueueDueEventReminders;
use App\Domain\Notifications\Actions\SyncUpcomingEventReminders;
use App\Domain\Platform\Actions\EnforcePlatformRetention;
use App\Domain\Platform\Actions\ManagePlatformAdministrator;
use App\Domain\Platform\Actions\ProcessAccountDeletionRequests;
use App\Domain\Platform\Actions\PublishOutboxBatch;
use App\Domain\Platform\Services\PlatformUsageService;
use App\Domain\Platform\Services\RuntimeConfigurationValidator;
use App\Domain\Recruitment\Actions\PurgeExpiredRecruitmentCandidates;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;
use Illuminate\Support\Str;

Artisan::command('about:phase', function (): void {
    $this->info('Kingshot Alliance — Phase 6 platform scale and administration');
})->purpose('Display the current implementation phase');

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

Artisan::command('platform:admin:grant {email}', function (ManagePlatformAdministrator $manage): int {
    $email = Str::lower(trim((string) $this->argument('email')));
    $user = User::query()->where('email', $email)->first();
    if (! $user instanceof User) {
        $this->error('No user exists with that email address.');

        return 1;
    }

    $manage->grant($user);
    $this->info('Platform administrator grant created. Web access still requires verified email and MFA.');

    return 0;
})->purpose('Bootstrap a platform administrator grant');

Artisan::command('platform:capture-usage {--limit=500}', function (PlatformUsageService $usage): int {
    $captured = $usage->captureAll(max(1, min(2000, (int) $this->option('limit'))));
    $this->info(sprintf('Captured usage for %d alliance(s).', $captured));

    return 0;
})->purpose('Capture alliance usage and capacity snapshots');

Artisan::command('platform:process-account-deletions {--limit=100}', function (ProcessAccountDeletionRequests $process): int {
    $processed = $process->handle(max(1, min(500, (int) $this->option('limit'))));
    $this->info(sprintf('Processed %d account deletion request(s).', $processed));

    return 0;
})->purpose('Process eligible account deletion requests with legal-hold and ownership checks');

Artisan::command('platform:enforce-retention', function (EnforcePlatformRetention $retention): int {
    $result = $retention->handle();
    $this->info(json_encode($result, JSON_THROW_ON_ERROR));

    return 0;
})->purpose('Enforce Phase 6 retention windows for operational records');

Artisan::command('integrations:queue-webhooks {--limit=100}', function (QueueDueWebhookDeliveries $queue): int {
    $queued = $queue->handle(max(1, min(500, (int) $this->option('limit'))));
    $this->info(sprintf('Queued %d due webhook delivery job(s).', $queued));

    return 0;
})->purpose('Recover and queue due webhook deliveries');

Artisan::command('content:publish-scheduled {--limit=100}', function (PublishScheduledContent $publisher): int {
    $limit = max(1, min(500, (int) $this->option('limit')));
    $published = $publisher->handle($limit);
    $this->info(sprintf('Published %d scheduled content item(s).', $published));

    return 0;
})->purpose('Publish due scheduled alliance content');

Artisan::command('events:sync-reminders {--limit=250}', function (SyncUpcomingEventReminders $sync): int {
    $limit = max(1, min(1000, (int) $this->option('limit')));
    $created = $sync->handle($limit);
    $this->info(sprintf('Created %d event reminder delivery record(s).', $created));

    return 0;
})->purpose('Materialize reminder deliveries for upcoming event registrations');

Artisan::command('events:queue-reminders {--limit=100}', function (QueueDueEventReminders $queue): int {
    $limit = max(1, min(500, (int) $this->option('limit')));
    $queued = $queue->handle($limit);
    $this->info(sprintf('Queued %d due event reminder(s).', $queued));

    return 0;
})->purpose('Queue due event reminders through the transactional outbox');

Artisan::command('contributions:queue-reports {--limit=50}', function (QueueDueContributionReports $queue): int {
    $limit = max(1, min(250, (int) $this->option('limit')));
    $queued = $queue->handle($limit);
    $this->info(sprintf('Queued %d due contribution report(s).', $queued));

    return 0;
})->purpose('Queue due contribution reports through the notification outbox');

Artisan::command('recruitment:purge-expired {--limit=100}', function (PurgeExpiredRecruitmentCandidates $purge): int {
    $limit = max(1, min(1000, (int) $this->option('limit')));
    $anonymized = $purge->handle($limit);
    $this->info(sprintf('Anonymized %d expired recruitment candidate record(s).', $anonymized));

    return 0;
})->purpose('Anonymize unsuccessful recruitment candidates whose retention period has expired');

Artisan::command('outbox:publish {--limit=100}', function (PublishOutboxBatch $publisher): int {
    $limit = max(1, min(500, (int) $this->option('limit')));
    $published = $publisher->handle($limit);
    $this->info(sprintf('Published %d outbox message(s).', $published));

    return 0;
})->purpose('Publish eligible transactional outbox messages');

Schedule::command('content:publish-scheduled --limit=100')->everyMinute()->onOneServer()->withoutOverlapping(10);
Schedule::command('events:sync-reminders --limit=250')->everyMinute()->onOneServer()->withoutOverlapping(10);
Schedule::command('events:queue-reminders --limit=100')->everyMinute()->onOneServer()->withoutOverlapping(10);
Schedule::command('contributions:queue-reports --limit=50')->everyMinute()->onOneServer()->withoutOverlapping(10);
Schedule::command('outbox:publish --limit=100')->everyMinute()->onOneServer()->withoutOverlapping(10);
Schedule::command('integrations:queue-webhooks --limit=100')->everyMinute()->onOneServer()->withoutOverlapping(10);
Schedule::command('platform:process-account-deletions --limit=100')->hourly()->onOneServer()->withoutOverlapping(30);
Schedule::command('platform:capture-usage --limit=2000')->hourly()->onOneServer()->withoutOverlapping(30);
Schedule::command('platform:enforce-retention')->dailyAt('03:45')->onOneServer()->withoutOverlapping(60);
Schedule::command('recruitment:purge-expired --limit=250')->dailyAt('03:15')->onOneServer()->withoutOverlapping(30);
Schedule::command('queue:prune-batches --hours=48')->daily();
Schedule::command('queue:prune-failed --hours=168')->daily();
