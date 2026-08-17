<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Contributions\Actions;

use App\Contexts\Intelligence\Access\Enums\IntelligencePermission;
use App\Contexts\Intelligence\Access\Services\AllianceIntelligenceWriteState;
use App\Contexts\Intelligence\Contributions\Models\ContributionDataQualityFlag;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use Illuminate\Support\Facades\DB;

final class ResolveContributionDataQualityFlag
{
    public function __construct(
        private readonly AllianceIntelligenceWriteState $writeState,
        private readonly AuditRecorder $audit,
    ) {}

    public function handle(string $actorPlayerId, string $allianceId, string $flagId): void
    {
        DB::transaction(function () use ($actorPlayerId, $allianceId, $flagId): void {
            [, $actor] = $this->writeState->authorize($actorPlayerId, $allianceId, IntelligencePermission::ContributionManage);
            $flag = ContributionDataQualityFlag::query()
                ->where('alliance_id', $allianceId)
                ->whereKey($flagId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($flag->status === 'resolved') {
                return;
            }

            $flag->forceFill([
                'status' => 'resolved',
                'resolved_at' => now(),
                'resolved_by_player_id' => $actor->playerId,
            ])->save();

            $this->audit->record('contribution.data-quality.resolved', $actor, $flag, $allianceId, [
                'code' => $flag->code,
                'player_id' => $flag->player_id,
            ]);
        });
    }
}
