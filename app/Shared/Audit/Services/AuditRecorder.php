<?php

declare(strict_types=1);

namespace App\Shared\Audit\Services;

use App\Shared\Audit\Contracts\AuditActor;
use App\Shared\Audit\Models\AuditEvent;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

final class AuditRecorder
{
    /** @param array<string, mixed> $metadata */
    public function record(
        string $event,
        ?AuditActor $actor = null,
        ?Model $subject = null,
        ?Model $alliance = null,
        array $metadata = [],
    ): AuditEvent {
        $request = app()->bound('request') ? request() : null;

        return AuditEvent::query()->create([
            'alliance_id' => $alliance === null ? null : (string) $alliance->getKey(),
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
