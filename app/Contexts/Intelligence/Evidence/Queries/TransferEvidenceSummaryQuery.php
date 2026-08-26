<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Evidence\Queries;

use App\Contexts\Intelligence\Evidence\Models\EvidenceClassificationAttempt;
use App\Contexts\Intelligence\Evidence\Models\EvidenceExtractedField;
use App\Contexts\Intelligence\Evidence\Models\EvidenceExtractionAttempt;
use App\Contexts\Intelligence\Evidence\Models\GameEvidence;
use App\Contexts\Intelligence\Evidence\Models\TransferEvidenceCommitAttempt;
use App\Contexts\Intelligence\Evidence\Models\TransferEvidenceReview;
use App\Contexts\Intelligence\Evidence\Models\TransferEvidenceReviewKingdom;
use Illuminate\Support\Collection;

final class TransferEvidenceSummaryQuery
{
    /** @return array<string,list<array<string,mixed>>> */
    public function forPlan(string $allianceId, string $planId): array
    {
        $evidence = GameEvidence::query()
            ->where('alliance_id', $allianceId)
            ->where('transfer_plan_id', $planId)
            ->whereNotNull('transfer_participant_id')
            ->orderByDesc('created_at')
            ->limit(500)
            ->get();
        if ($evidence->isEmpty()) {
            return [];
        }
        $evidenceIds = $evidence->pluck('id')->map('strval')->all();
        $classifications = EvidenceClassificationAttempt::query()
            ->whereIn('evidence_id', $evidenceIds)
            ->orderByDesc('created_at')
            ->get()
            ->groupBy('evidence_id');
        $extractions = EvidenceExtractionAttempt::query()
            ->whereIn('evidence_id', $evidenceIds)
            ->orderByDesc('created_at')
            ->get()
            ->groupBy('evidence_id');
        $latestExtractionIds = $extractions
            ->map(static fn (Collection $rows) => $rows->first()?->id)
            ->filter()
            ->map('strval')
            ->values()
            ->all();
        $fields = EvidenceExtractedField::query()
            ->whereIn('extraction_attempt_id', $latestExtractionIds)
            ->orderBy('row_ordinal')
            ->orderBy('id')
            ->get()
            ->groupBy('extraction_attempt_id');
        $reviews = TransferEvidenceReview::query()
            ->whereIn('evidence_id', $evidenceIds)
            ->orderByDesc('revision_number')
            ->orderByDesc('id')
            ->get()
            ->groupBy('evidence_id');
        $latestReviewIds = $reviews
            ->map(static fn (Collection $rows) => $rows->first()?->id)
            ->filter()
            ->map('strval')
            ->values()
            ->all();
        $reviewKingdoms = TransferEvidenceReviewKingdom::query()
            ->whereIn('review_id', $latestReviewIds)
            ->orderBy('ordinal')
            ->get()
            ->groupBy('review_id');
        $commits = TransferEvidenceCommitAttempt::query()
            ->whereIn('evidence_id', $evidenceIds)
            ->orderByDesc('created_at')
            ->get()
            ->groupBy('evidence_id');

        $result = [];
        foreach ($evidence as $item) {
            $classification = $classifications->get((string) $item->id, collect())->first();
            $extraction = $extractions->get((string) $item->id, collect())->first();
            $review = $reviews->get((string) $item->id, collect())->first();
            $commit = $commits->get((string) $item->id, collect())->first();
            $fieldRows = $extraction instanceof EvidenceExtractionAttempt
                ? $fields->get((string) $extraction->id, collect())
                : collect();
            $kingdomRows = $review instanceof TransferEvidenceReview
                ? $reviewKingdoms->get((string) $review->id, collect())
                : collect();
            $participantId = (string) $item->transfer_participant_id;
            $result[$participantId] ??= [];
            $result[$participantId][] = [
                'id' => (string) $item->id,
                'expectedKind' => $item->expected_kind->value,
                'kind' => $item->kind->value,
                'status' => $item->lifecycle_status->value,
                'createdAt' => $item->created_at?->toIso8601String(),
                'hasImage' => $item->path !== null,
                'hasVisualDuplicate' => $item->visual_duplicate_evidence_id !== null,
                'classification' => $classification instanceof EvidenceClassificationAttempt ? [
                    'kind' => $classification->classified_kind->value,
                    'confidence' => (float) $classification->confidence,
                    'reason' => $classification->reason,
                    'status' => $classification->status->value,
                ] : null,
                'extraction' => $extraction instanceof EvidenceExtractionAttempt ? [
                    'id' => (string) $extraction->id,
                    'schemaVersion' => $extraction->schema_version,
                    'confidence' => (float) $extraction->overall_confidence,
                    'status' => $extraction->status->value,
                    'fields' => $fieldRows->map(static fn (EvidenceExtractedField $field): array => [
                        'key' => (string) $field->field_key,
                        'ordinal' => (int) $field->row_ordinal,
                        'raw' => (string) $field->raw_text,
                        'value' => $field->normalized_value,
                        'confidence' => (float) $field->confidence,
                        'warnings' => is_array($field->warnings) ? $field->warnings : [],
                    ])->values()->all(),
                ] : null,
                'review' => $review instanceof TransferEvidenceReview ? [
                    'id' => (string) $review->id,
                    'revision' => (int) $review->revision_number,
                    'status' => $review->status->value,
                    'observedAt' => $review->observed_at->toIso8601String(),
                    'validUntil' => $review->valid_until?->toIso8601String(),
                    'governorPower' => $review->governor_power,
                    'transferScore' => $review->transfer_score,
                    'passesAvailable' => $review->transfer_passes_available,
                    'passesRequired' => $review->transfer_passes_required,
                    'invitationStatus' => $review->invitation_status,
                    'targetPowerCap' => $review->target_power_cap,
                    'kingdomClassification' => $review->kingdom_classification,
                    'officialGroupIdentifier' => $review->official_group_identifier,
                    'officialGroupKingdomNumbers' => $kingdomRows->pluck('kingdom_number')->map(static fn ($number): int => (int) $number)->all(),
                    'semanticDuplicateReviewId' => $review->semantic_duplicate_review_id,
                    'duplicateResolution' => $review->duplicate_resolution,
                ] : null,
                'commit' => $commit instanceof TransferEvidenceCommitAttempt ? [
                    'id' => (string) $commit->id,
                    'status' => $commit->status->value,
                    'destinationAction' => $commit->destination_action,
                    'destinationReceiptId' => $commit->destination_receipt_id,
                    'failureCode' => $commit->failure_code,
                ] : null,
            ];
        }

        return $result;
    }
}
