<?php

declare(strict_types=1);

namespace App\Contexts\Operations\Results\Queries;

use App\Contexts\GameWorld\Players\Queries\PlayerReferenceQuery;
use App\Contexts\Operations\Results\Enums\BearHuntBattleReportStatus;
use App\Contexts\Operations\Results\Models\BearHuntBattleReport;
use App\Contexts\Operations\Results\Models\EventPlayerResult;
use App\Contexts\Operations\Results\Services\ResultScoreTotal;
use App\Shared\Infrastructure\Pagination\ScopedCursorCodec;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

final readonly class BearHuntDebriefResultQuery
{
    public function __construct(private PlayerReferenceQuery $players, private ScopedCursorCodec $cursors) {}

    /**
     * Facts for a currently authorized occurrence. Damage uses projected scores,
     * including preserved manual baselines; it never re-sums accepted OCR rows.
     *
     * @return array<string,mixed>
     */
    public function forOccurrence(string $occurrenceId, string $actorId, ?string $cursor = null): array
    {
        $query = EventPlayerResult::query()->where('occurrence_id', $occurrenceId)->whereNotNull('score');
        $summary = (clone $query)->toBase()->selectRaw('COUNT(*) AS governor_count, SUM(score) AS total_damage')->first();
        $total = (int) ($summary->governor_count ?? 0);
        $damage = ResultScoreTotal::fromDatabase($summary->total_damage ?? null);
        $scope = 'operations.debrief.results.v1:'.$actorId.':'.$occurrenceId;
        $through = $cursor === null ? (clone $query)->max('id') : null;
        if ($cursor !== null) {
            $position = $this->cursors->decode($cursor, $scope);
            $after = $position['after'] ?? null;
            $rank = $position['rank'] ?? null;
            $score = $position['score'] ?? null;
            $through = $position['through'] ?? null;
            if (count($position) !== 4 || ! array_key_exists('rank', $position)
                || ! is_string($after) || ! Str::isUlid($after) || ! is_string($through) || ! Str::isUlid($through)
                || ($rank !== null && (! is_int($rank) || $rank < 1 || $rank > 2147483647))
                || ! is_int($score) || $score < 0) {
                throw ValidationException::withMessages(['cursor' => 'The Debrief result cursor is invalid.']);
            }
            $query->where(static function (Builder $query) use ($rank, $score, $after): void {
                $sameRank = static function (Builder $equal) use ($rank, $score, $after): void {
                    $equal->where('rank', $rank)->where(static fn (Builder $scores) => $scores
                        ->where('score', '<', $score)->orWhere(static fn (Builder $tie) => $tie
                        ->where('score', $score)->where('player_id', '>', $after)));
                };
                if ($rank === null) {
                    $sameRank($query);
                } else {
                    $query->where('rank', '>', $rank)->orWhereNull('rank')->orWhere($sameRank);
                }
            });
        }
        $rows = $through === null ? new Collection : $query->where('id', '<=', $through)
            ->orderByRaw('rank IS NULL')->orderBy('rank')->orderByDesc('score')->orderBy('player_id')->limit(26)->get();
        $page = $rows->take(25)->values();
        $last = $page->last();
        $next = $rows->count() > 25 && $last instanceof EventPlayerResult
            ? $this->cursors->encode($scope, ['after' => (string) $last->player_id, 'rank' => $last->rank,
                'score' => $last->score, 'through' => $through]) : null;
        // Personal facts remain independently addressable outside the visible page.
        $personal = EventPlayerResult::query()->where('occurrence_id', $occurrenceId)
            ->where('player_id', $actorId)->whereNotNull('score')->first();
        $displayed = $page->keyBy('player_id');
        if ($personal instanceof EventPlayerResult) {
            $displayed->put($actorId, $personal);
        }
        $ids = array_values($displayed->keys()->map(static fn ($id): string => (string) $id)->all());
        $references = $this->players->byIds($ids);
        $contributions = DB::table('bear_hunt_battle_report_entries as entry')
            ->join('bear_hunt_battle_reports as report', 'report.id', '=', 'entry.report_id')
            ->where('report.occurrence_id', $occurrenceId)->where('report.status', BearHuntBattleReportStatus::Accepted->value)
            ->whereIn('entry.player_id', $ids)->groupBy('entry.player_id')
            ->selectRaw('entry.player_id, COUNT(*) AS aggregate')->pluck('aggregate', 'entry.player_id');
        $facts = $displayed->map(function (EventPlayerResult $result) use ($references, $contributions): array {
            $id = (string) $result->player_id;
            $reference = $references[$id] ?? null;

            return ['playerId' => $id, 'playerName' => $reference?->currentName,
                'damage' => ResultScoreTotal::fromDatabase($result->score), 'rank' => $result->rank === null ? null : (int) $result->rank,
                'acceptedReportCount' => (int) ($contributions[$id] ?? 0), 'recordedAt' => $this->iso($result->recorded_at)];
        });

        return ['available' => $total > 0,
            'acceptedReportCount' => BearHuntBattleReport::query()->where('occurrence_id', $occurrenceId)
                ->where('status', BearHuntBattleReportStatus::Accepted->value)->count(),
            'totalDamage' => $damage, 'governorCount' => $total,
            'governors' => array_values($facts->only($page->pluck('player_id')->all())->values()->all()),
            'personal' => $facts->get($actorId),
            'governorPage' => ['nextCursor' => $next, 'hasMore' => $next !== null,
                'pageSize' => 25, 'isFirstPage' => $cursor === null, 'total' => $total]];
    }

    private function iso(mixed $value): ?string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format(DATE_ATOM);
        }

        return is_string($value) && $value !== '' ? $value : null;
    }
}
