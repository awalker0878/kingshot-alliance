<?php

declare(strict_types=1);

namespace App\Contexts\Communications\Delivery\Console\Commands;

use App\ReadModels\CommandOverview\Actions\QueueOfficerBriefNotifications;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

final class QueueOfficerBriefsCommand extends Command
{
    protected $signature = 'notifications:queue-officer-briefs {--group=all} {--limit=1000} {--after=} {--cycle}';

    protected $description = 'Queue bounded, authorized Daily or Event Officer Brief deliveries.';

    public function handle(QueueOfficerBriefNotifications $queue): int
    {
        $groupOption = $this->option('group');
        $group = is_string($groupOption) ? trim($groupOption) : '';
        if (! in_array($group, QueueOfficerBriefNotifications::GROUP_OPTIONS, true)) {
            $this->error('Choose --group=all, --group=daily or --group=event.');

            return self::FAILURE;
        }

        $afterValue = $this->option('after');
        $afterOption = is_string($afterValue) ? trim($afterValue) : '';
        $cursorKey = 'notification-delivery:officer-brief:'.$group;
        $cycle = (bool) $this->option('cycle') && $afterOption === '';
        $storedCursor = $cycle ? Cache::get($cursorKey) : null;
        $after = $afterOption !== ''
            ? $afterOption
            : (is_string($storedCursor) && $storedCursor !== '' ? $storedCursor : null);

        $result = $queue->handle(
            group: $group,
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
