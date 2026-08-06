<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('about:phase', function (): void {
    $this->info('Kingshot Alliance — Phase 0 engineering foundation');
})->purpose('Display the current implementation phase');

Schedule::command('queue:prune-batches --hours=48')->daily();
Schedule::command('queue:prune-failed --hours=168')->daily();
Schedule::command('sanctum:prune-expired --hours=24')->daily();
