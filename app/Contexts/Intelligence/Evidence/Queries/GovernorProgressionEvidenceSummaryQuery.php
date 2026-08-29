<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Evidence\Queries;

use App\Contexts\Intelligence\Evidence\Models\EvidenceClassificationAttempt;
use App\Contexts\Intelligence\Evidence\Models\EvidenceExtractedField;
use App\Contexts\Intelligence\Evidence\Models\EvidenceExtractionAttempt;
use App\Contexts\Intelligence\Evidence\Models\GameEvidence;
use App\Contexts\Intelligence\Evidence\Models\GovernorProgressionEvidenceCommitAttempt;
use App\Contexts\Intelligence\Evidence\Models\GovernorProgressionEvidenceReview;
use App\Contexts\Intelligence\Evidence\Models\ProgressionNormalizationAttempt;

final class GovernorProgressionEvidenceSummaryQuery
{
    /**
     * Bounded factual status projection for an authorized Governor profile.
     *
     * @return array{total:int,pending:int,needsReview:int,committed:int,failed:int,latestAt:?string}
     */
    public function profileSummary(string $allianceId, string $rosterEntryId): array
    {
        $rows = GameEvidence::query()
            ->where('alliance_id', $allianceId)
            ->where('roster_entry_id', $rosterEntryId)
            ->whereNull('occurrence_id')
            ->whereNull('transfer_plan_id')
            ->whereNull('transfer_participant_id')
            ->orderByDesc('created_at')
            ->limit(100)
            ->get(['lifecycle_status', 'created_at']);
        $statuses = $rows->groupBy(static fn (GameEvidence $evidence): string => $evidence->lifecycle_status->value);
        $pending = ['uploaded', 'classifying', 'classified', 'extracting', 'approved', 'committing'];

        return [
            'total' => $rows->count(),
            'pending' => $statuses->only($pending)->sum(static fn ($items): int => $items->count()),
            'needsReview' => $statuses->get('needs_review', collect())->count(),
            'committed' => $statuses->get('committed', collect())->count(),
            'failed' => $statuses->get('failed', collect())->count(),
            'latestAt' => $rows->first()?->created_at?->toIso8601String(),
        ];
    }

    /** @return list<array<string,mixed>> */
    public function forRosterEntry(string $allianceId, string $rosterEntryId): array
    {
        $summaries = GameEvidence::query()
            ->where('alliance_id', $allianceId)
            ->where('roster_entry_id', $rosterEntryId)
            ->whereNull('occurrence_id')
            ->whereNull('transfer_plan_id')
            ->whereNull('transfer_participant_id')
            ->orderByDesc('created_at')
            ->limit(30)
            ->get()
            ->map(fn (GameEvidence $evidence): array => $this->summary($evidence))
            ->values()
            ->all();

        return array_values($summaries);
    }

