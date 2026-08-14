<?php

declare(strict_types=1);

namespace App\Domain\Contributions\Actions;

use App\Domain\Alliances\Models\Alliance;
use App\Domain\Audit\Services\AuditRecorder;
use App\Domain\Contributions\Models\ContributionDataQualityFlag;
use App\Domain\Kingdoms\Models\Player;
use InvalidArgumentException;

final class ResolveContributionDataQualityFlag
{
    public function __construct(private readonly AuditRecorder $audit) {}

    public function handle(
        Player $actor,
        Alliance $alliance,
        ContributionDataQualityFlag $flag,
    ): ContributionDataQualityFlag {
        if ($flag->alliance_id !== $alliance->id) {
            throw new InvalidArgumentException('Data-quality flag does not belong to the active alliance.');
        }

        if ($flag->status === 'resolved') {
            return $flag;
        }

        $flag->forceFill([
            'status' => 'resolved',
            'resolved_at' => now(),
            'resolved_by_player_id' => $actor->id,
        ])->save();

        $this->audit->record('contribution.data-quality.resolved', $actor, $flag, $alliance, [
            'code' => $flag->code,
        ]);

        return $flag->refresh();
    }
}
