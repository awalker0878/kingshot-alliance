<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Evidence\Queries;

use App\Contexts\Intelligence\Access\Enums\IntelligencePermission;
use App\Contexts\Intelligence\Access\Services\AllianceIntelligenceAuthorization;
use App\Contexts\Intelligence\Evidence\Enums\EvidenceKind;
use App\Contexts\Intelligence\Evidence\Models\EvidenceExtractedField;
use App\Contexts\Intelligence\Evidence\Models\EvidenceExtractionAttempt;
use App\Contexts\Intelligence\Evidence\Models\GameEvidence;
use App\Contexts\Intelligence\Evidence\Models\SpatialEvidenceCommitAttempt;
use App\Contexts\Intelligence\Evidence\Models\SpatialEvidenceReview;
use Illuminate\Auth\Access\AuthorizationException;

final readonly class TerritorySpatialEvidenceSummaryQuery
{
    public function __construct(private AllianceIntelligenceAuthorization $authorization)
    {
    }

    /** @return list<array<string, mixed>> */
    public function forScope(
        string $actorPlayerId,
        string $allianceId,
        string $kingdomId,
        int $limit = 50,
    ): array {
        if (! $this->authorization->allows($actorPlayerId, $allianceId, IntelligencePermission::KingdomManage)) {
            throw new AuthorizationException;
        }

        $evidence = GameEvidence::query()
            ->where('alliance_id', $allianceId)
            ->where('kingdom_id', $kingdomId)
            ->where('expected_kind', EvidenceKind::TerritoryMapObservation->value)
            ->orderByDesc('created_at')
            ->limit(max(1, min(100, $limit)))
            ->get();
        if ($evidence->isEmpty()) {
            return [];
        }

        $ids = $evidence->pluck('id')->map(static fn ($id): string => (string) $id)->all();
        $extractions = EvidenceExtractionAttempt::query()
            ->whereIn('evidence_id', $ids)
            ->orderByDesc('completed_at')
            ->orderByDesc('id')
            ->get()
            ->groupBy('evidence_id');
        $extractionIds = [];
        foreach ($extractions as $attempts) {
            $first = $attempts->first();
            if ($first instanceof EvidenceExtractionAttempt) {
                $extractionIds[] = (string) $first->id;
            }
        }

        $fields = EvidenceExtractedField::query()
            ->whereIn('extraction_attempt_id', $extractionIds)
            ->orderBy('row_ordinal')
            ->get()
            ->groupBy('extraction_attempt_id');
        $reviews = SpatialEvidenceReview::query()
            ->whereIn('evidence_id', $ids)
            ->orderByDesc('revision_number')
            ->orderByDesc('id')
            ->get()
            ->groupBy('evidence_id');
        $reviewIds = [];
        foreach ($reviews as $items) {
            $first = $items->first();
            if ($first instanceof SpatialEvidenceReview) {
                $reviewIds[] = (string) $first->id;
            }
        }

        $attempts = SpatialEvidenceCommitAttempt::query()
            ->whereIn('spatial_review_id', $reviewIds)
            ->orderByDesc('created_at')
            ->get()
            ->groupBy('spatial_review_id');

        $result = [];
        foreach ($evidence as $item) {
            $latestExtraction = $extractions[(string) $item->id]?->first();
            $latestReview = $reviews[(string) $item->id]?->first();
            $candidateRows = [];
            if ($latestExtraction instanceof EvidenceExtractionAttempt) {
                foreach ($fields[(string) $latestExtraction->id] ?? [] as $field) {
                    if (! $field instanceof EvidenceExtractedField) {
                        continue;
                    }
                    $candidateRows[] = [
                        'field' => (string) $field->field_key,
                        'ordinal' => (int) $field->row_ordinal,
                        'raw' => (string) $field->raw_text,
                        'normalized' => (string) $field->normalized_value,
                        'confidence' => (float) $field->confidence,
                        'bounds' => $field->bounding_box,
                        'warnings' => $field->warnings ?? [],
                    ];
                }
            }

            $commit = $latestReview instanceof SpatialEvidenceReview
                ? $attempts[(string) $latestReview->id]?->first()
                : null;
            $result[] = [
                'id' => (string) $item->id,
                'status' => $item->lifecycle_status->value,
                'detected_kind' => $item->kind->value,
                'created_at' => $item->created_at?->toIso8601String(),
                'map_dataset_id' => $item->map_dataset_id,
                'map_dataset_checksum' => $item->map_dataset_checksum,
                'visual_duplicate_evidence_id' => $item->visual_duplicate_evidence_id,
                'visual_duplicate_distance' => $item->visual_duplicate_distance,
                'candidates' => $candidateRows,
                'review' => $latestReview instanceof SpatialEvidenceReview ? [
                    'id' => (string) $latestReview->id,
                    'revision' => (int) $latestReview->revision_number,
                    'status' => $latestReview->status->value,
                    'captured_at' => $latestReview->captured_at->toIso8601String(),
                    'coverage_kind' => $latestReview->coverage_kind->value,
                    'completeness' => $latestReview->completeness->value,
                    'coverage_bounds' => $latestReview->coverage_bounds,
                    'payload' => $latestReview->payload,
                    'duplicate_review_id' => $latestReview->semantic_duplicate_review_id,
                    'duplicate_resolution' => $latestReview->duplicate_resolution,
                ] : null,
                'commit' => $commit instanceof SpatialEvidenceCommitAttempt ? [
                    'status' => $commit->status->value,
                    'receipt_id' => $commit->destination_receipt_id,
                    'receipt' => $commit->destination_receipt,
                    'failure_code' => $commit->failure_code,
                ] : null,
            ];
        }

        return $result;
    }
}
