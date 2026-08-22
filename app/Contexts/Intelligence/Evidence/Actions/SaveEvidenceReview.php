<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Evidence\Actions;

use App\Contexts\Alliance\Membership\Queries\RosterEntryQuery;
use App\Contexts\GameWorld\Players\Queries\PlayerReferenceQuery;
use App\Contexts\Intelligence\Evidence\Enums\EvidenceAttemptStatus;
use App\Contexts\Intelligence\Evidence\Enums\EvidenceLifecycleStatus;
use App\Contexts\Intelligence\Evidence\Enums\EvidenceReviewStatus;
use App\Contexts\Intelligence\Evidence\Models\EvidenceExtractedField;
use App\Contexts\Intelligence\Evidence\Models\EvidenceExtractionAttempt;
use App\Contexts\Intelligence\Evidence\Models\EvidenceReview;
use App\Contexts\Intelligence\Evidence\Models\EvidenceReviewRow;
use App\Contexts\Intelligence\Evidence\Models\GameEvidence;
use App\Contexts\Operations\Results\Queries\BearHuntEvidenceTargetQuery;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class SaveEvidenceReview
{
    public function __construct(
        private BearHuntEvidenceTargetQuery $targets,
        private RosterEntryQuery $roster,
        private PlayerReferenceQuery $players,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    /**
     * @param list<array{row_ordinal:int,included:bool,player_id:?string,player_name:string,reported_rank:?int,damage_points:?int,correction_reason:?string}> $rows
     */
    public function handle(
        string $actorPlayerId,
        string $evidenceId,
        string $extractionAttemptId,
        array $rows,
        ?string $reportTimestampText = null,
    ): string {
        if ($rows === [] || count($rows) > 100) {
            throw ValidationException::withMessages(['rows' => 'Review between 1 and 100 ranking rows.']);
        }

        $evidence = GameEvidence::query()->findOrFail($evidenceId);
        $target = $this->targets->authorizeManage($actorPlayerId, (string) $evidence->occurrence_id);
        if ((string) $evidence->alliance_id !== $target->allianceId) {
            throw ValidationException::withMessages(['evidence' => 'This evidence is outside the current Bear Hunt scope.']);
        }

        return DB::transaction(function () use ($actorPlayerId, $evidenceId, $extractionAttemptId, $rows, $reportTimestampText, $target): string {
            $this->targets->authorizeManage($actorPlayerId, $target->occurrenceId);
            $actor = $this->players->lockCurrent($actorPlayerId);
            $evidence = GameEvidence::query()->whereKey($evidenceId)->where('alliance_id', $target->allianceId)->lockForUpdate()->firstOrFail();
            $extraction = EvidenceExtractionAttempt::query()
                ->whereKey($extractionAttemptId)
                ->where('evidence_id', $evidenceId)
                ->where('status', EvidenceAttemptStatus::Completed->value)
                ->sharedLock()
                ->firstOrFail();

            $sourceFields = EvidenceExtractedField::query()
                ->where('extraction_attempt_id', $extraction->id)
                ->get()
                ->groupBy(static fn (EvidenceExtractedField $field): int => (int) $field->row_ordinal);

            $normalizedRows = [];
            $seenOrdinals = [];
            $seenPlayers = [];
            foreach ($rows as $row) {
                $ordinal = (int) $row['row_ordinal'];
                if ($ordinal < 1 || $ordinal > 1000 || isset($seenOrdinals[$ordinal])) {
                    throw ValidationException::withMessages(['rows' => 'Review row ordinals must be unique positive values.']);
                }
                $seenOrdinals[$ordinal] = true;
                $included = (bool) $row['included'];
                $playerId = $row['player_id'] === null ? null : trim((string) $row['player_id']);
                $playerName = trim((string) $row['player_name']);
                $rank = $row['reported_rank'];
                $damage = $row['damage_points'];
                $reason = $row['correction_reason'] === null ? null : trim((string) $row['correction_reason']);

                if ($playerName === '' || mb_strlen($playerName) > 128) {
                    throw ValidationException::withMessages(['rows' => 'Every reviewed row needs a Governor name of 128 characters or fewer.']);
                }
                if ($rank !== null && ($rank < 1 || $rank > 999)) {
                    throw ValidationException::withMessages(['rows' => 'Reported rank must be between 1 and 999 when provided.']);
                }
                if ($damage !== null && $damage < 0) {
                    throw ValidationException::withMessages(['rows' => 'Damage cannot be negative.']);
                }
                if ($reason !== null && mb_strlen($reason) > 500) {
                    throw ValidationException::withMessages(['rows' => 'Correction reasons must be 500 characters or fewer.']);
                }
                if ($included) {
                    if ($playerId === null || $playerId === '' || $damage === null) {
                        throw ValidationException::withMessages(['rows' => 'Included rows require a resolved Governor and damage value.']);
                    }
                    if (isset($seenPlayers[$playerId])) {
                        throw ValidationException::withMessages(['rows' => 'A Governor can appear only once in one battle report.']);
                    }
                    if (! $this->roster->hasActiveRosterPresence($target->allianceId, $playerId)) {
                        throw ValidationException::withMessages(['rows' => 'Included Governors must be active members of the Bear Hunt Alliance.']);
                    }
                    $this->players->lockCurrent($playerId);
                    $seenPlayers[$playerId] = true;
                }

                $fields = $sourceFields->get($ordinal, collect())->keyBy(static fn (EvidenceExtractedField $field): string => (string) $field->field_key);
                $rankField = $fields->get('rank');
                $nameField = $fields->get('player_name');
                $damageField = $fields->get('damage');
                $normalizedRows[] = [
                    'row_ordinal' => $ordinal,
                    'included' => $included,
                    'player_id' => $playerId === '' ? null : $playerId,
                    'player_name' => $playerName,
                    'reported_rank' => $rank,
                    'damage_points' => $damage,
                    'correction_reason' => $reason === '' ? null : $reason,
                    'source_rank_field_id' => $rankField instanceof EvidenceExtractedField ? (string) $rankField->id : null,
                    'source_name_field_id' => $nameField instanceof EvidenceExtractedField ? (string) $nameField->id : null,
                    'source_damage_field_id' => $damageField instanceof EvidenceExtractedField ? (string) $damageField->id : null,
                    'rank_corrected' => ! ($rankField instanceof EvidenceExtractedField) || (string) $rankField->normalized_value !== ($rank === null ? '' : (string) $rank),
                    'name_corrected' => ! ($nameField instanceof EvidenceExtractedField) || trim((string) $nameField->normalized_value) !== $playerName,
                    'damage_corrected' => ! ($damageField instanceof EvidenceExtractedField) || (string) $damageField->normalized_value !== ($damage === null ? '' : (string) $damage),
                ];
            }

            $timestamp = $reportTimestampText === null || trim($reportTimestampText) === '' ? null : trim($reportTimestampText);
            if ($timestamp !== null && mb_strlen($timestamp) > 64) {
                throw ValidationException::withMessages(['report_timestamp' => 'Report timestamp text must be 64 characters or fewer.']);
            }
            $fingerprint = $this->fingerprint($target->occurrenceId, $timestamp, $normalizedRows);
            $duplicate = EvidenceReview::query()
                ->where('occurrence_id', $target->occurrenceId)
                ->where('semantic_fingerprint', $fingerprint)
                ->where('evidence_id', '!=', $evidenceId)
                ->whereIn('status', [EvidenceReviewStatus::Approved->value, EvidenceReviewStatus::DuplicateBlocked->value])
                ->orderBy('reviewed_at')
                ->first();
            $revision = ((int) EvidenceReview::query()->where('evidence_id', $evidenceId)->max('revision_number')) + 1;
            $status = $duplicate instanceof EvidenceReview ? EvidenceReviewStatus::DuplicateBlocked : EvidenceReviewStatus::Approved;
            $review = EvidenceReview::query()->create([
                'evidence_id' => $evidence->id,
                'extraction_attempt_id' => $extraction->id,
                'alliance_id' => $target->allianceId,
                'occurrence_id' => $target->occurrenceId,
                'revision_number' => $revision,
                'status' => $status,
                'report_timestamp_text' => $timestamp,
                'semantic_fingerprint' => $fingerprint,
                'semantic_duplicate_review_id' => $duplicate instanceof EvidenceReview ? (string) $duplicate->id : null,
                'reviewed_by_player_id' => $actorPlayerId,
                'reviewed_at' => now(),
            ]);
            foreach ($normalizedRows as $row) {
                EvidenceReviewRow::query()->create(['review_id' => $review->id, ...$row]);
            }
            $evidence->forceFill(['lifecycle_status' => $status === EvidenceReviewStatus::Approved ? EvidenceLifecycleStatus::Approved : EvidenceLifecycleStatus::NeedsReview])->save();
            $metadata = [
                'evidence_id' => (string) $evidence->id,
                'review_id' => (string) $review->id,
                'revision_number' => $revision,
                'included_rows' => count(array_filter($normalizedRows, static fn (array $row): bool => $row['included'])),
                'semantic_duplicate' => $duplicate instanceof EvidenceReview,
            ];
            $event = $duplicate instanceof EvidenceReview ? 'evidence.semantic_duplicate_detected' : 'evidence.review_approved';
            $this->audit->record($event, $actor, $evidence, $target->allianceId, $metadata);
            $this->outbox->record($event, $target->allianceId, $evidence, $metadata);

            return (string) $review->id;
        });
    }

    /** @param list<array<string,mixed>> $rows */
    private function fingerprint(string $occurrenceId, ?string $timestamp, array $rows): string
    {
        $accepted = array_values(array_filter($rows, static fn (array $row): bool => (bool) $row['included']));
        usort($accepted, static fn (array $a, array $b): int => strcmp((string) $a['player_id'], (string) $b['player_id']));
        $payload = [$occurrenceId, $timestamp, array_map(static fn (array $row): array => [
            (string) $row['player_id'], $row['reported_rank'], $row['damage_points'],
        ], $accepted)];

        return hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR));
    }
}
