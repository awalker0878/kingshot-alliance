<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Evidence\Queries;

use App\Contexts\Intelligence\Evidence\Enums\EvidenceLifecycleStatus;
use App\Contexts\Intelligence\Evidence\Models\EvidenceExtractedField;
use App\Contexts\Intelligence\Evidence\Models\EvidenceExtractionAttempt;
use App\Contexts\Intelligence\Evidence\Models\EvidenceReview;
use App\Contexts\Intelligence\Evidence\Models\GameEvidence;
use App\Contexts\Operations\Results\Queries\BearHuntEvidenceTargetQuery;
use Illuminate\Support\Collection;

final readonly class BearHuntUnmatchedGovernorQuery
{
    private const MAX_EVIDENCE = 50;

    public function __construct(private BearHuntEvidenceTargetQuery $targets) {}

    /**
     * Return the bounded review queue for Bear Hunt Evidence whose latest
     * extraction still requires Governor row matching. Calling this query requires
     * current manager authority and never exposes another Alliance's Evidence.
     *
     * Evidence can remain `needs_review` after Governor matching because a
     * semantic duplicate still needs resolution. A saved review for the latest
     * extraction means its included rows are resolved and excluded rows were
     * intentionally excluded, so that Evidence is not an unmatched-Governor item.
     *
     * @return list<array{
     *   evidenceId:string,
     *   receivedAt:?string,
     *   reviewHref:string,
     *   rows:list<array{ordinal:int,observedName:?string,reportedRank:?int,damage:?int,confidence:?float}>
     * }>
     */
    public function forOccurrence(string $actorPlayerId, string $occurrenceId): array
    {
        $target = $this->targets->authorizeManage($actorPlayerId, $occurrenceId);
        $items = GameEvidence::query()
            ->where('alliance_id', $target->allianceId)
            ->where('occurrence_id', $target->occurrenceId)
            ->where('lifecycle_status', EvidenceLifecycleStatus::NeedsReview->value)
            ->orderByDesc('created_at')
            ->limit(self::MAX_EVIDENCE)
            ->get();
        if ($items->isEmpty()) {
            return [];
        }

        /** @var list<string> $evidenceIds */
        $evidenceIds = $items->pluck('id')
            ->map(static fn ($id): string => (string) $id)
            ->values()
            ->all();

        /** @var array<string,EvidenceExtractionAttempt> $latestAttempts */
        $latestAttempts = [];
        foreach (EvidenceExtractionAttempt::query()
            ->whereIn('evidence_id', $evidenceIds)
            ->orderBy('evidence_id')
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->get() as $attempt) {
            $evidenceId = (string) $attempt->evidence_id;
            $latestAttempts[$evidenceId] ??= $attempt;
        }

        /** @var array<string,EvidenceReview> $latestReviews */
        $latestReviews = [];
        foreach (EvidenceReview::query()
            ->whereIn('evidence_id', $evidenceIds)
            ->orderBy('evidence_id')
            ->orderByDesc('revision_number')
            ->get() as $review) {
            $evidenceId = (string) $review->evidence_id;
            $latestReviews[$evidenceId] ??= $review;
        }

        /** @var list<string> $unreviewedAttemptIds */
        $unreviewedAttemptIds = [];
        foreach ($evidenceIds as $evidenceId) {
            $attempt = $latestAttempts[$evidenceId] ?? null;
            if (! $attempt instanceof EvidenceExtractionAttempt) {
                continue;
            }
            $review = $latestReviews[$evidenceId] ?? null;
            if ($review instanceof EvidenceReview
                && (string) $review->extraction_attempt_id === (string) $attempt->id) {
                continue;
            }
            $unreviewedAttemptIds[] = (string) $attempt->id;
        }
        if ($unreviewedAttemptIds === []) {
            return [];
        }

        $fieldsByAttempt = EvidenceExtractedField::query()
            ->whereIn('extraction_attempt_id', $unreviewedAttemptIds)
            ->where('row_ordinal', '>', 0)
            ->orderBy('extraction_attempt_id')
            ->orderBy('row_ordinal')
            ->orderBy('field_key')
            ->get()
            ->groupBy(static fn (EvidenceExtractedField $field): string => (string) $field->extraction_attempt_id);

        $queue = [];
        foreach ($items as $evidence) {
            $evidenceId = (string) $evidence->id;
            $attempt = $latestAttempts[$evidenceId] ?? null;
            if (! $attempt instanceof EvidenceExtractionAttempt) {
                continue;
            }
            $review = $latestReviews[$evidenceId] ?? null;
            if ($review instanceof EvidenceReview
                && (string) $review->extraction_attempt_id === (string) $attempt->id) {
                continue;
            }

            $attemptFields = $fieldsByAttempt->get((string) $attempt->id);
            if (! $attemptFields instanceof Collection) {
                continue;
            }
            $fields = $attemptFields->groupBy(
                static fn (EvidenceExtractedField $field): int => (int) $field->row_ordinal,
            );

            $rows = [];
            foreach ($fields as $ordinal => $rowFields) {
                if (! $rowFields instanceof Collection) {
                    continue;
                }
                $byKey = $rowFields->keyBy(
                    static fn (EvidenceExtractedField $field): string => (string) $field->field_key,
                );
                $name = $byKey->get('player_name');
                $rank = $byKey->get('rank');
                $damage = $byKey->get('damage');
                $confidenceValues = $rowFields->pluck('confidence')
                    ->filter(static fn ($value): bool => is_numeric($value));

                $rows[] = [
                    'ordinal' => (int) $ordinal,
                    'observedName' => $name instanceof EvidenceExtractedField
                        ? (string) $name->normalized_value
                        : null,
                    'reportedRank' => $rank instanceof EvidenceExtractedField
                        && is_numeric($rank->normalized_value)
                        ? (int) $rank->normalized_value
                        : null,
                    'damage' => $damage instanceof EvidenceExtractedField
                        && is_numeric($damage->normalized_value)
                        ? (int) $damage->normalized_value
                        : null,
                    'confidence' => $confidenceValues->isEmpty()
                        ? null
                        : round((float) $confidenceValues->avg(), 4),
                ];
            }
            if ($rows === []) {
                continue;
            }

            $queue[] = [
                'evidenceId' => $evidenceId,
                'receivedAt' => $evidence->created_at?->toIso8601String(),
                'reviewHref' => '/events/'.$target->occurrenceId.'/screenshot-intake#evidence-'.$evidenceId,
                'rows' => $rows,
            ];
        }

        return $queue;
    }
}
