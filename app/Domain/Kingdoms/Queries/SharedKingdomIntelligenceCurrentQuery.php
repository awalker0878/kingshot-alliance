<?php

declare(strict_types=1);

namespace App\Domain\Kingdoms\Queries;

use App\Contexts\Alliance\Core\Enums\AllianceStatus;
use App\Contexts\Alliance\Core\Models\Alliance;
use App\Contexts\GameWorld\Models\KingdomAllianceObservation;
use App\Domain\Kingdoms\Enums\KingdomIntelligenceShareState;
use App\Domain\Kingdoms\Enums\KingdomIntelligenceShareTargetState;
use App\Contexts\Intelligence\Observations\Enums\TrackedKingdomAllianceState;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

final class SharedKingdomIntelligenceCurrentQuery
{
    public const CURRENT_LIMIT = 250;

    /** @return list<array<string, mixed>> */
    public function forRecipient(Alliance $recipientAlliance, ?Carbon $asOf = null): array
    {
        $asOf ??= now();

        $targets = DB::table('kingdom_intelligence_share_targets as targets')
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
            ->orderBy('source_alliances.name')
            ->orderBy('game_alliances.current_name')
            ->orderBy('targets.id')
            ->limit(self::CURRENT_LIMIT)
            ->get();

        if ($targets->isEmpty()) {
            return [];
        }

        $trackingIds = $targets->pluck('tracking_id')->map(static fn (mixed $id): string => (string) $id)->all();
        $sourceAllianceIds = $targets
            ->pluck('source_alliance_id')
            ->map(static fn (mixed $id): string => (string) $id)
            ->unique()
            ->values()
            ->all();

        $observations = KingdomAllianceObservation::query()
            ->select([
                'id',
                'alliance_id',
                'tracked_kingdom_alliance_id',
                'observed_name',
                'observed_tag',
                'power',
                'member_count',
                'captured_at',
            ])
            ->whereIn('alliance_id', $sourceAllianceIds)
            ->whereIn('tracked_kingdom_alliance_id', $trackingIds)
            ->whereNull('invalidated_at')
            ->where('captured_at', '<=', $asOf)
            ->whereRaw(
                'kingdom_alliance_observations.id = (select latest.id from kingdom_alliance_observations as latest '
                .'where latest.alliance_id = kingdom_alliance_observations.alliance_id '
                .'and latest.tracked_kingdom_alliance_id = kingdom_alliance_observations.tracked_kingdom_alliance_id '
                .'and latest.invalidated_at is null and latest.captured_at <= ? '
                .'order by latest.captured_at desc, latest.id desc limit 1)',
                [$asOf],
            )
            ->get()
            ->keyBy(static fn (KingdomAllianceObservation $observation): string => (string) $observation->tracked_kingdom_alliance_id);

        $freshCutoff = $asOf->copy()->subDays(KingdomAllianceObservationQuery::FRESH_DAYS);
        $rows = [];

        foreach ($targets as $target) {
            $trackingId = (string) $target->tracking_id;
            $sourceAllianceId = (string) $target->source_alliance_id;
            $observation = $observations->get($trackingId);
            if ($observation instanceof KingdomAllianceObservation
                && (string) $observation->alliance_id !== $sourceAllianceId) {
                $observation = null;
            }

            $rows[] = [
                'shareTargetId' => (string) $target->share_target_id,
                'sourceAlliance' => [
                    'id' => $sourceAllianceId,
                    'name' => (string) $target->source_alliance_name,
                ],
                'gameAlliance' => [
                    'name' => (string) $target->current_name,
                    'tag' => $target->current_tag === null ? null : (string) $target->current_tag,
                ],
                'freshness' => match (true) {
                    ! $observation instanceof KingdomAllianceObservation => 'missing',
                    $observation->captured_at->gte($freshCutoff) => 'current',
                    default => 'stale',
                },
                'latestObservation' => $this->observation($observation),
            ];
        }

        return $rows;
    }

    /** @return array<string, mixed>|null */
    private function observation(?KingdomAllianceObservation $observation): ?array
    {
        if (! $observation instanceof KingdomAllianceObservation) {
            return null;
        }

        return [
            'observedName' => (string) $observation->observed_name,
            'observedTag' => $observation->observed_tag,
            'power' => $observation->power === null ? null : (string) $observation->power,
            'memberCount' => $observation->member_count,
            'capturedAt' => $observation->captured_at->toIso8601String(),
        ];
    }
}
