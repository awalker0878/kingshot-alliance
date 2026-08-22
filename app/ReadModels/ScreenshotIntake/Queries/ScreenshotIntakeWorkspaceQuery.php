<?php

declare(strict_types=1);

namespace App\ReadModels\ScreenshotIntake\Queries;

use App\Contexts\Alliance\Membership\Queries\RosterEntryQuery;
use App\Contexts\GameWorld\Players\Queries\PlayerReferenceQuery;
use App\Contexts\Intelligence\Evidence\Enums\EvidenceCommitStatus;
use App\Contexts\Intelligence\Evidence\Enums\EvidenceReviewStatus;
use App\Contexts\Intelligence\Evidence\Models\EvidenceClassificationAttempt;
use App\Contexts\Intelligence\Evidence\Models\EvidenceCommitAttempt;
use App\Contexts\Intelligence\Evidence\Models\EvidenceExtractedField;
use App\Contexts\Intelligence\Evidence\Models\EvidenceExtractionAttempt;
use App\Contexts\Intelligence\Evidence\Models\EvidenceReview;
use App\Contexts\Intelligence\Evidence\Models\EvidenceReviewRow;
use App\Contexts\Intelligence\Evidence\Models\GameEvidence;
use App\Contexts\Operations\Results\Queries\BearHuntEvidenceTargetQuery;
use App\Contexts\Operations\Results\Queries\BearHuntResultSnapshotQuery;
use Illuminate\Support\Collection;

