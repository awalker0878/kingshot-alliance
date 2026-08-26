<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Evidence\Actions;

use App\Contexts\Alliance\Membership\Queries\RosterEntryQuery;
use App\Contexts\Intelligence\Access\Enums\IntelligencePermission;
use App\Contexts\Intelligence\Access\Services\AllianceIntelligenceAuthorization;
use App\Contexts\Intelligence\Access\Services\AllianceIntelligenceWriteState;
use App\Contexts\Intelligence\Evidence\Enums\EvidenceCommitStatus;
use App\Contexts\Intelligence\Evidence\Enums\EvidenceLifecycleStatus;
use App\Contexts\Intelligence\Evidence\Models\GameEvidence;
use App\Contexts\Intelligence\Evidence\Models\GovernorProgressionEvidenceCommitAttempt;
use App\Contexts\Intelligence\Evidence\Services\EvidenceRedactor;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class DeleteGovernorProgressionEvidence
{
    public function __construct(
        private AllianceIntelligenceAuthorization $authorization,
        private AllianceIntelligenceWriteState $writeState,
        private RosterEntryQuery $roster,
        private EvidenceRedactor $redactor,
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
            if (in_array($evidence->lifecycle_status, [
                EvidenceLifecycleStatus::Classifying,
                EvidenceLifecycleStatus::Extracting,
                EvidenceLifecycleStatus::Committing,
            ], true)) {
                throw ValidationException::withMessages(['evidence' => 'Governor Progression Evidence cannot be deleted while processing or committing is active.']);
            }
            $wasCommitted = GovernorProgressionEvidenceCommitAttempt::query()
                ->where('evidence_id', $evidence->id)
                ->where('status', EvidenceCommitStatus::Succeeded->value)
                ->exists();
            $this->redactor->redact($evidence, 'user_requested');
            $evidence->forceFill(['lifecycle_status' => EvidenceLifecycleStatus::Deleted])->save();
            $metadata = [
                'evidence_id' => (string) $evidence->id,
                'evidence_kind' => $evidence->expected_kind->value,
                'scope' => 'governor_roster_entry',
                'roster_entry_id' => $entry->rosterEntryId,
                'target_player_id' => $entry->playerId,
                'committed_roster_history_preserved' => $wasCommitted,
            ];
            $this->audit->record('evidence.governor_progression_deleted', $actor, $evidence, $allianceId, $metadata);
            $this->outbox->record('evidence.governor_progression_deleted', $allianceId, $evidence, $metadata);
        });
    }
}
