<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Evidence\Queries;

use App\Contexts\Intelligence\Evidence\Enums\EvidenceLifecycleStatus;
use App\Contexts\Intelligence\Evidence\Models\EvidenceExtractedField;
use App\Contexts\Intelligence\Evidence\Models\EvidenceExtractionAttempt;
use App\Contexts\Intelligence\Evidence\Models\GameEvidence;
use App\Contexts\Operations\Results\Queries\BearHuntEvidenceTargetQuery;
use Illuminate\Support\Collection;

final readonly class BearHuntUnmatchedGovernorQuery
{
    private const MAX_EVIDENCE = 50;

    public function __construct(private BearHuntEvidenceTargetQuery $targets) {}

    /**
     * Return the bounded review queue for Bear Hunt Evidence that still requires
     * human matching/review. Calling this query requires current manager authority;
     * it never exposes another Alliance's Evidence.
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

        $queue = [];
        foreach ($items as $evidence) {
            $attempt = EvidenceExtractionAttempt::query()
                ->where('evidence_id', $evidence->id)
                ->orderByDesc('created_at')
                ->first();
            if (! $attempt instanceof EvidenceExtractionAttempt) {
                continue;
            }

            $fields = EvidenceExtractedField::query()
                ->where('extraction_attempt_id', $attempt->id)
                ->where('row_ordinal', '>', 0)
                ->orderBy('row_ordinal')
                ->orderBy('field_key')
                ->get()
                ->groupBy('row_ordinal');

            $rows = [];
            foreach ($fields as $ordinal => $rowFields) {
                if (! $rowFields instanceof Collection) {
                    continue;
                }
                $byKey = $rowFields->keyBy('field_key');
                $name = $byKey->get('player_name');
                $rank = $byKey->get('rank');
                $damage = $byKey->get('damage');
                $confidenceValues = $rowFields->pluck('confidence')->filter(static fn ($value): bool => is_numeric($value));

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

            $queue[] = [
                'evidenceId' => (string) $evidence->id,
                'receivedAt' => $evidence->created_at?->toIso8601String(),
                'reviewHref' => '/events/'.$target->occurrenceId.'/screenshot-intake#evidence-'.(string) $evidence->id,
                'rows' => $rows,
            ];
        }

        return $queue;
    }
}
