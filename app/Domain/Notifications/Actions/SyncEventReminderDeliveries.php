<?php

declare(strict_types=1);

namespace App\Domain\Notifications\Actions;

use App\Domain\Events\Enums\EventRegistrationStatus;
use App\Domain\Events\Models\EventOccurrence;
use App\Domain\Events\Models\EventRegistration;
use App\Domain\Notifications\Enums\EventReminderDeliveryStatus;
use App\Domain\Notifications\Models\EventReminderDelivery;
use App\Domain\Notifications\Models\EventReminderRule;
use Illuminate\Support\Facades\DB;

final class SyncEventReminderDeliveries
{
    public function handle(EventOccurrence $occurrence): int
    {
        return DB::transaction(function () use ($occurrence): int {
            $occurrence = EventOccurrence::query()
                ->whereKey($occurrence->id)
                ->where('alliance_id', $occurrence->alliance_id)
                ->lockForUpdate()
                ->firstOrFail();

            $rules = EventReminderRule::query()
                ->where('event_id', $occurrence->event_id)
                ->where('alliance_id', $occurrence->alliance_id)
                ->where('is_enabled', true)
                ->get();

            $registrations = EventRegistration::query()
                ->where('occurrence_id', $occurrence->id)
                ->whereIn('status', [
                    EventRegistrationStatus::Registered->value,
                    EventRegistrationStatus::Waitlisted->value,
                ])
                ->get();

            $created = 0;

            foreach ($rules as $rule) {
                foreach ($registrations as $registration) {
                    $idempotencyKey = hash('sha256', implode('|', [
                        $occurrence->alliance_id,
                        $occurrence->id,
                        $rule->id,
                        $registration->membership_id,
                    ]));

                    $delivery = EventReminderDelivery::query()->firstOrCreate(
                        ['idempotency_key' => $idempotencyKey],
                        [
                            'alliance_id' => $occurrence->alliance_id,
                            'occurrence_id' => $occurrence->id,
                            'rule_id' => $rule->id,
                            'membership_id' => $registration->membership_id,
                            'due_at' => $occurrence->starts_at->copy()->subMinutes($rule->minutes_before_start),
                            'status' => EventReminderDeliveryStatus::Pending,
                            'attempts' => 0,
                        ],
                    );

                    if ($delivery->wasRecentlyCreated) {
                        $created++;
                    }
                }
            }

            return $created;
        });
    }
}
