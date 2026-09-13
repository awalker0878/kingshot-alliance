<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Evidence\Queries;

use App\Contexts\Intelligence\Evidence\Enums\EvidenceLifecycleStatus;
use App\Contexts\Intelligence\Evidence\Models\EvidenceExtractionAttempt;
use App\Contexts\Intelligence\Evidence\Models\GameEvidence;
use App\Contexts\Operations\Results\Queries\BearHuntEvidenceTargetQuery;
use Illuminate\Support\Facades\DB;

final readonly class BearHuntUnmatchedGovernorQuery
{
    private const MAX_EVIDENCE = 50;

    private const MAX_PREVIEW_ROWS = 25;

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
     * Resolved/reviewed Evidence is filtered before the queue bound is applied so
     * duplicate follow-up work cannot starve genuinely unmatched Evidence.
     *
     * @return list<array{
     *   evidenceId:string,
     *   receivedAt:?string,
     *   reviewHref:string,
     *   rowCount:int,
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
            ->whereExists(static function ($query): void {
                $query->selectRaw('1')
                    ->from('evidence_extraction_attempts as unmatched_attempt')
                    ->whereColumn('unmatched_attempt.evidence_id', 'game_evidence.id');
            })
            ->whereNotExists(static function ($query): void {
                $query->selectRaw('1')
                    ->from('evidence_reviews as resolved_review')
                    ->whereColumn('resolved_review.evidence_id', 'game_evidence.id')
                    ->whereRaw(
                        'resolved_review.extraction_attempt_id = ('.
                        'SELECT latest_attempt.id FROM evidence_extraction_attempts AS latest_attempt '.
                        'WHERE latest_attempt.evidence_id = game_evidence.id '.
                        'ORDER BY latest_attempt.created_at DESC, latest_attempt.id DESC LIMIT 1)'
                    );
            })
            ->orderByDesc('created_at')
            ->orderByDesc('id')
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

        $latestAttempts = EvidenceExtractionAttempt::query()
            ->whereIn('evidence_id', $evidenceIds)
            ->selectRaw('DISTINCT ON (evidence_id) id, evidence_id')
            ->orderBy('evidence_id')->orderByDesc('created_at')->orderByDesc('id')
            ->get()->keyBy('evidence_id');
        $attemptIds = $latestAttempts->pluck('id')->all();
        // Aggregate fields before paging rows. Retained attempts, extra fields and
        // long OCR strings never expand the preview's materialized payload.
        $ranked = DB::table('evidence_extracted_fields')->whereIn('extraction_attempt_id', $attemptIds)
            ->where('row_ordinal', '>', 0)->groupBy('extraction_attempt_id', 'row_ordinal')
            ->selectRaw("extraction_attempt_id, row_ordinal,
                LEFT(MAX(CASE WHEN field_key = 'player_name' THEN normalized_value END), 513) AS observed_name,
                LEFT(MAX(CASE WHEN field_key = 'rank' THEN normalized_value END), 32) AS reported_rank,
                LEFT(MAX(CASE WHEN field_key = 'damage' THEN normalized_value END), 32) AS damage,
                AVG(confidence) AS confidence,
                COUNT(*) OVER (PARTITION BY extraction_attempt_id) AS row_count,
                ROW_NUMBER() OVER (PARTITION BY extraction_attempt_id ORDER BY row_ordinal) AS row_position");
        $rowsByAttempt = DB::query()->fromSub($ranked, 'preview')
            ->where('row_position', '<=', self::MAX_PREVIEW_ROWS)
            ->orderBy('extraction_attempt_id')->orderBy('row_ordinal')->get()->groupBy('extraction_attempt_id');

        $queue = [];
        foreach ($items as $evidence) {
            $evidenceId = (string) $evidence->id;
            $attempt = $latestAttempts->get($evidenceId);
            if (! $attempt instanceof EvidenceExtractionAttempt) {
                continue;
            }

            $preview = $rowsByAttempt->get((string) $attempt->id, collect());
            $rows = [];
            foreach ($preview as $row) {
                $name = $row->observed_name;
                $rows[] = [
                    'ordinal' => (int) $row->row_ordinal,
                    'observedName' => is_string($name) ? (mb_strlen($name) > 512 ? mb_substr($name, 0, 512).'…' : $name) : null,
                    'reportedRank' => $this->nonnegativeInteger($row->reported_rank),
                    'damage' => $this->nonnegativeInteger($row->damage),
                    'confidence' => is_numeric($row->confidence) ? round((float) $row->confidence, 4) : null,
                ];
            }
            if ($rows === []) {
                continue;
            }

            $queue[] = [
                'evidenceId' => $evidenceId,
                'receivedAt' => $evidence->created_at?->toIso8601String(),
                'reviewHref' => '/events/'.$target->occurrenceId.'/screenshot-intake?evidence='.$evidenceId.'#evidence-'.$evidenceId,
                'rowCount' => (int) ($preview->first()->row_count ?? 0),
                'rows' => $rows,
            ];
        }

        return $queue;
    }

    private function nonnegativeInteger(mixed $value): ?int
    {
        if (! is_string($value) || preg_match('/^(0|[1-9][0-9]{0,18})$/D', $value) !== 1
            || (strlen($value) === 19 && strcmp($value, (string) PHP_INT_MAX) > 0)) {
            return null;
        }

        return (int) $value;
    }
}
