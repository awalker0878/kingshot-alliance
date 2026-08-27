<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Evidence\Actions;

use App\Contexts\Alliance\Membership\Queries\RosterEntryQuery;
use App\Contexts\Intelligence\Access\Enums\IntelligencePermission;
use App\Contexts\Intelligence\Access\Services\AllianceIntelligenceAuthorization;
use App\Contexts\Intelligence\Access\Services\AllianceIntelligenceWriteState;
use App\Contexts\Intelligence\Evidence\Enums\EvidenceLifecycleStatus;
use App\Contexts\Intelligence\Evidence\Jobs\ClassifyGameEvidenceJob;
use App\Contexts\Intelligence\Evidence\Models\GameEvidence;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class RetryGovernorProgressionEvidenceProcessing
{
    public function __construct(
        private AllianceIntelligenceAuthorization $authorization,
        private AllianceIntelligenceWriteState $writeState,
        private RosterEntryQuery $roster,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    public function handle(string $actorPlayerId, string $allianceId, string $rosterEntryId, string $evidenceId): void
    {
        if (! $this->authorization->allows($actorPlayerId, $allianceId, IntelligencePermission::KingdomManage)) {
            throw new AuthorizationException;
        }
        $this->roster->requireActiveOrTracked($allianceId, $rosterEntryId);

        DB::transaction(function () use ($actorPlayerId, $allianceId, $rosterEntryId, $evidenceId): void {
            [, $actor] = $this->writeState->authorize($actorPlayerId, $allianceId, IntelligencePermission::KingdomManage);
            $entry = $this->roster->requireActiveOrTracked($allianceId, $rosterEntryId);
            $evidence = GameEvidence::query()
                ->whereKey($evidenceId)
                ->where('alliance_id', $allianceId)
                ->where('roster_entry_id', $rosterEntryId)
                ->whereNull('occurrence_id')
                ->whereNull('transfer_plan_id')
                ->whereNull('transfer_participant_id')
                ->lockForUpdate()
                ->firstOrFail();
            if ($evidence->lifecycle_status !== EvidenceLifecycleStatus::Failed) {
                throw ValidationException::withMessages(['evidence' => 'Only failed Governor Progression Evidence processing can be retried.']);
            }
            if ($evidence->path === null) {
                throw ValidationException::withMessages(['evidence' => 'Deleted or redacted Evidence cannot be processed again.']);
            }
            $evidence->forceFill(['lifecycle_status' => EvidenceLifecycleStatus::Uploaded])->save();
            $metadata = [
                'evidence_id' => (string) $evidence->id,
                'evidence_kind' => $evidence->expected_kind->value,
                'scope' => 'governor_roster_entry',
                'roster_entry_id' => $entry->rosterEntryId,
                'target_player_id' => $entry->playerId,
            ];
            $this->audit->record('evidence.governor_progression_retry_requested', $actor, $evidence, $allianceId, $metadata);
            $this->outbox->record('evidence.governor_progression_retry_requested', $allianceId, $evidence, $metadata);
        });

        ClassifyGameEvidenceJob::dispatch($evidenceId);
    }
}