final readonly class ScreenshotIntakeWorkspaceQuery
{
    public function __construct(
        private BearHuntEvidenceTargetQuery $targets,
        private BearHuntResultSnapshotQuery $results,
        private RosterEntryQuery $roster,
        private PlayerReferenceQuery $players,
    ) {}

    /** @return array<string, mixed> */
    public function forBearHunt(string $actorPlayerId, string $occurrenceId): array
    {
        $target = $this->targets->authorizeManage($actorPlayerId, $occurrenceId);
        $playerReferences = $this->players->byIds($this->roster->activePlayerIds($target->allianceId));
        $playerOptions = array_values(array_map(
            static fn ($player): array => [
                'id' => $player->playerId,
                'name' => $player->currentName,
            ],
            $playerReferences,
        ));
        usort($playerOptions, static fn (array $left, array $right): int => strcasecmp($left['name'], $right['name']));

        $evidence = GameEvidence::query()
            ->where('alliance_id', $target->allianceId)
            ->where('occurrence_id', $target->occurrenceId)
            ->orderByDesc('created_at')
            ->limit(100)
            ->get()
            ->map(fn (GameEvidence $item): array => $this->evidence($item))
            ->values()
            ->all();

        return [
            'occurrenceId' => $target->occurrenceId,
            'allianceId' => $target->allianceId,
            'acceptedReportCount' => $this->results->acceptedReportCount($target->occurrenceId),
            'players' => $playerOptions,
            'evidence' => $evidence,
        ];
    }

    /** @return array<string, mixed> */
    private function evidence(GameEvidence $evidence): array
    {
        $classifications = EvidenceClassificationAttempt::query()
            ->where('evidence_id', $evidence->id)
            ->orderBy('created_at')
            ->get();
        $extractions = EvidenceExtractionAttempt::query()
            ->where('evidence_id', $evidence->id)
            ->orderBy('created_at')
            ->get();
        $reviews = EvidenceReview::query()
            ->where('evidence_id', $evidence->id)
            ->orderBy('revision_number')
            ->get();
        $commits = EvidenceCommitAttempt::query()
            ->where('evidence_id', $evidence->id)
            ->orderBy('created_at')
            ->get();
        $latestExtraction = $extractions->last();
        $latestReview = $reviews->last();
        $latestCommit = $commits->last();

        return [
            'id' => (string) $evidence->id,
            'originalName' => (string) $evidence->original_name,
            'mimeType' => (string) $evidence->mime_type,
            'sizeBytes' => (int) $evidence->size_bytes,
            'width' => (int) $evidence->width,
            'height' => (int) $evidence->height,
            'sha256Prefix' => substr((string) $evidence->sha256, 0, 12),
            'status' => (string) $evidence->getRawOriginal('lifecycle_status'),
            'kind' => (string) $evidence->getRawOriginal('kind'),
            'receivedAt' => $evidence->created_at?->toIso8601String(),
            'imageAvailable' => is_string($evidence->path) && $evidence->path !== '',
            'visualDuplicate' => $evidence->visual_duplicate_evidence_id === null ? null : [
                'evidenceId' => (string) $evidence->visual_duplicate_evidence_id,
                'distance' => (int) $evidence->visual_duplicate_distance,
            ],
            'classifications' => $classifications->map(static fn (EvidenceClassificationAttempt $attempt): array => [
                'id' => (string) $attempt->id,
                'status' => (string) $attempt->getRawOriginal('status'),
                'kind' => (string) $attempt->getRawOriginal('classified_kind'),
                'confidence' => (float) $attempt->confidence,
                'reason' => $attempt->reason,
                'failureCode' => $attempt->failure_code,
                'ocrEngine' => $attempt->ocr_engine,
                'ocrVersion' => $attempt->ocr_version,
                'startedAt' => $attempt->started_at?->toIso8601String(),
                'completedAt' => $attempt->completed_at?->toIso8601String(),
            ])->values()->all(),
            'extractions' => $extractions->map(fn (EvidenceExtractionAttempt $attempt): array => $this->extraction($attempt))->values()->all(),
            'latestExtraction' => $latestExtraction instanceof EvidenceExtractionAttempt ? $this->extraction($latestExtraction) : null,
            'reviews' => $reviews->map(fn (EvidenceReview $review): array => $this->review($review))->values()->all(),
            'latestReview' => $latestReview instanceof EvidenceReview ? $this->review($latestReview) : null,
            'commits' => $commits->map(static fn (EvidenceCommitAttempt $attempt): array => [
                'id' => (string) $attempt->id,
                'status' => (string) $attempt->getRawOriginal('status'),
                'destinationReportId' => $attempt->destination_report_id === null ? null : (string) $attempt->destination_report_id,
                'receipt' => $attempt->destination_receipt,
                'failureCode' => $attempt->failure_code,
                'startedAt' => $attempt->started_at?->toIso8601String(),
                'completedAt' => $attempt->completed_at?->toIso8601String(),
            ])->values()->all(),
            'preview' => $latestReview instanceof EvidenceReview ? $this->preview($latestReview) : null,
            'canCommit' => $latestReview instanceof EvidenceReview
                && $latestReview->getRawOriginal('status') === EvidenceReviewStatus::Approved->value
                && ! $commits->contains(static fn (EvidenceCommitAttempt $attempt): bool => $attempt->getRawOriginal('status') === EvidenceCommitStatus::Succeeded->value),
            'canRetry' => $evidence->getRawOriginal('lifecycle_status') === 'failed' && $evidence->path !== null,
            'canDelete' => ! in_array((string) $evidence->getRawOriginal('lifecycle_status'), ['classifying', 'extracting', 'committing', 'purged'], true),
            'latestCommitStatus' => $latestCommit instanceof EvidenceCommitAttempt ? (string) $latestCommit->getRawOriginal('status') : null,
        ];
    }

    /** @return array<string, mixed> */
    private function extraction(EvidenceExtractionAttempt $attempt): array
    {
        $fields = EvidenceExtractedField::query()
            ->where('extraction_attempt_id', $attempt->id)
            ->orderBy('row_ordinal')
            ->orderBy('field_key')
            ->get();
        $rows = $fields
            ->where('row_ordinal', '>', 0)
            ->groupBy('row_ordinal')
            ->map(function (Collection $rowFields, int|string $ordinal): array {
                $byKey = $rowFields->keyBy('field_key');

                return [
                    'ordinal' => (int) $ordinal,
                    'rank' => $this->field($byKey->get('rank')),
                    'playerName' => $this->field($byKey->get('player_name')),
                    'damage' => $this->field($byKey->get('damage')),
                ];
            })
            ->values()
            ->all();
        $timestamp = $fields->firstWhere('field_key', 'report_timestamp');

        return [
            'id' => (string) $attempt->id,
            'status' => (string) $attempt->getRawOriginal('status'),
            'overallConfidence' => (float) $attempt->overall_confidence,
            'fieldCount' => (int) $attempt->field_count,
            'failureCode' => $attempt->failure_code,
            'startedAt' => $attempt->started_at?->toIso8601String(),
            'completedAt' => $attempt->completed_at?->toIso8601String(),
            'reportTimestamp' => $this->field($timestamp),
            'rows' => $rows,
        ];
    }

    /** @return array<string, mixed>|null */
    private function field(mixed $field): ?array
    {
        if (! $field instanceof EvidenceExtractedField) {
            return null;
        }

        return [
            'id' => (string) $field->id,
            'rawText' => (string) $field->raw_text,
            'value' => (string) $field->normalized_value,
            'confidence' => (float) $field->confidence,
            'boundingBox' => $field->bounding_box,
            'warnings' => $field->warnings ?? [],
        ];
    }

    /** @return array<string, mixed> */
    private function review(EvidenceReview $review): array
    {
        $rows = EvidenceReviewRow::query()
            ->where('review_id', $review->id)
            ->orderBy('row_ordinal')
            ->get()
            ->map(static fn (EvidenceReviewRow $row): array => [
                'ordinal' => (int) $row->row_ordinal,
                'included' => (bool) $row->included,
                'playerId' => $row->player_id === null ? null : (string) $row->player_id,
                'playerName' => (string) $row->player_name,
                'reportedRank' => $row->reported_rank === null ? null : (int) $row->reported_rank,
                'damagePoints' => $row->damage_points === null ? null : (int) $row->damage_points,
                'correctionReason' => $row->correction_reason,
                'corrected' => (bool) $row->rank_corrected || (bool) $row->name_corrected || (bool) $row->damage_corrected,
            ])
            ->values()
            ->all();

        return [
            'id' => (string) $review->id,
            'extractionAttemptId' => (string) $review->extraction_attempt_id,
            'revision' => (int) $review->revision_number,
            'status' => (string) $review->getRawOriginal('status'),
            'reportTimestampText' => $review->report_timestamp_text,
            'semanticFingerprintPrefix' => substr((string) $review->semantic_fingerprint, 0, 12),
            'semanticDuplicateReviewId' => $review->semantic_duplicate_review_id === null ? null : (string) $review->semantic_duplicate_review_id,
            'duplicateResolution' => $review->duplicate_resolution,
            'reviewedAt' => $review->reviewed_at?->toIso8601String(),
            'rows' => $rows,
        ];
    }

    /** @return array<string, mixed> */
    private function preview(EvidenceReview $review): array
    {
        $rows = EvidenceReviewRow::query()
            ->where('review_id', $review->id)
            ->where('included', true)
            ->orderBy('row_ordinal')
            ->get();
        $playerIds = $rows->pluck('player_id')
            ->filter(static fn ($id): bool => is_string($id) && $id !== '')
            ->map(static fn ($id): string => (string) $id)
            ->values()
            ->all();
        $current = $this->results->players((string) $review->occurrence_id, $playerIds);
        $names = $this->players->byIds($playerIds);

        return [
            'reviewId' => (string) $review->id,
            'rows' => $rows->map(static function (EvidenceReviewRow $row) use ($current, $names): array {
                $playerId = (string) $row->player_id;
                $before = $current[$playerId]['score'] ?? 0;
                $damage = (int) ($row->damage_points ?? 0);

                return [
                    'playerId' => $playerId,
                    'playerName' => $names[$playerId]?->currentName ?? (string) $row->player_name,
                    'beforeScore' => $before,
                    'reportDamage' => $damage,
                    'afterScore' => $before + $damage,
                ];
            })->values()->all(),
        ];
    }
}
