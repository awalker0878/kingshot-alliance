<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Evidence\Actions;

use App\Contexts\Alliance\Membership\Queries\RosterEntryQuery;
use App\Contexts\Intelligence\Access\Enums\IntelligencePermission;
use App\Contexts\Intelligence\Access\Services\AllianceIntelligenceWriteState;
use App\Contexts\Intelligence\Evidence\Enums\EvidenceLifecycleStatus;
use App\Contexts\Intelligence\Evidence\Enums\EvidenceReviewStatus;
use App\Contexts\Intelligence\Evidence\Models\AllianceRosterEvidence;
use App\Contexts\Intelligence\Evidence\Models\AllianceRosterEvidenceReview;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class SaveAllianceRosterEvidenceReview
{
    public function __construct(
        private AllianceIntelligenceWriteState $writeState,
        private RosterEntryQuery $rosterEntries,
        private AuditRecorder $audit,
        private OutboxRecorder $outbox,
    ) {}

    /**
     * @param  list<array{observed_name:string,game_player_id?:?string,observed_rank?:?string,power?:?int,roster_entry_id?:?string,source_metadata?:array<string,mixed>}>  $rows
     */
    public function handle(
        string $actorPlayerId,
        string $allianceId,
        string $evidenceId,
        string $capturedAt,
        array $rows,
        bool $completeRoster = false,
        bool $allowSemanticDuplicate = false,
    ): string {
        if ($rows === []) {
            throw ValidationException::withMessages(['rows' => 'Review at least one visible Governor row.']);
        }
        if (count($rows) > 300) {
            throw ValidationException::withMessages(['rows' => 'A single roster screenshot review is limited to 300 rows.']);
        }

        $captured = Carbon::parse($capturedAt);
        $normalized = $this->normalizeRows($allianceId, $rows);
        $fingerprint = hash('sha256', json_encode([
            'schema' => 'alliance-roster-v1',
            'captured_at' => $captured->toIso8601String(),
            'complete_roster' => $completeRoster,
            'rows' => $normalized,
        ], JSON_THROW_ON_ERROR));

        return DB::transaction(function () use ($actorPlayerId, $allianceId, $evidenceId, $captured, $normalized, $completeRoster, $fingerprint, $allowSemanticDuplicate): string {
            [, $actor] = $this->writeState->authorize($actorPlayerId, $allianceId, IntelligencePermission::KingdomManage);
            $evidence = AllianceRosterEvidence::query()
                ->whereKey($evidenceId)
                ->where('alliance_id', $allianceId)
                ->lockForUpdate()
                ->firstOrFail();

            if (in_array($evidence->lifecycle_status, [EvidenceLifecycleStatus::Committed, EvidenceLifecycleStatus::Deleted], true)) {
                throw ValidationException::withMessages(['evidence' => 'This screenshot cannot receive another review.']);
            }

            $duplicate = AllianceRosterEvidenceReview::query()
                ->where('alliance_id', $allianceId)
                ->where('semantic_fingerprint', $fingerprint)
                ->where('evidence_id', '!=', $evidenceId)
                ->where('status', EvidenceReviewStatus::Approved->value)
                ->latest('reviewed_at')
                ->first();

            $status = $duplicate instanceof AllianceRosterEvidenceReview && ! $allowSemanticDuplicate
                ? EvidenceReviewStatus::DuplicateBlocked
                : EvidenceReviewStatus::Approved;
            $revision = ((int) AllianceRosterEvidenceReview::query()->where('evidence_id', $evidenceId)->max('revision_number')) + 1;

            $review = AllianceRosterEvidenceReview::query()->create([
                'evidence_id' => $evidenceId,
                'alliance_id' => $allianceId,
                'schema_version' => 'alliance-roster-v1',
                'revision_number' => $revision,
                'status' => $status,
                'captured_at' => $captured,
                'payload' => ['complete_roster' => $completeRoster, 'rows' => $normalized],
                'semantic_fingerprint' => $fingerprint,
                'semantic_duplicate_review_id' => $duplicate?->id,
                'duplicate_resolution' => $duplicate instanceof AllianceRosterEvidenceReview && $allowSemanticDuplicate
                    ? 'Reviewer confirmed this is a distinct dated observation.'
                    : null,
                'reviewed_by_player_id' => $actorPlayerId,
                'reviewed_at' => now(),
            ]);

            $evidence->forceFill([
                'lifecycle_status' => $status === EvidenceReviewStatus::Approved
                    ? EvidenceLifecycleStatus::Approved
                    : EvidenceLifecycleStatus::NeedsReview,
            ])->save();

            $metadata = [
                'evidence_id' => $evidenceId,
                'review_id' => (string) $review->id,
                'revision' => $revision,
                'status' => $status->value,
                'row_count' => count($normalized),
                'complete_roster' => $completeRoster,
                'captured_at' => $captured->toIso8601String(),
                'semantic_duplicate_review_id' => $duplicate?->id,
            ];
            $this->audit->record('evidence.alliance_roster_reviewed', $actor, $evidence, $allianceId, $metadata);
            $this->outbox->record('evidence.alliance_roster_reviewed', $allianceId, $evidence, $metadata);

            return (string) $review->id;
        });
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @return list<array<string, mixed>>
     */
    private function normalizeRows(string $allianceId, array $rows): array
    {
        $rosterEntryIds = [];
        foreach ($rows as $row) {
            $rosterEntryId = isset($row['roster_entry_id']) && $row['roster_entry_id'] !== ''
                ? (string) $row['roster_entry_id']
                : null;
            if ($rosterEntryId !== null) {
                $rosterEntryIds[] = $rosterEntryId;
            }
        }
        $knownRosterEntries = $this->rosterEntries->byIds($allianceId, $rosterEntryIds);

        $normalized = [];
        foreach ($rows as $index => $row) {
            $name = trim((string) ($row['observed_name'] ?? ''));
            if ($name === '') {
                throw ValidationException::withMessages(["rows.{$index}.observed_name" => 'Governor name is required.']);
            }
            $rank = isset($row['observed_rank']) && $row['observed_rank'] !== '' ? strtolower((string) $row['observed_rank']) : null;
            if ($rank !== null && ! in_array($rank, ['r1', 'r2', 'r3', 'r4', 'r5'], true)) {
                throw ValidationException::withMessages(["rows.{$index}.observed_rank" => 'Visible Alliance rank must be R1–R5.']);
            }
            $power = $row['power'] ?? null;
            if ($power !== null && (! is_numeric($power) || (int) $power < 0)) {
                throw ValidationException::withMessages(["rows.{$index}.power" => 'Visible power must be a non-negative integer.']);
            }
            $rosterEntryId = isset($row['roster_entry_id']) && $row['roster_entry_id'] !== '' ? (string) $row['roster_entry_id'] : null;
            if ($rosterEntryId !== null && ! isset($knownRosterEntries[$rosterEntryId])) {
                throw ValidationException::withMessages(["rows.{$index}.roster_entry_id" => 'The linked roster entry is outside this Alliance.']);
            }
            $normalized[] = [
                'observed_name' => $name,
                'game_player_id' => isset($row['game_player_id']) && trim((string) $row['game_player_id']) !== '' ? trim((string) $row['game_player_id']) : null,
                'observed_rank' => $rank,
                'power' => $power === null ? null : (int) $power,
                'roster_entry_id' => $rosterEntryId,
                'source_metadata' => is_array($row['source_metadata'] ?? null) ? $row['source_metadata'] : null,
            ];
        }
        usort($normalized, static fn (array $a, array $b): int => [$a['game_player_id'] ?? '', mb_strtolower($a['observed_name'])] <=> [$b['game_player_id'] ?? '', mb_strtolower($b['observed_name'])]);

        return $normalized;
    }
}
