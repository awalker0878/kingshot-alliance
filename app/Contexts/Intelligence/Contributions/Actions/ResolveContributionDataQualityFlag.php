<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Contributions\Actions;

use App\Contexts\Alliance\Access\Services\AllianceMutationAuthority;
use App\Contexts\Alliance\Core\Models\Alliance;
use App\Contexts\GameWorld\Models\Player;
use App\Contexts\Intelligence\Access\Enums\IntelligencePermission;
use App\Contexts\Intelligence\Contributions\Models\ContributionDataQualityFlag;
use App\Shared\Audit\Services\AuditRecorder;
use Illuminate\Support\Facades\DB;

final class ResolveContributionDataQualityFlag
{
    public function __construct(
        private readonly AllianceMutationAuthority $authority,
        private readonly AuditRecorder $audit,
    ) {}

    public function handle(
        Player $actor,
        Alliance $alliance,
        ContributionDataQualityFlag $flag,
    ): ContributionDataQualityFlag {
        return DB::transaction(function () use ($actor, $alliance, $flag): ContributionDataQualityFlag {
            $context = $this->authority->require($actor, $alliance, IntelligencePermission::ContributionManage);
            $locked = ContributionDataQualityFlag::query()
                ->where('alliance_id', $context->alliance->id)
                ->whereKey($flag->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status === 'resolved') {
                return $locked;
            }

            $locked->forceFill([
                'status' => 'resolved',
                'resolved_at' => now(),
                'resolved_by_player_id' => $context->actor->id,
            ])->save();

            $this->audit->record('contribution.data-quality.resolved', $context->actor, $locked, $context->alliance, [
                'code' => $locked->code,
                'player_id' => $locked->player_id,
            ]);

            return $locked->refresh();
        });
    }
}
