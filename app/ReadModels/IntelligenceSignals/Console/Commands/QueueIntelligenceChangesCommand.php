<?php

declare(strict_types=1);

namespace App\ReadModels\IntelligenceSignals\Console\Commands;

use App\ReadModels\IntelligenceSignals\Services\QueueIntelligenceChangeNotifications;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

final class QueueIntelligenceChangesCommand extends Command
{
    protected $signature = 'notifications:queue-intelligence-changes {--limit=1000} {--after=} {--cycle}';

    protected $description = 'Queue bounded, authorized Intelligence change deliveries.';

    public function handle(QueueIntelligenceChangeNotifications $queue): int
    {
        $afterValue = $this->option('after');
        $afterOption = is_string($afterValue) ? trim($afterValue) : '';
        $cursorKey = 'notification-delivery:intelligence-change';
        $cycle = (bool) $this->option('cycle') && $afterOption === '';
        $storedCursor = $cycle ? Cache::get($cursorKey) : null;
        $after = $afterOption !== ''
            ? $afterOption
            : (is_string($storedCursor) && $storedCursor !== '' ? $storedCursor : null);

        $result = $queue->handle(
            limit: max(1, min(2000, (int) $this->option('limit'))),
            afterMembershipId: $after,
        );

        if ($cycle) {
            if ($result->nextCursor === null) {
                Cache::forget($cursorKey);
            } else {
                Cache::forever($cursorKey, $result->nextCursor);
            }
        }

        $this->line(json_encode($result->toArray(), JSON_THROW_ON_ERROR));

        return self::SUCCESS;
    }
}
