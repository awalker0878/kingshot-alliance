<?php

declare(strict_types=1);

namespace App\Domain\Kingdoms\Services;

use App\Domain\Alliances\Models\Alliance;
use App\Domain\Kingdoms\Enums\KingdomAllianceDiplomacyState;
use App\Domain\Kingdoms\Enums\TrackedKingdomAllianceState;
use App\Domain\Kingdoms\Models\KingdomAllianceObservation;
use App\Domain\Kingdoms\Models\TrackedKingdomAlliance;
use App\Domain\Kingdoms\Queries\KingdomAllianceIntelligenceQuery;
use App\Domain\Kingdoms\Queries\KingdomAllianceObservationQuery;
use Illuminate\Support\Carbon;

final readonly class KingdomAllianceIntelligence
{
    public const SEVEN_DAY_WINDOW = 7;

    public const THIRTY_DAY_WINDOW = 30;

    public function __construct(
        private KingdomAllianceIntelligenceQuery $query,
        private PowerMath $powerMath,
    ) {}

    /**
     * @param  array{tracking: string, freshness: string, diplomacy: string, sort: string, direction: string}  $filters
     * @return array<string, mixed>
     */
    public function forAlliance(
        Alliance $alliance,
        bool $includePrivate,
        array $filters,
        ?Carbon $asOf = null,
    ): array {
        $asOf ??= now();
        $tracking = $this->query->tracking($alliance);
        $latest = $this->query->latestAccepted($alliance, $tracking, $asOf);
        $previous = $this->query->previousAccepted($alliance, $tracking, $asOf);
        $sevenDay = $this->query->baselines($alliance, $tracking, self::SEVEN_DAY_WINDOW, $asOf);
        $thirtyDay = $this->query->baselines($alliance, $tracking, self::THIRTY_DAY_WINDOW, $asOf);
        $contactDiagnostics = $includePrivate
            ? $this->query->contactDiagnostics($alliance, $tracking, $asOf)
            : [];
        $freshCutoff = $asOf->copy()->subDays(KingdomAllianceObservationQuery::FRESH_DAYS);
        $summary = [
            'activeTrackedAlliances' => 0,
            'observationQuality' => [
                'current' => 0,
                'stale' => 0,
                'missing' => 0,
                'staleAfterDays' => KingdomAllianceObservationQuery::FRESH_DAYS,
            ],
            'diplomacyStates' => $this->emptyDiplomacyCounts(),
            'relationshipsNeedingReview' => 0,
        ];
        $managerSummary = [
            'trackedWithActiveContact' => 0,
            'trackedWithVerificationDue' => 0,
            'verificationStaleAfterDays' => KingdomAllianceIntelligenceQuery::CONTACT_VERIFICATION_STALE_DAYS,
        ];
        $rows = [];

        foreach ($tracking as $entry) {
            $entryId = (string) $entry->id;
            $currentObservation = $latest[$entryId] ?? null;
            $freshness = $this->freshness($currentObservation, $freshCutoff);
            $relationship = $entry->diplomacy;
            $diplomacyState = $relationship?->current_state->value ?? KingdomAllianceDiplomacyState::Unknown->value;
            $needsReview = $relationship !== null
                && (($relationship->review_at !== null && $relationship->review_at->lte($asOf))
                    || ($relationship->expires_at !== null && $relationship->expires_at->lte($asOf)));
            $diagnostics = $contactDiagnostics[$entryId] ?? [
                'active' => 0,
                'verificationDue' => 0,
                'latestVerifiedAt' => null,
            ];

            if ($entry->state === TrackedKingdomAllianceState::Active) {
                $summary['activeTrackedAlliances']++;
                $summary['observationQuality'][$freshness]++;
                $summary['diplomacyStates'][$diplomacyState]++;

                if ($needsReview) {
                    $summary['relationshipsNeedingReview']++;
                }

                if ($includePrivate && $diagnostics['active'] > 0) {
                    $managerSummary['trackedWithActiveContact']++;
                }

                if ($includePrivate && $diagnostics['verificationDue'] > 0) {
                    $managerSummary['trackedWithVerificationDue']++;
                }
            }

            $row = [
                'name' => (string) $entry->kingdomAlliance->current_name,
                'tag' => $entry->kingdomAlliance->current_tag,
                'trackingState' => $entry->state->value,
                'kingdom' => (string) $entry->kingdom->number,
                'contextCurrent' => $alliance->kingdom_id !== null && $alliance->kingdom_id === $entry->kingdom_id,
                'historyUrl' => route('alliance.kingdom-alliances.history', ['tracking' => $entry->id], false),
                'freshness' => $freshness,
                'observationAgeDays' => $currentObservation === null
                    ? null
                    : (int) floor($currentObservation->captured_at->diffInDays($asOf, true)),
                'latestObservation' => $this->observation($currentObservation),
                'priorChange' => $this->comparison($currentObservation, $previous[$entryId] ?? null),
                'sevenDayChange' => $this->comparison($currentObservation, $sevenDay[$entryId] ?? null),
                'thirtyDayChange' => $this->comparison($currentObservation, $thirtyDay[$entryId] ?? null),
                'diplomacy' => [
                    'state' => $diplomacyState,
                    'needsReview' => $needsReview,
                    'effectiveAt' => $relationship?->effective_at->toIso8601String(),
                    'reviewAt' => $relationship?->review_at?->toIso8601String(),
                    'expiresAt' => $relationship?->expires_at?->toIso8601String(),
                ],
            ];

            if ($includePrivate) {
                $row['diplomacyUrl'] = route(
                    'alliance.kingdom-alliances.diplomacy.show',
                    ['tracking' => $entry->id],
                    false,
                );
                $row['contactsUrl'] = route(
                    'alliance.kingdom-alliances.diplomacy.contacts.show',
                    ['tracking' => $entry->id],
                    false,
                );
                $row['contactDiagnostics'] = [
                    'activeContacts' => $diagnostics['active'],
                    'verificationDue' => $diagnostics['verificationDue'],
                    'latestVerifiedAt' => $diagnostics['latestVerifiedAt'],
                    'staleAfterDays' => KingdomAllianceIntelligenceQuery::CONTACT_VERIFICATION_STALE_DAYS,
                ];
            }

            $rows[] = $row;
        }

        $rows = $this->filterRows($rows, $filters);
        $this->sortRows($rows, $filters['sort'], $filters['direction']);

        return [
            'asOf' => $asOf->toIso8601String(),
            'summary' => $summary,
            'managerSummary' => $includePrivate ? $managerSummary : null,
            'windows' => [
                'sevenDay' => [
                    'days' => self::SEVEN_DAY_WINDOW,
                    'oldestDays' => self::SEVEN_DAY_WINDOW * 2,
                ],
                'thirtyDay' => [
                    'days' => self::THIRTY_DAY_WINDOW,
                    'oldestDays' => self::THIRTY_DAY_WINDOW * 2,
                ],
            ],
            'filters' => $filters,
            'rows' => $rows,
        ];
    }

    /** @return array<string, int> */
    private function emptyDiplomacyCounts(): array
    {
        $counts = [];

        foreach (KingdomAllianceDiplomacyState::cases() as $state) {
            $counts[$state->value] = 0;
        }

        return $counts;
    }

    private function freshness(?KingdomAllianceObservation $latest, Carbon $freshCutoff): string
    {
        if ($latest === null) {
            return 'missing';
        }

        return $latest->captured_at->gte($freshCutoff) ? 'current' : 'stale';
    }

    /** @return array{power: string|null, memberCount: int|null, capturedAt: string}|null */
    private function observation(?KingdomAllianceObservation $observation): ?array
    {
        if ($observation === null) {
            return null;
        }

        return [
            'power' => $observation->power === null ? null : (string) $observation->power,
            'memberCount' => $observation->member_count,
            'capturedAt' => $observation->captured_at->toIso8601String(),
        ];
    }

    /**
     * @return array{baselineCapturedAt: string, powerChange: string|null, memberChange: int|null}|null
     */
    private function comparison(
        ?KingdomAllianceObservation $current,
        ?KingdomAllianceObservation $baseline,
    ): ?array {
        if ($current === null || $baseline === null || ! $current->captured_at->gt($baseline->captured_at)) {
            return null;
        }

        return [
            'baselineCapturedAt' => $baseline->captured_at->toIso8601String(),
            'powerChange' => $current->power === null || $baseline->power === null
                ? null
                : $this->powerMath->difference((string) $current->power, (string) $baseline->power),
            'memberChange' => $current->member_count === null || $baseline->member_count === null
                ? null
                : $current->member_count - $baseline->member_count,
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     * @param  array{tracking: string, freshness: string, diplomacy: string, sort: string, direction: string}  $filters
     * @return list<array<string, mixed>>
     */
    private function filterRows(array $rows, array $filters): array
    {
        return array_values(array_filter(
            $rows,
            static function (array $row) use ($filters): bool {
                if ($filters['tracking'] !== 'all' && $row['trackingState'] !== $filters['tracking']) {
                    return false;
                }

                if ($filters['freshness'] !== 'all' && $row['freshness'] !== $filters['freshness']) {
                    return false;
                }

                /** @var array{state: string} $diplomacy */
                $diplomacy = $row['diplomacy'];

                return $filters['diplomacy'] === 'all' || $diplomacy['state'] === $filters['diplomacy'];
            },
        ));
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function sortRows(array &$rows, string $sort, string $direction): void
    {
        usort($rows, function (array $left, array $right) use ($sort, $direction): int {
            $leftValue = $this->sortValue($left, $sort);
            $rightValue = $this->sortValue($right, $sort);

            if ($leftValue === null && $rightValue === null) {
                return strcasecmp((string) $left['name'], (string) $right['name']);
            }

            if ($leftValue === null) {
                return 1;
            }

            if ($rightValue === null) {
                return -1;
            }

            $comparison = is_int($leftValue) && is_int($rightValue)
                ? $leftValue <=> $rightValue
                : strcasecmp((string) $leftValue, (string) $rightValue);

            if ($comparison === 0) {
                $comparison = strcasecmp((string) $left['name'], (string) $right['name']);
            }

            return $direction === 'desc' ? -$comparison : $comparison;
        });
    }

    /** @param array<string, mixed> $row */
    private function sortValue(array $row, string $sort): string|int|null
    {
        /** @var array{power: string|null, memberCount: int|null, capturedAt: string}|null $latest */
        $latest = $row['latestObservation'];
        /** @var array{state: string} $diplomacy */
        $diplomacy = $row['diplomacy'];

        return match ($sort) {
            'tag' => is_string($row['tag']) ? $row['tag'] : null,
            'power' => $latest === null || $latest['power'] === null ? null : (int) $latest['power'],
            'members' => $latest['memberCount'] ?? null,
            'age' => is_int($row['observationAgeDays']) ? $row['observationAgeDays'] : null,
            'diplomacy' => $diplomacy['state'],
            default => (string) $row['name'],
        };
    }
}
