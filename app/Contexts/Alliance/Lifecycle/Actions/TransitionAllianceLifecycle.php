<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Lifecycle\Actions;

use App\Contexts\Alliance\Lifecycle\Enums\AllianceStatus;
use App\Contexts\Alliance\Lifecycle\Queries\AllianceReferenceQuery;
use App\Contexts\Alliance\Lifecycle\Services\AllianceLifecycleMutation;
use App\Contexts\Alliance\Lifecycle\ValueObjects\AllianceReference;
use App\Shared\Infrastructure\AuditTrail\Contracts\AuditActor;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Carbon\CarbonInterface;
use Illuminate\Support\Facades\DB;

final readonly class TransitionAllianceLifecycle
{
    public function __construct(
        private AllianceLifecycleMutation $mutation,
        private AllianceReferenceQuery $alliances,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    public function handle(
        AuditActor $actor,
        string $allianceId,
        AllianceStatus $target,
        string $reason,
        ?CarbonInterface $retentionUntil = null,
    ): AllianceReference {
        DB::transaction(function () use ($actor, $allianceId, $target, $reason, $retentionUntil): void {
            $locked = $this->mutation->acquire($allianceId);
            $previous = $locked->status;
            $updated = $this->mutation->transitionLocked($locked, $target, $reason, $retentionUntil);

            $this->audit->record(
                'alliance.lifecycle.changed',
                $actor,
                $updated,
                $updated,
                [
                    'from' => $previous->value,
                    'to' => $target->value,
                    'reason' => $reason,
                    'retention_until' => $updated->retention_until?->toIso8601String(),
                ],
            );

            $this->outbox->record(
                'alliance.lifecycle.changed',
                (string) $updated->id,
                $updated,
                [
                    'alliance_id' => $updated->id,
                    'from' => $previous->value,
                    'to' => $target->value,
                ],
            );
        });

        return $this->alliances->require($allianceId);
    }
}
