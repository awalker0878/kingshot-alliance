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
use DateTimeInterface;
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
        $playerOptions = [];
        foreach ($playerReferences as $player) {
            $playerOptions[] = [
                'id' => $player->playerId,
                'name' => $player->currentName,
            ];
        }
        usort($playerOptions, static fn (array $left, array $right): int => strcasecmp($left['name'], $right['name']));

        $evidence = [];
        foreach (GameEvidence::query()
            ->where('alliance_id', $target->allianceId)
            ->where('occurrence_id', $target->occurrenceId)
            ->orderByDesc('created_at')
            ->limit(100)
            ->get() as $item) {
            $evidence[] = $this->evidence($item);
        }

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

        $classificationRows = [];
        foreach ($classifications as $attempt) {
            $classificationRows[] = [
                'id' => (string) $attempt->id,
                'status' => (string) $attempt->getRawOriginal('status'),
                'kind' => (string) $attempt->getRawOriginal('classified_kind'),
                'confidence' => (float) $attempt->confidence,
                'reason' => $attempt->reason,
                'failureCode' => $attempt->failure_code,
                'ocrEngine' => $attempt->ocr_engine,
                'ocrVersion' => $attempt->ocr_version,
                'startedAt' => $this->isoTimestamp($attempt->started_at),
                'completedAt' => $this->isoTimestamp($attempt->completed_at),
            ];
        }

        $extractionRows = [];
        foreach ($extractions as $attempt) {
            $extractionRows[] = $this->extraction($attempt);
        }

        $reviewRows = [];
        foreach ($reviews as $review) {
            $reviewRows[] = $this->review($review);
        }

        $commitRows = [];
        foreach ($commits as $attempt) {
            $commitRows[] = [
                'id' => (string) $attempt->id,
                'status' => (string) $attempt->getRawOriginal('status'),
                'destinationReportId' => $attempt->destination_report_id === null ? null : (string) $attempt->destination_report_id,
                'receipt' => $attempt->destination_receipt,
                'failureCode' => $attempt->failure_code,
                'startedAt' => $this->isoTimestamp($attempt->started_at),
                'completedAt' => $this->isoTimestamp($attempt->completed_at),
            ];
        }

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
            'receivedAt' => $this->isoTimestamp($evidence->created_at),
            'imageAvailable' => is_string($evidence->path) && $evidence->path !== '',
            'visualDuplicate' => $evidence->visual_duplicate_evidence_id === null ? null : [
                'evidenceId' => (string) $evidence->visual_duplicate_evidence_id,
                'distance' => (int) $evidence->visual_duplicate_distance,
            ],
            'classifications' => $classificationRows,
            'extractions' => $extractionRows,
            'latestExtraction' => $latestExtraction instanceof EvidenceExtractionAttempt ? $this->extraction($latestExtraction) : null,
            'reviews' => $reviewRows,
            'latestReview' => $latestReview instanceof EvidenceReview ? $this->review($latestReview) : null,
            'commits' => $commitRows,
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
            'startedAt' => $this->isoTimestamp($attempt->started_at),
            'completedAt' => $this->isoTimestamp($attempt->completed_at),
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
        $rows = [];
        foreach (EvidenceReviewRow::query()
            ->where('review_id', $review->id)
            ->orderBy('row_ordinal')
            ->get() as $row) {
            $rows[] = [
                'ordinal' => (int) $row->row_ordinal,
                'included' => (bool) $row->included,
                'playerId' => $row->player_id === null ? null : (string) $row->player_id,
                'playerName' => (string) $row->player_name,
                'reportedRank' => $row->reported_rank === null ? null : (int) $row->reported_rank,
                'damagePoints' => $row->damage_points === null ? null : (int) $row->damage_points,
                'correctionReason' => $row->correction_reason,
                'corrected' => (bool) $row->rank_corrected || (bool) $row->name_corrected || (bool) $row->damage_corrected,
            ];
        }

        return [
            'id' => (string) $review->id,
            'extractionAttemptId' => (string) $review->extraction_attempt_id,
            'revision' => (int) $review->revision_number,
            'status' => (string) $review->getRawOriginal('status'),
            'reportTimestampText' => $review->report_timestamp_text,
            'semanticFingerprintPrefix' => substr((string) $review->semantic_fingerprint, 0, 12),
            'semanticDuplicateReviewId' => $review->semantic_duplicate_review_id === null ? null : (string) $review->semantic_duplicate_review_id,
            'duplicateResolution' => $review->duplicate_resolution,
            'reviewedAt' => $this->isoTimestamp($review->reviewed_at),
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

        /** @var list<string> $playerIds */
        $playerIds = [];
        foreach ($rows as $row) {
            if (is_string($row->player_id) && $row->player_id !== '') {
                $playerIds[] = $row->player_id;
            }
        }
        $playerIds = array_values(array_unique($playerIds));

        $current = $this->results->players((string) $review->occurrence_id, $playerIds);
        $names = $this->players->byIds($playerIds);
        $previewRows = [];

        foreach ($rows as $row) {
            $playerId = (string) $row->player_id;
            $before = $current[$playerId]['score'] ?? 0;
            $damage = (int) ($row->damage_points ?? 0);
            $playerReference = $names[$playerId] ?? null;

            $previewRows[] = [
                'playerId' => $playerId,
                'playerName' => $playerReference === null ? (string) $row->player_name : $playerReference->currentName,
                'beforeScore' => $before,
                'reportDamage' => $damage,
                'afterScore' => $before + $damage,
            ];
        }

        return [
            'reviewId' => (string) $review->id,
            'rows' => $previewRows,
        ];
    }

    private function isoTimestamp(mixed $value): ?string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format(DATE_ATOM);
        }

        if (is_string($value) && $value !== '') {
            return $value;
        }

        return null;
    }
}
