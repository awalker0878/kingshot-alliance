<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Actions;

use App\Domain\Events\Enums\EventOccurrenceStatus;
use App\Domain\Events\Models\EventOccurrence;

final class SyncUpcomingEventReminders
{
    public function __construct(private SyncEventReminderDeliveries $syncOccurrence) {}

    public function handle(int $limit = 250): int
    {
        $limit = max(1, min($limit, 1000));
        $created = 0;

        EventOccurrence::query()
            ->where('status', EventOccurrenceStatus::Scheduled->value)
            ->where('starts_at', '>', now())
            ->orderBy('starts_at')
            ->limit($limit)
            ->get()
            ->each(function (EventOccurrence $occurrence) use (&$created): void {
                $created += $this->syncOccurrence->handle($occurrence);
            });

        return $created;
    }
}