    /** @return array<string,mixed> */
    private function summary(GameEvidence $evidence): array
    {
        $classification = EvidenceClassificationAttempt::query()
            ->where('evidence_id', $evidence->id)
            ->orderByDesc('created_at')
            ->first();
        $extraction = EvidenceExtractionAttempt::query()
            ->where('evidence_id', $evidence->id)
            ->orderByDesc('created_at')
            ->first();
        $normalization = ProgressionNormalizationAttempt::query()
            ->where('evidence_id', $evidence->id)
            ->orderByDesc('created_at')
            ->first();
        $review = GovernorProgressionEvidenceReview::query()
            ->where('evidence_id', $evidence->id)
            ->orderByDesc('revision_number')
            ->orderByDesc('id')
            ->first();
        $commit = GovernorProgressionEvidenceCommitAttempt::query()
            ->where('evidence_id', $evidence->id)
            ->orderByDesc('created_at')
            ->first();
        $fields = $extraction instanceof EvidenceExtractionAttempt
            ? EvidenceExtractedField::query()
                ->where('extraction_attempt_id', $extraction->id)
                ->orderBy('row_ordinal')
                ->orderBy('field_key')
                ->get()
                ->map(static fn (EvidenceExtractedField $field): array => [
                    'id' => (string) $field->id,
                    'fieldKey' => (string) $field->field_key,
                    'rowOrdinal' => (int) $field->row_ordinal,
                    'rawText' => (string) $field->raw_text,
                    'normalizedValue' => $field->normalized_value,
                    'dataType' => (string) $field->data_type,
                    'confidence' => (float) $field->confidence,
                    'boundingBox' => is_array($field->bounding_box) ? $field->bounding_box : null,
                    'warnings' => is_array($field->warnings) ? $field->warnings : [],
                ])->values()->all()
            : [];

        return [
            'id' => (string) $evidence->id,
            'originalName' => (string) $evidence->original_name,
            'expectedKind' => $evidence->expected_kind->value,
            'detectedKind' => $evidence->kind->value,
            'lifecycleStatus' => $evidence->lifecycle_status->value,
            'createdAt' => $evidence->created_at?->toIso8601String(),
            'imageAvailable' => $evidence->path !== null,
            'visualDuplicate' => $evidence->visual_duplicate_evidence_id === null ? null : [
                'evidenceId' => (string) $evidence->visual_duplicate_evidence_id,
                'distance' => $evidence->visual_duplicate_distance,
            ],
            'classification' => $classification instanceof EvidenceClassificationAttempt ? [
                'id' => (string) $classification->id,
                'status' => (string) $classification->getRawOriginal('status'),
                'kind' => (string) $classification->getRawOriginal('classified_kind'),
                'confidence' => (float) $classification->confidence,
                'reason' => $classification->reason,
                'classifierKey' => (string) $classification->classifier_key,
                'classifierVersion' => (string) $classification->classifier_version,
            ] : null,
            'extraction' => $extraction instanceof EvidenceExtractionAttempt ? [
                'id' => (string) $extraction->id,
                'status' => (string) $extraction->getRawOriginal('status'),
                'extractorKey' => (string) $extraction->extractor_key,
                'extractorVersion' => (string) $extraction->extractor_version,
                'schemaVersion' => (string) $extraction->schema_version,
                'overallConfidence' => (float) $extraction->overall_confidence,
                'fields' => $fields,
            ] : null,
            'normalization' => $normalization instanceof ProgressionNormalizationAttempt ? [
                'id' => (string) $normalization->id,
                'status' => (string) $normalization->getRawOriginal('status'),
                'normalizerKey' => (string) $normalization->normalizer_key,
                'normalizerVersion' => (string) $normalization->normalizer_version,
                'datasetId' => (string) $normalization->progression_dataset_id,
                'datasetChecksum' => (string) $normalization->progression_dataset_checksum,
                'payload' => is_array($normalization->normalized_payload) ? $normalization->normalized_payload : [],
                'warnings' => is_array($normalization->warnings) ? $normalization->warnings : [],
            ] : null,
            'review' => $review instanceof GovernorProgressionEvidenceReview ? [
                'id' => (string) $review->id,
                'revisionNumber' => (int) $review->revision_number,
                'status' => $review->status->value,
                'kind' => $review->evidence_kind->value,
                'schemaVersion' => (string) $review->schema_version,
                'datasetId' => (string) $review->progression_dataset_id,
                'datasetChecksum' => (string) $review->progression_dataset_checksum,
                'capturedAt' => $review->captured_at->toIso8601String(),
                'payload' => is_array($review->payload) ? $review->payload : [],
                'semanticDuplicateReviewId' => $review->semantic_duplicate_review_id,
                'duplicateResolution' => $review->duplicate_resolution,
            ] : null,
            'commit' => $commit instanceof GovernorProgressionEvidenceCommitAttempt ? [
                'id' => (string) $commit->id,
                'status' => $commit->status->value,
                'destinationAction' => (string) $commit->destination_action,
                'destinationReceiptId' => $commit->destination_receipt_id,
                'destinationReceipt' => is_array($commit->destination_receipt) ? $commit->destination_receipt : null,
                'failureCode' => $commit->failure_code,
            ] : null,
        ];
    }
}
