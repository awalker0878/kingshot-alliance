<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\KingdomTransfers\Queries;

use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferObservationKind;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferSourceType;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferGroup;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferKingdomConditionObservation;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferObservation;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/** Select bounded witnesses without losing current conflicts or provenance. */
final class TransferEligibilityEvidenceQuery
{
    /**
     * @param  array<string,?string>  $participantTargets
     * @return Collection<int,TransferObservation>
     */
    public function observations(string $allianceId, string $planId, array $participantTargets, CarbonImmutable $now): Collection
    {
        if ($participantTargets === []) {
            return collect();
        }
        $sources = $this->authoritativeSources();
        $placeholders = implode(',', array_fill(0, count($sources), '?'));
        $numericKinds = array_map(static fn (TransferObservationKind $kind): string => $kind->value, array_values(array_filter(TransferObservationKind::cases(), static fn (TransferObservationKind $kind): bool => $kind->usesNumericValue())));
        $booleanKinds = array_map(static fn (TransferObservationKind $kind): string => $kind->value, array_values(array_filter(TransferObservationKind::cases(), static fn (TransferObservationKind $kind): bool => $kind->usesBooleanValue())));
        $targetKinds = array_map(static fn (TransferObservationKind $kind): string => $kind->value, array_values(array_filter(TransferObservationKind::cases(), static fn (TransferObservationKind $kind): bool => $kind->requiresTargetKingdom())));
        $base = DB::table('transfer_observations')
            ->where('alliance_id', $allianceId)->where('transfer_plan_id', $planId)
            ->whereIn('kind', array_column(TransferObservationKind::cases(), 'value'))
            ->where(function (Builder $query) use ($participantTargets, $targetKinds): void {
                foreach ($participantTargets as $participantId => $targetId) {
                    $query->orWhere(function (Builder $scope) use ($participantId, $targetId, $targetKinds): void {
                        $scope->where('transfer_participant_id', $participantId)
                            ->where(function (Builder $target) use ($targetId, $targetKinds): void {
                                $target->where(function (Builder $global) use ($targetKinds): void {
                                    $global->whereNotIn('kind', $targetKinds)->whereNull('target_kingdom_id');
                                });
                                if ($targetId !== null) {
                                    $target->orWhere(function (Builder $scoped) use ($targetId, $targetKinds): void {
                                        $scoped->whereIn('kind', $targetKinds)->where('target_kingdom_id', $targetId);
                                    });
                                }
                            });
                    });
                }
            })
            ->select(['id', 'transfer_participant_id', 'kind', 'target_kingdom_id', 'observed_at'])
            ->selectRaw('CASE WHEN source_type IN ('.$placeholders.') THEN CASE WHEN valid_until >= ? THEN 2 ELSE 1 END ELSE 0 END AS authority_class', [...$sources, $now->format('Y-m-d H:i:s.uP')])
            ->selectRaw('CASE WHEN kind IN ('.implode(',', array_fill(0, count($numericKinds), '?')).') THEN to_jsonb(numeric_value) WHEN kind IN ('.implode(',', array_fill(0, count($booleanKinds), '?')).') THEN to_jsonb(boolean_value) ELSE to_jsonb(text_value) END AS fact_value', [...$numericKinds, ...$booleanKinds]);
        $partition = 'transfer_participant_id, kind, target_kingdom_id, authority_class';
        $values = DB::query()->fromSub($base, 'scoped_observations')->select('*')
            ->selectRaw('ROW_NUMBER() OVER (PARTITION BY '.$partition.', fact_value ORDER BY observed_at DESC, id DESC) AS value_rank');
        $distinct = DB::query()->fromSub($values, 'distinct_values')->where('value_rank', 1)->select('*')
            ->selectRaw('ROW_NUMBER() OVER (PARTITION BY '.$partition.' ORDER BY observed_at DESC, id DESC) AS fact_rank');
        $ids = DB::query()->fromSub($distinct, 'witnesses')->select('id')
            ->whereRaw('fact_rank <= CASE WHEN authority_class = 2 THEN 2 ELSE 1 END');

        return TransferObservation::query()->whereIn('id', $ids)
            ->with('targetKingdom:id,number')->orderByDesc('observed_at')->orderByDesc('id')->get();
    }

