<?php

declare(strict_types=1);

namespace App\Application\Events;

use App\Models\Alliance;
use App\Models\OutboxMessage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

final class EventOutbox
{
    /** @param array<string, mixed> $payload */
    public function record(string $eventType, Alliance $alliance, Model $aggregate, array $payload = []): OutboxMessage
    {
        return OutboxMessage::query()->create([
            'alliance_id' => $alliance->id,
            'event_type' => $eventType,
            'aggregate_type' => $aggregate->getMorphClass(),
            'aggregate_id' => (string) $aggregate->getKey(),
            'idempotency_key' => $eventType.':'.$aggregate->getKey().':'.Str::ulid(),
            'payload' => ['alliance_id' => $alliance->id] + $payload,
            'occurred_at' => now(),
            'available_at' => now(),
            'attempts' => 0,
        ]);
    }
}
