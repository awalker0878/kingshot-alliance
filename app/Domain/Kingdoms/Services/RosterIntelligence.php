<?php

declare(strict_types=1);

namespace App\Domain\Kingdoms\Services;

use App\Domain\Alliances\Models\Alliance;
use App\Domain\Kingdoms\Enums\RosterState;
use App\Domain\Kingdoms\Models\AllianceRosterEntry;
use App\Domain\Kingdoms\Models\PlayerSnapshot;
use App\Domain\Kingdoms\Queries\PlayerSnapshotQuery;
use App\Domain\Kingdoms\Queries\RosterQuery;
use App\Domain\Memberships\Enums\MembershipStatus;
use App\Domain\Memberships\Models\AllianceMembership;
use Illuminate\Support\Carbon;

final readonly class RosterIntelligence
{
    public const RECENT_ROSTER_DAYS = 7;

    public function __construct(
        private RosterQuery $roster,
        private PlayerSnapshotQuery $snapshots,
        private PowerMath $powerMath,
    ) {}

    /** @return array<string, mixed> */
    public function forAlliance(Alliance $alliance, ?Carbon $asOf = null): array
    {
        $asOf ??= now();
        $entries = $this->roster->forAlliance($alliance);
        $tracked = $entries
            ->filter(static fn (AllianceRosterEntry $entry): bool => in_array(
                $entry->state,
                [RosterState::Active, RosterState::Tracked],
                true,
            ))
            ->values();
        $linkedPlayerIds = AllianceMembership::query()
            ->where('alliance_id', $alliance->id)
            ->where('status', MembershipStatus::Active->value)
            ->pluck('player_id')
            ->map(static fn ($id): string => (string) $id)
            ->flip();
        $latest = $this->snapshots->latestForEntries($alliance, $tracked);
        $sevenDayBaselines = $this->snapshots->baselinesForEntries($alliance, $tracked, 7, $asOf);
        $thirtyDayBaselines = $this->snapshots->baselinesForEntries($alliance, $tracked, 30, $asOf);
        $staleCutoff = $asOf->copy()->subDays(PlayerSnapshotQuery::STALE_AFTER_DAYS);
        $recentCutoff = $asOf->copy()->subDays(self::RECENT_ROSTER_DAYS);
        $powers = [];
        $linked = 0;
        $stale = 0;
        $missing = 0;
        $current = 0;
        $recentJoins = 0;
        $sevenDayChange = '0';
        $thirtyDayChange = '0';
        $sevenDayComparable = 0;
        $thirtyDayComparable = 0;
        $comparisons = [];

        foreach ($tracked as $entry) {
            $membershipLinked = $linkedPlayerIds->has((string) $entry->player_id);
            if ($membershipLinked) {
                $linked++;
            }

            if ($entry->joined_at !== null && $entry->joined_at->greaterThanOrEqualTo($recentCutoff->copy()->startOfDay())) {
                $recentJoins++;
            }

            $entryId = (string) $entry->id;
            $snapshot = $latest[$entryId] ?? null;
            $snapshotState = 'missing';

            if ($snapshot === null) {
                $missing++;
            } else {
                $powers[] = (string) $snapshot->power;

                if ($snapshot->captured_at->lt($staleCutoff)) {
                    $snapshotState = 'stale';
                    $stale++;
                } else {
                    $snapshotState = 'current';
                    $current++;
                }
            }

            $sevenDay = $this->comparison($snapshot, $sevenDayBaselines[$entryId] ?? null);
            if ($sevenDay !== null) {
                $sevenDayChange = $this->powerMath->addSigned($sevenDayChange, $sevenDay['change']);
                $sevenDayComparable++;
            }

            $thirtyDay = $this->comparison($snapshot, $thirtyDayBaselines[$entryId] ?? null);
            if ($thirtyDay !== null) {
                $thirtyDayChange = $this->powerMath->addSigned($thirtyDayChange, $thirtyDay['change']);
                $thirtyDayComparable++;
            }

            $comparisons[] = [
                'entryId' => $entryId,
                'name' => (string) $entry->observed_name,
                'state' => $entry->state->value,
                'membershipLinked' => $membershipLinked,
                'snapshotState' => $snapshotState,
                'current' => $snapshot === null ? null : [
                    'power' => (string) $snapshot->power,
                    'capturedAt' => $snapshot->captured_at->toIso8601String(),
                ],
                'sevenDay' => $sevenDay,
                'thirtyDay' => $thirtyDay,
            ];
        }

        usort(
            $comparisons,
            static fn (array $left, array $right): int => strcasecmp((string) $left['name'], (string) $right['name']),
        );

        $recentDepartures = $entries
            ->filter(static fn (AllianceRosterEntry $entry): bool => $entry->state === RosterState::Left)
            ->filter(static fn (AllianceRosterEntry $entry): bool => $entry->left_at !== null && $entry->left_at->greaterThanOrEqualTo($recentCutoff))
            ->count();
        $trackedCount = $tracked->count();
        $recordedCount = count($powers);

        return [
            'asOf' => $asOf->toIso8601String(),
            'trackedPlayers' => $trackedCount,
            'recordedPowerPlayers' => $recordedCount,
            'totalPower' => $recordedCount === 0 ? null : $this->powerMath->sum($powers),
            'averagePower' => $this->powerMath->averageRounded($powers),
            'medianPower' => $this->powerMath->median($powers),
            'snapshotQuality' => [
                'current' => $current,
                'stale' => $stale,
                'missing' => $missing,
                'staleAfterDays' => PlayerSnapshotQuery::STALE_AFTER_DAYS,
            ],
            'recentRoster' => [
                'days' => self::RECENT_ROSTER_DAYS,
                'joins' => $recentJoins,
                'departures' => $recentDepartures,
            ],
            'linkage' => [
                'linked' => $linked,
                'total' => $trackedCount,
                'percent' => $this->percent($linked, $trackedCount),
            ],
            'sevenDayTrend' => [
                'days' => 7,
                'change' => $sevenDayComparable === 0 ? null : $sevenDayChange,
                'comparablePlayers' => $sevenDayComparable,
            ],
            'thirtyDayTrend' => [
                'days' => 30,
                'change' => $thirtyDayComparable === 0 ? null : $thirtyDayChange,
                'comparablePlayers' => $thirtyDayComparable,
            ],
            'comparisons' => $comparisons,
        ];
    }

    /**
     * @return array{
     *   baselinePower: string,
     *   baselineCapturedAt: string,
     *   currentPower: string,
     *   currentCapturedAt: string,
     *   change: string
     * }|null
     */
    private function comparison(?PlayerSnapshot $current, ?PlayerSnapshot $baseline): ?array
    {
        if ($current === null || $baseline === null || ! $current->captured_at->gt($baseline->captured_at)) {
            return null;
        }

        $currentPower = (string) $current->power;
        $baselinePower = (string) $baseline->power;

        return [
            'baselinePower' => $baselinePower,
            'baselineCapturedAt' => $baseline->captured_at->toIso8601String(),
            'currentPower' => $currentPower,
            'currentCapturedAt' => $current->captured_at->toIso8601String(),
            'change' => $this->powerMath->difference($currentPower, $baselinePower),
        ];
    }

    private function percent(int $numerator, int $denominator): ?string
    {
        if ($denominator === 0) {
            return null;
        }

        $tenths = intdiv(($numerator * 1000) + intdiv($denominator, 2), $denominator);

        return intdiv($tenths, 10).'.'.($tenths % 10);
    }
}
