<?php

declare(strict_types=1);

namespace App\ReadModels\KingdomIntelligence;

use App\Contexts\Intelligence\Diplomacy\Models\KingdomAllianceDiplomacyContact;
use App\Contexts\Intelligence\Observations\Models\KingdomAllianceObservation;
use DateTimeInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

final class KingdomAllianceIntelligenceQuery
{
    public const CONTACT_VERIFICATION_STALE_DAYS = 30;

    /** @return Collection<int, KingdomAllianceTrackingRow> */
    public function tracking(string $allianceId): Collection
    {
        return DB::table('tracked_kingdom_alliances as tracking')
            ->join('kingdom_alliances as game_alliances', 'game_alliances.id', '=', 'tracking.kingdom_alliance_id')
            ->join('kingdoms', 'kingdoms.id', '=', 'tracking.kingdom_id')
            ->leftJoin(
                'kingdom_alliance_diplomacy_relationships as diplomacy',
                'diplomacy.tracked_kingdom_alliance_id',
                '=',
                'tracking.id',
            )
            ->where('tracking.alliance_id', $allianceId)
            ->select([
                'tracking.id',
                'tracking.kingdom_alliance_id',
                'tracking.kingdom_id',
                'tracking.state',
                'game_alliances.current_name',
                'game_alliances.current_tag',
                'kingdoms.number as kingdom_number',
                'diplomacy.current_state as diplomacy_state',
                'diplomacy.effective_at as diplomacy_effective_at',
                'diplomacy.review_at as diplomacy_review_at',
                'diplomacy.expires_at as diplomacy_expires_at',
            ])
            ->orderBy('game_alliances.current_name')
            ->orderBy('tracking.id')
            ->get()
            ->map(static fn (object $row): KingdomAllianceTrackingRow => new KingdomAllianceTrackingRow(
                id: (string) $row->id,
                kingdomAllianceId: (string) $row->kingdom_alliance_id,
                kingdomId: (string) $row->kingdom_id,
                state: (string) $row->state,
                currentName: (string) $row->current_name,
                currentTag: $row->current_tag === null ? null : (string) $row->current_tag,
                kingdomNumber: (int) $row->kingdom_number,
                diplomacyState: $row->diplomacy_state === null ? null : (string) $row->diplomacy_state,
                diplomacyEffectiveAt: self::carbon($row->diplomacy_effective_at),
                diplomacyReviewAt: self::carbon($row->diplomacy_review_at),
                diplomacyExpiresAt: self::carbon($row->diplomacy_expires_at),
            ));
    }

    /**
     * @param  iterable<int, KingdomAllianceTrackingRow>  $tracking
     * @return array<string, KingdomAllianceObservation>
     */
    public function latestAccepted(string $allianceId, iterable $tracking, Carbon $asOf): array
    {
        $trackingIds = $this->trackingIds($tracking);
        if ($trackingIds === []) {
            return [];
        }

        $observations = KingdomAllianceObservation::query()
            ->where('alliance_id', $allianceId)
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
            ->get();

        return $this->byTracking($observations);
    }

    /**
     * @param  iterable<int, KingdomAllianceTrackingRow>  $tracking
     * @return array<string, KingdomAllianceObservation>
     */
    public function previousAccepted(string $allianceId, iterable $tracking, Carbon $asOf): array
    {
        $trackingIds = $this->trackingIds($tracking);
        if ($trackingIds === []) {
            return [];
        }

        $observations = KingdomAllianceObservation::query()
            ->where('alliance_id', $allianceId)
            ->whereIn('tracked_kingdom_alliance_id', $trackingIds)
            ->whereNull('invalidated_at')
            ->where('captured_at', '<=', $asOf)
            ->whereRaw(
                'kingdom_alliance_observations.id = (select prior.id from kingdom_alliance_observations as prior '
                .'where prior.alliance_id = kingdom_alliance_observations.alliance_id '
                .'and prior.tracked_kingdom_alliance_id = kingdom_alliance_observations.tracked_kingdom_alliance_id '
                .'and prior.invalidated_at is null and prior.captured_at <= ? '
                .'order by prior.captured_at desc, prior.id desc offset 1 limit 1)',
                [$asOf],
            )
            ->get();

        return $this->byTracking($observations);
    }

