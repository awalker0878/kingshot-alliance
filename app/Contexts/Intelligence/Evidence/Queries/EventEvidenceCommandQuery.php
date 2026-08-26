<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Evidence\Queries;

use App\Contexts\Intelligence\Evidence\Enums\EvidenceCommitStatus;
use App\Contexts\Intelligence\Evidence\Enums\EvidenceLifecycleStatus;
use App\Contexts\Intelligence\Evidence\Models\EvidenceCommitAttempt;
use App\Contexts\Intelligence\Evidence\Models\GameEvidence;
use App\Contexts\Operations\Results\Queries\BearHuntEvidenceTargetQuery;

final readonly class EventEvidenceCommandQuery
{
    private const MAX_EVIDENCE = 200;

    public function __construct(
        private BearHuntEvidenceTargetQuery $targets,
        private BearHuntUnmatchedGovernorQuery $unmatched,
    ) {}

    /**
     * Bear Hunt is currently the only Event evidence class with a typed destination contract.
     * The target query reauthorizes the concrete occurrence before any private Evidence is read.
     *
     * @return array{
     *     evidenceCount:int,
     *     processingCount:int,
     *     awaitingReviewCount:int,
     *     unmatchedGovernorCount:int,
     *     commitPendingCount:int,
     *     commitFailedCount:int,
     *     processingFailedCount:int,
     *     committedCount:int
     * }
     */
    public function forBearHuntOccurrence(string $actorPlayerId, string $occurrenceId): array
    {
        $target = $this->targets->authorizeManage($actorPlayerId, $occurrenceId);
        $evidence = GameEvidence::query()
            ->where('alliance_id', $target->allianceId)
            ->where('occurrence_id', $target->occurrenceId)
            ->where('lifecycle_status', '!=', EvidenceLifecycleStatus::Deleted->value)
            ->orderByDesc('created_at')
            ->limit(self::MAX_EVIDENCE)
            ->get(['id', 'lifecycle_status']);
        $evidenceIds = $evidence
            ->pluck('id')
            ->map(static fn ($id): string => (string) $id)
            ->all();

        /** @var array<string, EvidenceCommitAttempt> $latestCommitByEvidence */
        $latestCommitByEvidence = [];
        if ($evidenceIds !== []) {
            foreach (EvidenceCommitAttempt::query()
                ->whereIn('evidence_id', $evidenceIds)
                ->orderBy('evidence_id')
                ->orderByDesc('started_at')
                ->orderByDesc('id')
                ->get(['id', 'evidence_id', 'status', 'started_at']) as $attempt) {
                $latestCommitByEvidence[(string) $attempt->evidence_id] ??= $attempt;
            }
        }

        $commitPendingCount = 0;
        $commitFailedCount = 0;
        foreach ($latestCommitByEvidence as $attempt) {
            if ($attempt->status === EvidenceCommitStatus::Pending) {
                $commitPendingCount++;
            }
            if ($attempt->status === EvidenceCommitStatus::Failed) {
                $commitFailedCount++;
            }
        }

        $processingStatuses = [
            EvidenceLifecycleStatus::Uploaded,
            EvidenceLifecycleStatus::Classifying,
            EvidenceLifecycleStatus::Classified,
            EvidenceLifecycleStatus::Extracting,
            EvidenceLifecycleStatus::Approved,
            EvidenceLifecycleStatus::Committing,
        ];
        $unmatchedEvidence = $this->unmatched->forOccurrence($actorPlayerId, $occurrenceId);
        $unmatchedGovernorCount = array_sum(array_map(
            static fn (array $item): int => is_array($item['rows'] ?? null) ? count($item['rows']) : 0,
            $unmatchedEvidence,
        ));
        $countStatus = static fn (EvidenceLifecycleStatus $status): int => $evidence->filter(
            static fn (GameEvidence $item): bool => $item->lifecycle_status === $status,
        )->count();

        return [
            'evidenceCount' => $evidence->count(),
            'processingCount' => $evidence->filter(
                static fn (GameEvidence $item): bool => in_array(
                    $item->lifecycle_status,
                    $processingStatuses,
                    true,
                ),
            )->count(),
            'awaitingReviewCount' => $countStatus(EvidenceLifecycleStatus::NeedsReview),
            'unmatchedGovernorCount' => $unmatchedGovernorCount,
            'commitPendingCount' => $commitPendingCount,
            'commitFailedCount' => $commitFailedCount,
            'processingFailedCount' => $countStatus(EvidenceLifecycleStatus::Failed),
            'committedCount' => $countStatus(EvidenceLifecycleStatus::Committed),
        ];
    }
}
