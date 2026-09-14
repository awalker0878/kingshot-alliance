<?php

declare(strict_types=1);

namespace App\Contexts\Operations\TerritoryPlanning\Actions;

use App\Contexts\Operations\TerritoryPlanning\Models\TerritoryRecoveryDraft;
use Illuminate\Support\Facades\DB;

final readonly class PruneExpiredTerritoryRecoveryDrafts
{
    public function handle(int $limit = 250): int
    {
        return DB::transaction(function () use ($limit): int {
            $ids = TerritoryRecoveryDraft::query()
                ->where('expires_at', '<=', now())
                ->orderBy('expires_at')->orderBy('id')
                ->limit(max(1, min(1000, $limit)))
                ->lock('for update skip locked')->pluck('id');

            return TerritoryRecoveryDraft::query()->whereKey($ids)->where('expires_at', '<=', now())->delete();
        });
    }
}
