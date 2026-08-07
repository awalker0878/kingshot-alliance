<?php

declare(strict_types=1);

use App\Application\Content\PublishScheduledContent;
use App\Application\Events\QueueDueEventReminders;
use App\Application\Events\SyncUpcomingEventReminders;
use App\Application\Operations\RuntimeConfigurationValidator;
use App\Application\Shared\PublishOutboxBatch;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('about:phase', function (): void {
    $this->info('Kingshot Alliance — Phase 3 events and rallies');
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
Schedule::command('outbox:publish --limit=100')
    ->everyMinute()
    ->onOneServer()
    ->withoutOverlapping(10);
Schedule::command('queue:prune-batches --hours=48')->daily();
Schedule::command('queue:prune-failed --hours=168')->daily();
