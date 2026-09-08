<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\Kingdoms\Queries;

use App\Contexts\GameWorld\Kingdoms\Models\KingdomAllianceIdentityHistory;
use App\Contexts\GameWorld\Kingdoms\ValueObjects\KingdomAllianceIdentityReference;
use Carbon\CarbonImmutable;
use DateTimeInterface;

final class KingdomAllianceIdentityHistoryQuery
{
    public function currentIdentity(string $kingdomAllianceId): ?KingdomAllianceIdentityReference
    {
        $history = KingdomAllianceIdentityHistory::query()
            ->where('kingdom_alliance_id', $kingdomAllianceId)
            ->whereNull('valid_to')
            ->orderByDesc('valid_from')
            ->first();

        return $history instanceof KingdomAllianceIdentityHistory ? $this->snapshot($history) : null;
    }

    public function identityAt(string $kingdomAllianceId, DateTimeInterface $at): ?KingdomAllianceIdentityReference
    {
        $moment = CarbonImmutable::instance($at)->utc();
        $history = KingdomAllianceIdentityHistory::query()
            ->where('kingdom_alliance_id', $kingdomAllianceId)
            ->where('valid_from', '<=', $moment)
            ->where(function ($query) use ($moment): void {
                $query->whereNull('valid_to')->orWhere('valid_to', '>', $moment);
            })
            ->orderByDesc('valid_from')
            ->first();

        return $history instanceof KingdomAllianceIdentityHistory ? $this->snapshot($history) : null;
    }

    /** @return list<KingdomAllianceIdentityReference> */
    public function identityHistory(string $kingdomAllianceId): array
    {
        return KingdomAllianceIdentityHistory::query()
            ->where('kingdom_alliance_id', $kingdomAllianceId)
            ->orderBy('valid_from')
            ->orderBy('id')
            ->get()
            ->map(fn (KingdomAllianceIdentityHistory $history): KingdomAllianceIdentityReference => $this->snapshot($history))
            ->values()
            ->all();
    }

    private function snapshot(KingdomAllianceIdentityHistory $history): KingdomAllianceIdentityReference
    {
        return new KingdomAllianceIdentityReference(
            historyId: (string) $history->id,
            kingdomAllianceId: (string) $history->kingdom_alliance_id,
            name: (string) $history->name,
            tag: $history->tag === null ? null : (string) $history->tag,
            gameAllianceId: $history->game_alliance_id === null ? null : (string) $history->game_alliance_id,
            validFrom: CarbonImmutable::instance($history->valid_from)->utc(),
            validTo: $history->valid_to === null ? null : CarbonImmutable::instance($history->valid_to)->utc(),
            sourceType: $history->source_type,
            sourceReference: $history->source_reference === null ? null : (string) $history->source_reference,
            observedAt: $history->observed_at === null ? null : CarbonImmutable::instance($history->observed_at)->utc(),
            confidenceBasisPoints: $history->confidence_basis_points,
            reason: $history->reason === null ? null : (string) $history->reason,
        );
    }
}
