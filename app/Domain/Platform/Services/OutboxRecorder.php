<?php

declare(strict_types=1);

namespace App\Domain\Platform\Services;

use App\Domain\Platform\Models\OutboxMessage;
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
    ): OutboxMessage {
        return OutboxMessage::query()->create([
            'alliance_id' => $allianceId,
            'event_type' => $eventType,
            'aggregate_type' => $aggregate->getMorphClass(),
            'aggregate_id' => (string) $aggregate->getKey(),
            'idempotency_key' => $idempotencyKey ?? $eventType.':'.$aggregate->getKey().':'.Str::ulid(),
            'payload' => ($allianceId === null ? [] : ['alliance_id' => $allianceId]) + $payload,
            'occurred_at' => now(),
            'available_at' => now(),
            'attempts' => 0,
        ]);
    }
}
