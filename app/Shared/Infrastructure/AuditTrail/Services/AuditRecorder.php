<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\AuditTrail\Services;

use App\Shared\Infrastructure\AuditTrail\Contracts\AuditActor;
use App\Shared\Infrastructure\AuditTrail\Models\AuditEvent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

final class AuditRecorder
{
    /** @param array<string, mixed> $metadata */
    public function record(
        string $event,
        ?AuditActor $actor = null,
        ?Model $subject = null,
        Model|string|null $alliance = null,
        array $metadata = [],
    ): AuditEvent {
        $request = app()->bound('request') ? request() : null;

        $allianceId = match (true) {
            is_string($alliance) => $alliance,
            $alliance instanceof Model => (string) $alliance->getKey(),
            default => $subject?->getAttribute('alliance_id') === null
                ? null
                : (string) $subject->getAttribute('alliance_id'),
        };

        return AuditEvent::query()->create([
            'alliance_id' => $allianceId,
            'actor_user_id' => $actor?->auditUserId(),
            'actor_player_id' => $actor?->auditPlayerId(),
            'event' => $event,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject === null ? null : (string) $subject->getKey(),
            'metadata' => $metadata === [] ? null : $metadata,
            'request_id' => $request instanceof Request
                ? $request->attributes->get('request_id')
                : null,
            'trace_id' => $request instanceof Request
                ? $request->attributes->get('trace_id')
                : null,
            'created_at' => now(),
        ]);
    }
}
