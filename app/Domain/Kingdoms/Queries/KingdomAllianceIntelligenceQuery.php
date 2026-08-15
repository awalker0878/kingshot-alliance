<?php

declare(strict_types=1);

namespace App\Domain\Kingdoms\Queries;

use App\Domain\Alliances\Models\Alliance;
use App\Contexts\GameWorld\Models\KingdomAllianceDiplomacyContact;
use App\Contexts\GameWorld\Models\KingdomAllianceObservation;
use App\Domain\Kingdoms\Models\TrackedKingdomAlliance;
use DateTimeInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Carbon;

final class KingdomAllianceIntelligenceQuery
{
    public const CONTACT_VERIFICATION_STALE_DAYS = 30;

    /** @return Collection<int, TrackedKingdomAlliance> */
    public function tracking(Alliance $alliance): Collection
    {
        return TrackedKingdomAlliance::query()
            ->where('alliance_id', $alliance->id)
            ->with([
                'kingdomAlliance:id,kingdom_id,current_name,current_tag,status',
                'kingdom:id,number,status',
                'diplomacy:id,alliance_id,tracked_kingdom_alliance_id,current_state,effective_at,review_at,expires_at',
            ])
            ->get();
    }

    /**
     * @param  iterable<int, TrackedKingdomAlliance>  $tracking
     * @return array<string, KingdomAllianceObservation>
     */
    public function latestAccepted(Alliance $alliance, iterable $tracking, Carbon $asOf): array
    {
        $trackingIds = $this->trackingIds($tracking);

        if ($trackingIds === []) {
            return [];
        }

        $observations = KingdomAllianceObservation::query()
            ->where('alliance_id', $alliance->id)
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
     * @param  iterable<int, TrackedKingdomAlliance>  $tracking
     * @return array<string, KingdomAllianceObservation>
     */
    public function previousAccepted(Alliance $alliance, iterable $tracking, Carbon $asOf): array
    {
        $trackingIds = $this->trackingIds($tracking);

        if ($trackingIds === []) {
            return [];
        }

        $observations = KingdomAllianceObservation::query()
            ->where('alliance_id', $alliance->id)
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
     * For an N-day comparison, select the closest accepted observation at or before asOf-N days,
     * but not older than asOf-2N days. Newer observations are not substituted and older history is
     * intentionally excluded rather than interpolated.
     *
     * @param  iterable<int, TrackedKingdomAlliance>  $tracking
     * @return array<string, KingdomAllianceObservation>
     */
    public function baselines(
        Alliance $alliance,
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
            ->where('alliance_id', $alliance->id)
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
     * @param  iterable<int, TrackedKingdomAlliance>  $tracking
     * @return array<string, array{active: int, verificationDue: int, latestVerifiedAt: string|null}>
     */
    public function contactDiagnostics(Alliance $alliance, iterable $tracking, Carbon $asOf): array
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
            ->where('alliance_id', $alliance->id)
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
     * @param  iterable<int, TrackedKingdomAlliance>  $tracking
     * @return list<string>
     */
    private function trackingIds(iterable $tracking): array
    {
        $ids = [];

        foreach ($tracking as $entry) {
            $ids[] = (string) $entry->id;
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
}
