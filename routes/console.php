<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('about:phase', function (): void {
    $this->info('Kingshot Alliance — Phase 0 engineering foundation');
})->purpose('Display the current implementation phase');

Artisan::command('app:config-check', function (): int {
    $required = config('operations.required_configuration', []);

    if (! is_array($required)) {
        $this->error('The required runtime configuration list is invalid.');

        return 1;
    }

    $missing = collect($required)
        ->filter(static fn (mixed $key): bool => is_string($key) && blank(config($key)))
        ->values();

    if ($missing->isNotEmpty()) {
        $this->error('Missing required runtime configuration: '.$missing->implode(', '));

        return 1;
    }

    $this->info('Runtime configuration is valid.');

    return 0;
})->purpose('Validate required staging and production configuration');

Schedule::command('queue:prune-batches --hours=48')->daily();
Schedule::command('queue:prune-failed --hours=168')->daily();
