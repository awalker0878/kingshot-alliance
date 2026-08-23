<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\KingdomTransfers\Queries;

use App\Contexts\GameWorld\KingdomTransfers\Models\TransferGroup;
use Illuminate\Database\Eloquent\Collection;

final class TransferGroupQuery
{
    /** @return Collection<int,TransferGroup> */
    public function currentForWindow(string $allianceId,string $windowId): Collection
    {
        return TransferGroup::query()->where('alliance_id',$allianceId)->where('transfer_window_id',$windowId)->whereNull('superseded_at')->with('kingdoms:id,number')->orderBy('official_label')->get();
    }

    /** @return Collection<int,TransferGroup> */
    public function historyForWindow(string $allianceId,string $windowId): Collection
    {
        return TransferGroup::query()->where('alliance_id',$allianceId)->where('transfer_window_id',$windowId)->with('kingdoms:id,number')->orderBy('official_label')->orderByDesc('revision')->get();
    }
}
