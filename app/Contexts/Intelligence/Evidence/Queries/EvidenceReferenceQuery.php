<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Evidence\Queries;

use App\Contexts\Intelligence\Evidence\Contracts\EvidenceReferenceLookup;
use App\Contexts\Intelligence\Evidence\Contracts\GovernorProgressionEvidenceReferenceLookup;
use App\Contexts\Intelligence\Evidence\Enums\EvidenceKind;
use App\Contexts\Intelligence\Evidence\Enums\EvidenceLifecycleStatus;
use App\Contexts\Intelligence\Evidence\Enums\EvidenceReviewStatus;
use App\Contexts\Intelligence\Evidence\Models\EvidenceReview;
use App\Contexts\Intelligence\Evidence\Models\GameEvidence;
use App\Contexts\Intelligence\Evidence\Models\GovernorProgressionEvidenceReview;
use App\Contexts\Intelligence\Evidence\Models\TransferEvidenceReview;

final class EvidenceReferenceQuery implements EvidenceReferenceLookup, GovernorProgressionEvidenceReferenceLookup
{
    public function belongsToAlliance(string $evidenceId, string $allianceId): bool
    {
        return GameEvidence::query()
            ->whereKey($evidenceId)
            ->where('alliance_id', $allianceId)
            ->exists();
    }

    public function isApprovedForAlliance(string $evidenceId, string $allianceId): bool
    {
        $evidence = GameEvidence::query()
            ->whereKey($evidenceId)
            ->where('alliance_id', $allianceId)
            ->first();
        if (! $evidence instanceof GameEvidence
            || $evidence->lifecycle_status === EvidenceLifecycleStatus::Deleted
            || $evidence->kind === EvidenceKind::Unknown
            || $evidence->kind !== $evidence->expected_kind) {
            return false;
        }

        if ($evidence->kind->isTransfer()) {
            $latest = TransferEvidenceReview::query()
                ->where('evidence_id', $evidenceId)
                ->where('alliance_id', $allianceId)
                ->orderByDesc('revision_number')
                ->orderByDesc('id')
                ->first(['status']);

            return $latest instanceof TransferEvidenceReview
                && $latest->status === EvidenceReviewStatus::Approved;
        }
        if ($evidence->kind->isGovernorProgression()) {
            $latest = GovernorProgressionEvidenceReview::query()
                ->where('evidence_id', $evidenceId)
                ->where('alliance_id', $allianceId)
                ->orderByDesc('revision_number')
                ->orderByDesc('id')
                ->first(['status']);

            return $latest instanceof GovernorProgressionEvidenceReview
                && $latest->status === EvidenceReviewStatus::Approved;
        }

        $latest = EvidenceReview::query()
            ->where('evidence_id', $evidenceId)
            ->where('alliance_id', $allianceId)
            ->orderByDesc('revision_number')
            ->orderByDesc('id')
            ->first(['status']);

        return $latest instanceof EvidenceReview
            && $latest->status === EvidenceReviewStatus::Approved;
    }

    public function isApprovedGovernorProgressionReview(
        string $evidenceId,
        string $reviewId,
        string $allianceId,
        string $rosterEntryId,
        string $playerId,
        EvidenceKind $kind,
        string $schemaVersion,
        string $progressionDatasetId,
        string $progressionDatasetChecksum,
    ): bool {
        if (! $kind->isGovernorProgression()) {
            return false;
        }
        $evidence = GameEvidence::query()
            ->whereKey($evidenceId)
            ->where('alliance_id', $allianceId)
            ->where('roster_entry_id', $rosterEntryId)
            ->whereNull('occurrence_id')
            ->whereNull('transfer_plan_id')
            ->whereNull('transfer_participant_id')
            ->first();
        if (! $evidence instanceof GameEvidence
            || $evidence->lifecycle_status === EvidenceLifecycleStatus::Deleted
            || $evidence->kind !== $kind
            || $evidence->expected_kind !== $kind) {
            return false;
        }
        $latest = GovernorProgressionEvidenceReview::query()
            ->whereKey($reviewId)
            ->where('evidence_id', $evidenceId)
            ->where('alliance_id', $allianceId)
            ->where('roster_entry_id', $rosterEntryId)
            ->where('player_id', $playerId)
            ->where('evidence_kind', $kind->value)
            ->where('schema_version', $schemaVersion)
            ->where('progression_dataset_id', $progressionDatasetId)
            ->where('progression_dataset_checksum', $progressionDatasetChecksum)
            ->where('status', EvidenceReviewStatus::Approved->value)
            ->first();
        if (! $latest instanceof GovernorProgressionEvidenceReview) {
            return false;
        }
        $latestId = GovernorProgressionEvidenceReview::query()
            ->where('evidence_id', $evidenceId)
            ->orderByDesc('revision_number')
            ->orderByDesc('id')
            ->value('id');

        return (string) $latestId === (string) $latest->id;
    }
}
