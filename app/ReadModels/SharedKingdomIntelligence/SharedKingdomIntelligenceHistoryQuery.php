<?php

declare(strict_types=1);

namespace App\ReadModels\SharedKingdomIntelligence;

use App\Contexts\Alliance\Lifecycle\Enums\AllianceStatus;
use App\Contexts\Alliance\Lifecycle\Models\Alliance;
use App\Contexts\Intelligence\Observations\Enums\TrackedKingdomAllianceState;
use App\Contexts\Intelligence\Observations\Models\KingdomAllianceObservation;
use App\Contexts\Intelligence\Observations\Queries\KingdomAllianceObservationQuery;
use App\Contexts\Intelligence\Sharing\Enums\KingdomIntelligenceShareState;
use App\Contexts\Intelligence\Sharing\Enums\KingdomIntelligenceShareTargetState;
use App\Contexts\Intelligence\Sharing\Models\KingdomIntelligenceShareTarget;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final readonly class SharedKingdomIntelligenceHistoryQuery
{
    public const DEFAULT_PAGE_SIZE = 50;

    public const MAX_PAGE_SIZE = 50;

    public const HISTORY_LIMIT = KingdomAllianceObservationQuery::HISTORY_LIMIT;

    public function __construct(
        private SharedKingdomIntelligenceHistoryCursor $cursors,
    ) {}

    /**
     * @return array{
     *   shareTargetId: string,
     *   sourceAlliance: array{id: string, name: string},
     *   gameAlliance: array{name: string, tag: string|null},
     *   items: list<array{
     *     observedName: string,
     *     observedTag: string|null,
     *     power: string|null,
     *     memberCount: int|null,
     *     capturedAt: string,
     *     freshness: 'current'|'stale'
     *   }>,
     *   nextCursor: string|null
     * }
     */
    public function forRecipientTarget(
        Alliance $recipientAlliance,
        string $shareTargetId,
        ?string $cursor = null,
        int $pageSize = self::DEFAULT_PAGE_SIZE,
        ?Carbon $asOf = null,
    ): array {
        $target = DB::table('kingdom_intelligence_share_targets as targets')
            ->join(
                'kingdom_intelligence_shares as shares',
                'shares.id',
                '=',
                'targets.kingdom_intelligence_share_id',
            )
            ->join('alliances as recipient_alliances', 'recipient_alliances.id', '=', 'shares.recipient_alliance_id')
            ->join('alliances as source_alliances', 'source_alliances.id', '=', 'shares.source_alliance_id')
            ->join(
                'tracked_kingdom_alliances as tracking',
                'tracking.id',
                '=',
                'targets.tracked_kingdom_alliance_id',
            )
            ->join('kingdom_alliances as game_alliances', 'game_alliances.id', '=', 'tracking.kingdom_alliance_id')
            ->where('targets.id', $shareTargetId)
            ->where('targets.state', KingdomIntelligenceShareTargetState::Active->value)
            ->where('shares.state', KingdomIntelligenceShareState::Active->value)
            ->where('shares.recipient_alliance_id', $recipientAlliance->id)
            ->where('recipient_alliances.status', AllianceStatus::Active->value)
            ->whereColumn('recipient_alliances.kingdom_id', 'shares.kingdom_id')
            ->where('source_alliances.status', AllianceStatus::Active->value)
            ->whereColumn('source_alliances.kingdom_id', 'shares.kingdom_id')
            ->whereColumn('tracking.alliance_id', 'shares.source_alliance_id')
            ->whereColumn('tracking.kingdom_id', 'shares.kingdom_id')
            ->where('tracking.state', TrackedKingdomAllianceState::Active->value)
            ->whereColumn('game_alliances.kingdom_id', 'shares.kingdom_id')
            ->select([
                'targets.id as share_target_id',
                'source_alliances.id as source_alliance_id',
                'source_alliances.name as source_alliance_name',
                'tracking.id as tracking_id',
                'game_alliances.current_name as current_name',
                'game_alliances.current_tag as current_tag',
            ])
            ->first();

        if ($target === null) {
            throw (new ModelNotFoundException)->setModel(KingdomIntelligenceShareTarget::class, [$shareTargetId]);
        }

        $pageSize = max(1, min(self::MAX_PAGE_SIZE, $pageSize));
        $seen = 0;
        $cursorCapturedAt = null;
        $cursorObservationId = null;

        if ($cursor !== null) {
            $decoded = $this->cursors->decode($cursor, $shareTargetId);
            $asOf = $decoded['as_of'];
            $cursorCapturedAt = $decoded['captured_at'];
            $cursorObservationId = $decoded['observation_id'];
            $seen = $decoded['seen'];
        } else {
            $asOf = ($asOf ?? now())->copy()->utc();
        }

        $remaining = max(0, self::HISTORY_LIMIT - $seen);
        $take = min($pageSize, $remaining);
        $rows = collect();

        if ($take > 0) {
            $rows = KingdomAllianceObservation::query()
                ->select([
                    'id',
                    'observed_name',
                    'observed_tag',
                    'power',
                    'member_count',
                    'captured_at',
                ])
                ->where('alliance_id', (string) $target->source_alliance_id)
                ->where('tracked_kingdom_alliance_id', (string) $target->tracking_id)
                ->whereNull('invalidated_at')
                ->where('captured_at', '<=', $asOf)
                ->when(
                    $cursorCapturedAt instanceof Carbon && $cursorObservationId !== null,
                    function (Builder $query) use ($cursorCapturedAt, $cursorObservationId): void {
                        $query->where(function (Builder $query) use ($cursorCapturedAt, $cursorObservationId): void {
                            $query
                                ->where('captured_at', '<', $cursorCapturedAt)
                                ->orWhere(function (Builder $query) use ($cursorCapturedAt, $cursorObservationId): void {
                                    $query
                                        ->where('captured_at', '=', $cursorCapturedAt)
                                        ->where('id', '<', $cursorObservationId);
                                });
                        });
                    },
                )
                ->orderByDesc('captured_at')
                ->orderByDesc('id')
                ->limit($take + 1)
                ->get();
        }

        $hasMore = $rows->count() > $take && ($seen + $take) < self::HISTORY_LIMIT;
        $page = $rows->take($take)->values();
        $freshCutoff = $asOf->copy()->subDays(KingdomAllianceObservationQuery::FRESH_DAYS);
        $items = [];

        /** @var KingdomAllianceObservation $observation */
        foreach ($page as $observation) {
            $items[] = [
                'observedName' => (string) $observation->observed_name,
                'observedTag' => $observation->observed_tag,
                'power' => $observation->power === null ? null : (string) $observation->power,
                'memberCount' => $observation->member_count,
                'capturedAt' => $observation->captured_at->toIso8601String(),
                'freshness' => $observation->captured_at->gte($freshCutoff) ? 'current' : 'stale',
            ];
        }

        $nextCursor = null;
        $last = $page->last();
        if ($hasMore && $last instanceof KingdomAllianceObservation) {
            $nextCursor = $this->cursors->encode(
                $shareTargetId,
                $asOf,
                $last->captured_at,
                (string) $last->id,
                $seen + $page->count(),
            );
        }

        return [
            'shareTargetId' => (string) $target->share_target_id,
            'sourceAlliance' => [
                'id' => (string) $target->source_alliance_id,
                'name' => (string) $target->source_alliance_name,
            ],
            'gameAlliance' => [
                'name' => (string) $target->current_name,
                'tag' => $target->current_tag === null ? null : (string) $target->current_tag,
            ],
            'items' => $items,
            'nextCursor' => $nextCursor,
        ];
    }
}
