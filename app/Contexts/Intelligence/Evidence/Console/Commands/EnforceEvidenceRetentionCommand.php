<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Evidence\Console\Commands;

use App\Contexts\Intelligence\Evidence\Actions\EnforceEvidenceRetention;
use Illuminate\Console\Command;

final class EnforceEvidenceRetentionCommand extends Command
{
    protected $signature = 'evidence:enforce-retention {--limit=250}';

    protected $description = 'Apply evidence retention policies in a bounded batch.';

    public function handle(EnforceEvidenceRetention $action): int
    {
        $result = $action->handle(max(1, min(1000, (int) $this->option('limit'))));
        $this->info(sprintf('Processed %d evidence retention change(s).', $result));

        return self::SUCCESS;
    }
}
