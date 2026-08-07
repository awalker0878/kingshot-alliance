<?php

declare(strict_types=1);

use App\Domain\Content\Actions\PublishScheduledContent;
use App\Domain\Notifications\Actions\QueueDueContributionReports;
use App\Domain\Notifications\Actions\QueueDueEventReminders;
use App\Domain\Notifications\Actions\SyncUpcomingEventReminders;
use App\Domain\Platform\Actions\PublishOutboxBatch;
use App\Domain\Platform\Services\RuntimeConfigurationValidator;
use App\Domain\Recruitment\Actions\PurgeExpiredRecruitmentCandidates;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('about:phase', function (): void {
    $this->info('Kingshot Alliance — Phase 5 contributions and reporting');
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

Schedule::command('content:publish-scheduled --limit=100')
    ->everyMinute()
    ->onOneServer()
    ->withoutOverlapping(10);
Schedule::command('events:sync-reminders --limit=250')
    ->everyMinute()
    ->onOneServer()
    ->withoutOverlapping(10);
Schedule::command('events:queue-reminders --limit=100')
    ->everyMinute()
    ->onOneServer()
    ->withoutOverlapping(10);
Schedule::command('contributions:queue-reports --limit=50')
    ->everyMinute()
    ->onOneServer()
    ->withoutOverlapping(10);
Schedule::command('outbox:publish --limit=100')
    ->everyMinute()
    ->onOneServer()
    ->withoutOverlapping(10);
Schedule::command('recruitment:purge-expired --limit=250')
    ->dailyAt('03:15')
    ->onOneServer()
    ->withoutOverlapping(30);
Schedule::command('queue:prune-batches --hours=48')->daily();
Schedule::command('queue:prune-failed --hours=168')->daily();
