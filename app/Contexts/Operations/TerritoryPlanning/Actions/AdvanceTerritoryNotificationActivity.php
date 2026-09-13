<?php

declare(strict_types=1);

namespace App\Contexts\Operations\TerritoryPlanning\Actions;

use App\Contexts\Operations\TerritoryPlanning\Models\TerritoryActivity;
use Illuminate\Support\Facades\DB;
use LogicException;

final class AdvanceTerritoryNotificationActivity
{
    public function handle(string $activityId, ?string $expectedAfter, ?string $nextAfter, bool $complete): void
    {
        if (DB::transactionLevel() < 1) {
            throw new LogicException('Activity receipts must commit with their delivery intents.');
        }
        $row = TerritoryActivity::query()->whereKey($activityId)->lockForUpdate()->firstOrFail();
        if ($row->after_player_id !== $expectedAfter || $row->completed_at !== null) {
            throw new LogicException('Territory notification activity cursor changed.');
        }
        $row->forceFill(['after_player_id' => $nextAfter, 'completed_at' => $complete ? now() : null])->save();
    }
}
