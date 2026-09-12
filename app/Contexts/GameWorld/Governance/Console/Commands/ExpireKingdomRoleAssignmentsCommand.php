<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\Governance\Console\Commands;

use App\Contexts\GameWorld\Governance\Actions\ExpireKingdomRoleAssignments;
use Illuminate\Console\Command;

final class ExpireKingdomRoleAssignmentsCommand extends Command
{
    protected $signature = 'kingdom-governance:expire-delegations {--limit=250}';

    protected $description = 'Expire due Kingdom delegations in a bounded batch.';

    public function handle(ExpireKingdomRoleAssignments $action): int
    {
        $result = $action->handle(max(1, min(500, (int) $this->option('limit'))));
        $this->info(sprintf('Expired %d Kingdom delegation(s).', $result));

        return self::SUCCESS;
    }
}
