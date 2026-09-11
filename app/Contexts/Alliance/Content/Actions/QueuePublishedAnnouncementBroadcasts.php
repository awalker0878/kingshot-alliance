<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Content\Actions;

use App\Contexts\Alliance\Content\Enums\BroadcastRunStatus;
use App\Contexts\Alliance\Content\Models\AnnouncementBroadcastRun;
use Illuminate\Support\Facades\Log;

final readonly class QueuePublishedAnnouncementBroadcasts
{
    public function __construct(
        private MaterializeAnnouncementBroadcastRuns $materialize,
        private QueueAnnouncementBroadcastRun $queueRun,
    ) {}

    /** Bound source visits independently from the total recipient work budget. */
    public function handle(int $limit = 25, int $recipientLimit = 100): int
    {
        $limit = max(1, min(100, $limit));
        $remaining = max(1, min(1000, $recipientLimit));
        $budget = $remaining;
        $sources = $this->materialize->handle($limit);
        $ids = AnnouncementBroadcastRun::query()->where('status', BroadcastRunStatus::Pending->value)
            ->orderBy('last_visited_at')->orderBy('id')->limit($limit)->pluck('id');
        $completed = 0;
        $visited = 0;
        $examined = 0;
        foreach ($ids as $id) {
            if ($remaining === 0) {
                break;
            }
            $result = $this->queueRun->handle((string) $id, min(25, $remaining));
            // Empty/cancelled/stale visits consume a unit too; never busy-loop them.
            $remaining -= max(1, $result->examined);
            $completed += (int) $result->completed;
            $visited++;
            $examined += $result->examined;
        }
        Log::info('content.broadcast_sweep', [
            'source_visits' => $sources, 'run_visits' => $visited,
            'recipient_attempts' => $examined, 'work_units' => $budget - $remaining,
            'recipient_budget' => $budget, 'completed_runs' => $completed,
        ]);

        return $completed;
    }
}