    /**
     * @param  iterable<int, KingdomAllianceTrackingRow>  $tracking
     * @return array<string, KingdomAllianceObservation>
     */
    public function baselines(
        string $allianceId,
        iterable $tracking,
        int $days,
        Carbon $asOf,
    ): array {
        $trackingIds = $this->trackingIds($tracking);
        if ($trackingIds === []) {
            return [];
        }

        $target = $asOf->copy()->subDays($days);
        $oldest = $asOf->copy()->subDays($days * 2);
        $observations = KingdomAllianceObservation::query()
            ->where('alliance_id', $allianceId)
            ->whereIn('tracked_kingdom_alliance_id', $trackingIds)
            ->whereNull('invalidated_at')
            ->whereBetween('captured_at', [$oldest, $target])
            ->whereRaw(
                'kingdom_alliance_observations.id = (select baseline.id from kingdom_alliance_observations as baseline '
                .'where baseline.alliance_id = kingdom_alliance_observations.alliance_id '
                .'and baseline.tracked_kingdom_alliance_id = kingdom_alliance_observations.tracked_kingdom_alliance_id '
                .'and baseline.invalidated_at is null and baseline.captured_at <= ? and baseline.captured_at >= ? '
                .'order by baseline.captured_at desc, baseline.id desc limit 1)',
                [$target, $oldest],
            )
            ->get();

        return $this->byTracking($observations);
    }

    /**
     * @param  iterable<int, KingdomAllianceTrackingRow>  $tracking
     * @return array<string, array{active:int,verificationDue:int,latestVerifiedAt:string|null}>
     */
    public function contactDiagnostics(string $allianceId, iterable $tracking, Carbon $asOf): array
    {
        $trackingIds = $this->trackingIds($tracking);
        if ($trackingIds === []) {
            return [];
        }

        $cutoff = $asOf->copy()->subDays(self::CONTACT_VERIFICATION_STALE_DAYS);
        $rows = KingdomAllianceDiplomacyContact::query()
            ->select('tracked_kingdom_alliance_id')
            ->selectRaw("count(*) filter (where state = 'active') as active_contact_count")
            ->selectRaw(
                "count(*) filter (where state = 'active' and (last_verified_at is null or last_verified_at < ?)) as verification_due_count",
                [$cutoff],
            )
            ->selectRaw("max(last_verified_at) filter (where state = 'active') as latest_verified_at")
            ->where('alliance_id', $allianceId)
            ->whereIn('tracked_kingdom_alliance_id', $trackingIds)
            ->groupBy('tracked_kingdom_alliance_id')
            ->get();

        $diagnostics = [];
        foreach ($rows as $row) {
            $trackingId = (string) $row->getAttribute('tracked_kingdom_alliance_id');
            $latestVerifiedAt = $row->getAttribute('latest_verified_at');
            $latestVerifiedIso = match (true) {
                $latestVerifiedAt instanceof DateTimeInterface => Carbon::instance($latestVerifiedAt)->toIso8601String(),
                is_string($latestVerifiedAt) => Carbon::parse($latestVerifiedAt)->toIso8601String(),
                default => null,
            };

            $diagnostics[$trackingId] = [
                'active' => (int) $row->getAttribute('active_contact_count'),
                'verificationDue' => (int) $row->getAttribute('verification_due_count'),
                'latestVerifiedAt' => $latestVerifiedIso,
            ];
        }

        return $diagnostics;
    }

    /**
     * @param  iterable<int, KingdomAllianceTrackingRow>  $tracking
     * @return list<string>
     */
    private function trackingIds(iterable $tracking): array
    {
        $ids = [];
        foreach ($tracking as $entry) {
            $ids[] = $entry->id;
        }

        return $ids;
    }

    /**
     * @param  iterable<int, KingdomAllianceObservation>  $observations
     * @return array<string, KingdomAllianceObservation>
     */
    private function byTracking(iterable $observations): array
    {
        $byTracking = [];
        foreach ($observations as $observation) {
            $byTracking[(string) $observation->tracked_kingdom_alliance_id] = $observation;
        }

        return $byTracking;
    }

    private static function carbon(mixed $value): ?Carbon
    {
        if ($value === null) {
            return null;
        }
        if ($value instanceof DateTimeInterface) {
            return Carbon::instance($value);
        }
        if (is_string($value) && $value !== '') {
            return Carbon::parse($value);
        }

        return null;
    }
}
