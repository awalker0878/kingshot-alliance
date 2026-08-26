<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Evidence\Queries;

use App\Contexts\Intelligence\Evidence\Contracts\EvidenceReferenceLookup;
use App\Contexts\Intelligence\Evidence\Enums\EvidenceKind;
use App\Contexts\Intelligence\Evidence\Enums\EvidenceLifecycleStatus;
use App\Contexts\Intelligence\Evidence\Enums\EvidenceReviewStatus;
use App\Contexts\Intelligence\Evidence\Models\EvidenceReview;
use App\Contexts\Intelligence\Evidence\Models\GameEvidence;
use App\Contexts\Intelligence\Evidence\Models\TransferEvidenceReview;

final class EvidenceReferenceQuery implements EvidenceReferenceLookup
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

        $latest = EvidenceReview::query()
            ->where('evidence_id', $evidenceId)
            ->where('alliance_id', $allianceId)
            ->orderByDesc('revision_number')
            ->orderByDesc('id')
            ->first(['status']);

        return $latest instanceof EvidenceReview
            && $latest->status === EvidenceReviewStatus::Approved;
    }
}
