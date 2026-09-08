<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\Kingdoms\Queries;

use App\Contexts\GameWorld\Kingdoms\Enums\KingdomAllianceStatus;
use App\Contexts\GameWorld\Kingdoms\Enums\KingdomStatus;
use App\Contexts\GameWorld\Kingdoms\Models\KingdomAlliance;
use App\Contexts\GameWorld\Kingdoms\Models\KingdomAllianceIdentityHistory;
use Illuminate\Support\Facades\DB;

final class KingdomsIntegrityQuery
{
    /**
     * @return array{
     *   active_alliance_under_archived_kingdom:list<string>,
     *   active_reconciled_alias:list<string>,
     *   missing_current_identity_history:list<string>,
     *   current_identity_drift:list<string>,
     *   multiple_open_identity_history:list<string>,
     *   canonical_cycles:list<string>,
     *   unresolved_duplicate_candidates:list<array{left:string,right:string,reasons:list<string>}>
     * }
     */
    public function report(): array
    {
        $alliances = KingdomAlliance::query()->with('kingdom')->orderBy('id')->get();
        $activeUnderArchived = [];
        $activeAliases = [];
        $missingHistory = [];
        $drift = [];
        $cycles = [];
        $unresolved = [];

        /** @var array<string, KingdomAlliance> $byId */
        $byId = $alliances->keyBy(fn (KingdomAlliance $alliance): string => (string) $alliance->id)->all();
        $currentHistory = KingdomAllianceIdentityHistory::query()
            ->whereNull('valid_to')
            ->get()
            ->keyBy(fn (KingdomAllianceIdentityHistory $history): string => (string) $history->kingdom_alliance_id);

        foreach ($alliances as $alliance) {
            $id = (string) $alliance->id;
            if ($alliance->status === KingdomAllianceStatus::Active && $alliance->kingdom->status !== KingdomStatus::Active) {
                $activeUnderArchived[] = $id;
            }
            if ($alliance->status === KingdomAllianceStatus::Active && $alliance->canonical_kingdom_alliance_id !== null) {
                $activeAliases[] = $id;
            }

            $history = $currentHistory->get($id);
            if ($alliance->canonical_kingdom_alliance_id === null && ! $history instanceof KingdomAllianceIdentityHistory) {
                $missingHistory[] = $id;
            } elseif ($history instanceof KingdomAllianceIdentityHistory
                && ((string) $history->name !== (string) $alliance->current_name
                    || $this->nullable($history->tag) !== $this->nullable($alliance->current_tag)
                    || $this->nullable($history->game_alliance_id) !== $this->nullable($alliance->game_alliance_id))) {
                $drift[] = $id;
            }

            if ($this->hasCycle($alliance, $byId)) {
                $cycles[] = $id;
            }
        }

        $groups = [];
        foreach ($alliances as $alliance) {
            if ($alliance->canonical_kingdom_alliance_id !== null) {
                continue;
            }
            $name = $this->normalize((string) $alliance->current_name);
            $tag = $this->normalize($alliance->current_tag === null ? null : (string) $alliance->current_tag);
            $key = (string) $alliance->kingdom_id;
            $groups[$key][] = [$alliance, $name, $tag];
        }
        foreach ($groups as $rows) {
            $count = count($rows);
            for ($left = 0; $left < $count; $left++) {
                for ($right = $left + 1; $right < $count; $right++) {
                    [$a, $aName, $aTag] = $rows[$left];
                    [$b, $bName, $bTag] = $rows[$right];
                    $reasons = [];
                    if ($aName !== '' && $aName === $bName) {
                        $reasons[] = 'same_normalized_name';
                    }
                    if ($aTag !== '' && $aTag === $bTag) {
                        $reasons[] = 'same_normalized_tag';
                    }
                    if ($reasons !== []) {
                        $unresolved[] = [
                            'left' => (string) $a->id,
                            'right' => (string) $b->id,
                            'reasons' => $reasons,
                        ];
                    }
                }
            }
        }

        $multipleOpen = DB::table('kingdom_alliance_identity_history')
            ->select('kingdom_alliance_id')
            ->whereNull('valid_to')
            ->groupBy('kingdom_alliance_id')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('kingdom_alliance_id')
            ->map(static fn ($id): string => (string) $id)
            ->values()
            ->all();

        return [
            'active_alliance_under_archived_kingdom' => $activeUnderArchived,
            'active_reconciled_alias' => $activeAliases,
            'missing_current_identity_history' => $missingHistory,
            'current_identity_drift' => $drift,
            'multiple_open_identity_history' => $multipleOpen,
            'canonical_cycles' => array_values(array_unique($cycles)),
            'unresolved_duplicate_candidates' => $unresolved,
        ];
    }

    /** @param array<string, KingdomAlliance> $byId */
    private function hasCycle(KingdomAlliance $start, array $byId): bool
    {
        $seen = [];
        $current = $start;
        for ($depth = 0; $depth < 32; $depth++) {
            $id = (string) $current->id;
            if (isset($seen[$id])) {
                return true;
            }
            $seen[$id] = true;
            $nextId = $current->canonical_kingdom_alliance_id === null ? null : (string) $current->canonical_kingdom_alliance_id;
            if ($nextId === null || ! isset($byId[$nextId])) {
                return false;
            }
            $current = $byId[$nextId];
        }

        return true;
    }

    private function normalize(?string $value): string
    {
        return mb_strtolower(trim($value ?? ''), 'UTF-8');
    }

    private function nullable(mixed $value): ?string
    {
        if ($value === null) {
            return null;
        }
        $value = trim((string) $value);

        return $value === '' ? null : $value;
    }
}
