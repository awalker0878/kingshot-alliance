<?php

declare(strict_types=1);

namespace App\Application\Identity;

use App\Models\Alliance;
use App\Models\AuditEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

final class AuditRecorder
{
    /** @param array<string, mixed> $metadata */
    public function record(
        string $event,
        ?User $actor = null,
        ?Model $subject = null,
        ?Alliance $alliance = null,
        array $metadata = [],
    ): AuditEvent {
        $request = app()->bound('request') ? request() : null;

        return AuditEvent::query()->create([
            'alliance_id' => $alliance?->id,
            'actor_user_id' => $actor?->id,
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
