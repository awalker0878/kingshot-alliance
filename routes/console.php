<?php

declare(strict_types=1);

use App\Application\Operations\RuntimeConfigurationValidator;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('about:phase', function (): void {
    $this->info('Kingshot Alliance — Phase 0 engineering foundation');
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

Schedule::command('queue:prune-batches --hours=48')->daily();
Schedule::command('queue:prune-failed --hours=168')->daily();
