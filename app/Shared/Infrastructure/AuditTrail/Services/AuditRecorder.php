<?php

declare(strict_types=1);

namespace App\Shared\Infrastructure\AuditTrail\Services;

use App\Shared\Infrastructure\AuditTrail\Contracts\AuditActor;
use App\Shared\Infrastructure\AuditTrail\Models\AuditEvent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

final class AuditRecorder
{
    /** The subject owner invokes this at its retention boundary inside its transaction. */
    public function redactSubjectMetadata(string $event, Model $subject): int
    {
        return AuditEvent::query()
            ->where('alliance_id', $subject->getAttribute('alliance_id'))
            ->where('event', $event)
            ->where('subject_type', $subject->getMorphClass())
            ->where('subject_id', (string) $subject->getKey())
            ->whereNotNull('metadata')
            ->update(['metadata' => json_encode(['retention_redacted' => true], JSON_THROW_ON_ERROR)]);
    }

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
