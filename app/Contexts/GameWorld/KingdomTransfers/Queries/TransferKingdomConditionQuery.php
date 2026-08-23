<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\KingdomTransfers\Queries;

use App\Contexts\GameWorld\KingdomTransfers\Models\TransferKingdomConditionObservation;
use Illuminate\Database\Eloquent\Collection;

final class TransferKingdomConditionQuery
{
    /** @return Collection<int,TransferKingdomConditionObservation> */
    public function forWindow(string $allianceId,string $windowId): Collection
    {
        return TransferKingdomConditionObservation::query()->where('alliance_id',$allianceId)->where('transfer_window_id',$windowId)->with('kingdom:id,number')->orderByDesc('observed_at')->orderByDesc('id')->limit(500)->get();
    }
}
