<?php

declare(strict_types=1);

namespace App\Shared\Messaging\Services;

use App\Shared\Messaging\Models\OutboxMessage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

final class OutboxRecorder
{
    /** @param array<string, mixed> $payload */
    public function record(
        string $eventType,
        ?string $allianceId,
        Model $aggregate,
        array $payload = [],
        ?string $idempotencyKey = null,
        ?string $partitionKey = null,
    ): OutboxMessage {
        return OutboxMessage::query()->create([
            'alliance_id' => $allianceId,
            'partition_key' => $partitionKey ?? ($allianceId === null ? null : 'alliance:'.$allianceId),
            'event_type' => $eventType,
            'aggregate_type' => $aggregate->getMorphClass(),
            'aggregate_id' => (string) $aggregate->getKey(),
            'idempotency_key' => $idempotencyKey ?? $eventType.':'.$aggregate->getKey().':'.Str::ulid(),
            'payload' => ($allianceId === null ? [] : ['alliance_id' => $allianceId])
                + (($partitionKey ?? ($allianceId === null ? null : 'alliance:'.$allianceId)) === null ? [] : ['partition_key' => $partitionKey ?? 'alliance:'.$allianceId])
                + $payload,
            'occurred_at' => now(),
            'available_at' => now(),
            'attempts' => 0,
        ]);
    }
}
