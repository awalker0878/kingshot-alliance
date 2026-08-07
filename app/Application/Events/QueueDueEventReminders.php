<?php

declare(strict_types=1);

namespace App\Application\Events;

use App\Domain\Events\Enums\EventRegistrationStatus;
use App\Domain\Events\Enums\EventReminderDeliveryStatus;
use App\Models\EventRegistration;
use App\Models\EventReminderDelivery;
use App\Models\OutboxMessage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

final class QueueDueEventReminders
{
    public function handle(int $limit = 100): int
    {
        $queued = 0;
        $limit = max(1, min($limit, 500));

        for ($index = 0; $index < $limit; $index++) {
            $result = DB::transaction(function (): ?bool {
                /** @var Builder<EventReminderDelivery> $query */
                $query = EventReminderDelivery::query()
                    ->where('status', EventReminderDeliveryStatus::Pending->value)
                    ->where('due_at', '<=', now())
                    ->orderBy('due_at')
                    ->orderBy('id');

                if (DB::getDriverName() === 'pgsql') {
                    $query->lock('for update skip locked');
                } else {
                    $query->lockForUpdate();
                }

                $delivery = $query->first();

                if (! $delivery instanceof EventReminderDelivery) {
                    return null;
                }

                $stillParticipating = EventRegistration::query()
                    ->where('alliance_id', $delivery->alliance_id)
                    ->where('occurrence_id', $delivery->occurrence_id)
                    ->where('membership_id', $delivery->membership_id)
                    ->whereIn('status', [
                        EventRegistrationStatus::Registered->value,
                        EventRegistrationStatus::Waitlisted->value,
                    ])
                    ->exists();

                if (! $stillParticipating) {
                    $delivery->forceFill([
                        'status' => EventReminderDeliveryStatus::Cancelled,
                        'last_error' => 'Registration is no longer active.',
                    ])->save();

                    return false;
                }

                $outboxKey = 'event.reminder.requested:'.$delivery->idempotency_key;

                OutboxMessage::query()->firstOrCreate(
                    ['idempotency_key' => $outboxKey],
                    [
                        'alliance_id' => $delivery->alliance_id,
                        'event_type' => 'event.reminder.requested',
                        'aggregate_type' => $delivery->getMorphClass(),
                        'aggregate_id' => $delivery->id,
                        'payload' => [
                            'alliance_id' => $delivery->alliance_id,
                            'delivery_id' => $delivery->id,
                            'occurrence_id' => $delivery->occurrence_id,
                            'membership_id' => $delivery->membership_id,
                        ],
                        'occurred_at' => now(),
                        'available_at' => now(),
                        'attempts' => 0,
                    ],
                );

                $delivery->forceFill([
                    'status' => EventReminderDeliveryStatus::Queued,
                    'attempts' => $delivery->attempts + 1,
                    'queued_at' => now(),
                    'last_error' => null,
                ])->save();

                return true;
            });

            if ($result === null) {
                break;
            }

            if ($result) {
                $queued++;
            }
        }

        return $queued;
    }
}
