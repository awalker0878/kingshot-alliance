<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\KingdomTransfers\Queries;

use App\Contexts\GameWorld\KingdomTransfers\Models\TransferWindow;
use Illuminate\Database\Eloquent\Collection;

final class TransferWindowQuery
{
    /** @return Collection<int,TransferWindow> */
    public function forAlliance(string $allianceId): Collection
    {
        return TransferWindow::query()->where('alliance_id',$allianceId)->orderByDesc('pre_transfer_starts_at')->orderByDesc('id')->limit(25)->get();
    }
}
