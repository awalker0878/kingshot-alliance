<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Evidence\Queries;

use App\Contexts\Intelligence\Evidence\Contracts\EvidenceReferenceLookup;
use App\Contexts\Intelligence\Evidence\Enums\EvidenceReviewStatus;
use App\Contexts\Intelligence\Evidence\Models\EvidenceReview;
use App\Contexts\Intelligence\Evidence\Models\GameEvidence;

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
        if (! $this->belongsToAlliance($evidenceId, $allianceId)) {
            return false;
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
