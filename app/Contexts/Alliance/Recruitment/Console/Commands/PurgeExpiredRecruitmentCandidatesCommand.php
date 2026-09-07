<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Recruitment\Console\Commands;

use App\Contexts\Alliance\Recruitment\Actions\PurgeExpiredRecruitmentCandidates;
use Illuminate\Console\Command;

final class PurgeExpiredRecruitmentCandidatesCommand extends Command
{
    protected $signature = 'recruitment:purge-expired {--limit=100}';

    protected $description = 'Anonymize unsuccessful recruitment candidates whose retention period has expired.';

    public function handle(PurgeExpiredRecruitmentCandidates $purge): int
    {
        $limit = max(1, min(1000, (int) $this->option('limit')));
        $anonymized = $purge->handle($limit);
        $this->info(sprintf('Anonymized %d expired recruitment candidate record(s).', $anonymized));

        return self::SUCCESS;
    }
}
