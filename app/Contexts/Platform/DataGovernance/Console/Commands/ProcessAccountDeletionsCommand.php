<?php

declare(strict_types=1);

namespace App\Contexts\Platform\DataGovernance\Console\Commands;

use App\Contexts\Platform\DataGovernance\Actions\ProcessAccountDeletionRequests;
use Illuminate\Console\Command;

final class ProcessAccountDeletionsCommand extends Command
{
    protected $signature = 'platform:process-account-deletions {--limit=100}';

    protected $description = 'Process eligible account deletion requests with legal-hold and ownership checks.';

    public function handle(ProcessAccountDeletionRequests $process): int
    {
        $processed = $process->handle(max(1, min(500, (int) $this->option('limit'))));
        $this->info(sprintf('Processed %d account deletion request(s).', $processed));

        return self::SUCCESS;
    }
}
