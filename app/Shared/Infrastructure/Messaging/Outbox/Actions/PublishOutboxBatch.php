<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\Messaging\Outbox\Actions;

use App\Shared\Infrastructure\Messaging\Outbox\Events\OutboxPublished;
use App\Shared\Infrastructure\Messaging\Outbox\Models\OutboxMessage;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Throwable;

final class PublishOutboxBatch
{
    public function handle(int $limit = 100): int
    {
        $published = 0;
        $limit = max(1, min($limit, 500));

        for ($index = 0; $index < $limit; $index++) {
            $claim = $this->claimNext();

            if ($claim === null) {
                break;
            }

            try {
                event(new OutboxPublished(
                    messageId: $claim->id,
                    allianceId: $claim->alliance_id,
                    eventType: $claim->event_type,
                    aggregateType: $claim->aggregate_type,
                    aggregateId: $claim->aggregate_id,
                    idempotencyKey: $claim->idempotency_key,
                    payload: $claim->payload,
                    occurredAt: $claim->occurred_at->toIso8601String(),
                ));

                $updated = OutboxMessage::query()
                    ->whereKey($claim->id)
                    ->whereNull('published_at')
                    ->where('attempts', $claim->attempts)
                    ->update([
                        'published_at' => now(),
                        'last_error' => null,
                    ]);

                if ($updated === 1) {
                    $published++;
                }
            } catch (Throwable $exception) {
                $delaySeconds = min(3600, 30 * (2 ** min($claim->attempts, 7)));

                OutboxMessage::query()
                    ->whereKey($claim->id)
                    ->whereNull('published_at')
                    ->where('attempts', $claim->attempts)
                    ->update([
                        'available_at' => now()->addSeconds($delaySeconds),
                        'last_error' => mb_substr($exception->getMessage(), 0, 2000),
                    ]);

                report($exception);
            }
        }

        return $published;
    }

    private function claimNext(): ?OutboxMessage
    {
        $maximumAttempts = max(1, (int) config('operations.outbox.maximum_attempts', 10));

        return DB::transaction(function () use ($maximumAttempts): ?OutboxMessage {
            /** @var Builder<OutboxMessage> $query */
            $query = OutboxMessage::query()
                ->whereNull('published_at')
                ->where('attempts', '<', $maximumAttempts)
                ->where('available_at', '<=', now())
                ->orderBy('occurred_at')
                ->orderBy('id');

            if (DB::getDriverName() === 'pgsql') {
                $query->lock('for update skip locked');
            } else {
                $query->lockForUpdate();
            }

            $message = $query->first();

            if (! $message instanceof OutboxMessage) {
                return null;
            }

            $message->forceFill([
                'attempts' => $message->attempts + 1,
                'available_at' => now()->addMinutes(5),
                'last_error' => null,
            ])->save();

            return $message->refresh();
        });
    }
}