    /**
     * @param  list<string>  $kingdomIds
     * @return Collection<int,TransferKingdomConditionObservation>
     */
    public function conditions(string $allianceId, string $windowId, array $kingdomIds): Collection
    {
        if ($kingdomIds === []) {
            return collect();
        }
        $sources = $this->authoritativeSources();
        $columns = ['power_cap', 'hero_generation', 'truegold_level', 'character_age_threshold_days'];
        $base = DB::table('transfer_kingdom_condition_observations')
            ->where('alliance_id', $allianceId)->where('transfer_window_id', $windowId)->whereIn('kingdom_id', $kingdomIds)
            ->select(['id', 'kingdom_id', 'observed_at', ...$columns])
            ->selectRaw('source_type IN ('.implode(',', array_fill(0, count($sources), '?')).') AS authoritative', $sources);
        $latest = DB::query()->fromSub($base, 'scoped_conditions')->select('*')
            ->selectRaw('ROW_NUMBER() OVER (PARTITION BY kingdom_id ORDER BY observed_at DESC, id DESC) AS overall_rank')
            ->selectRaw('ROW_NUMBER() OVER (PARTITION BY kingdom_id, authoritative ORDER BY observed_at DESC, id DESC) AS authority_rank')
            ->selectRaw('MAX(observed_at) OVER (PARTITION BY kingdom_id, authoritative) AS latest_at');
        foreach ($columns as $column) {
            $latest->selectRaw('FIRST_VALUE('.$column.') OVER (PARTITION BY kingdom_id, authoritative ORDER BY observed_at DESC, id DESC) AS first_'.$column);
        }
        $witnesses = DB::query()->fromSub($latest, 'latest_conditions')
            ->where(function (Builder $query): void {
                $query->where('overall_rank', 1)->orWhere(function (Builder $authoritative): void {
                    $authoritative->where('authoritative', true)->whereColumn('observed_at', 'latest_at');
                });
            })->select('*');
        foreach ($columns as $column) {
            $witnesses->selectRaw('ROW_NUMBER() OVER (PARTITION BY kingdom_id, authoritative, ('.$column.' IS DISTINCT FROM first_'.$column.') ORDER BY observed_at DESC, id DESC) AS witness_'.$column);
        }
        $ids = DB::query()->fromSub($witnesses, 'condition_witnesses')->select('id')
            ->where(function (Builder $query) use ($columns): void {
                $query->where('overall_rank', 1)->orWhere(function (Builder $authoritative) use ($columns): void {
                    $authoritative->where('authoritative', true)->where(function (Builder $selected) use ($columns): void {
                        $selected->where('authority_rank', 1);
                        foreach ($columns as $column) {
                            $selected->orWhere(function (Builder $conflict) use ($column): void {
                                $conflict->whereRaw($column.' IS DISTINCT FROM first_'.$column)->where('witness_'.$column, 1);
                            });
                        }
                    });
                });
            });

        return TransferKingdomConditionObservation::query()->whereIn('id', $ids)
            ->orderByDesc('observed_at')->orderByDesc('id')->get();
    }

    /**
     * @param  list<string>  $kingdomIds
     * @return array<string,TransferGroup>
     */
    public function groups(string $allianceId, string $windowId, array $kingdomIds): array
    {
        if ($kingdomIds === []) {
            return [];
        }
        $groups = TransferGroup::query()->where('alliance_id', $allianceId)->where('transfer_window_id', $windowId)
            ->whereNull('superseded_at')
            ->whereHas('kingdoms', static fn ($query) => $query->whereIn('kingdoms.id', $kingdomIds))
            ->with(['kingdoms' => static fn ($query) => $query->select('kingdoms.id')->whereIn('kingdoms.id', $kingdomIds)])
            ->orderBy('id')->get();
        $result = [];
        foreach ($groups as $group) {
            foreach ($group->kingdoms as $kingdom) {
                $result[(string) $kingdom->id] = $group;
            }
        }

        return $result;
    }

    /** @return list<string> */
    private function authoritativeSources(): array
    {
        return array_map(static fn (TransferSourceType $source): string => $source->value, array_values(array_filter(TransferSourceType::cases(), static fn (TransferSourceType $source): bool => $source->isAuthoritative())));
    }
}
