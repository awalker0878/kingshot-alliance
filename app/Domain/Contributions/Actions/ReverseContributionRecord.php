<?php

declare(strict_types=1);

namespace App\Domain\Contributions\Actions;

use App\Domain\Alliances\Models\Alliance;
use App\Domain\Audit\Services\AuditRecorder;
use App\Domain\Authorization\Enums\PermissionKey;
use App\Domain\Authorization\Services\AllianceMutationAuthority;
use App\Domain\Contributions\Enums\ContributionRecordStatus;
use App\Domain\Contributions\Models\ContributionRecord;
use App\Domain\Kingdoms\Models\Player;
use App\Domain\Platform\Services\OutboxRecorder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class ReverseContributionRecord
{
    public function __construct(
        private readonly AllianceMutationAuthority $authority,
        private readonly AuditRecorder $audit,
        private readonly OutboxRecorder $outbox,
    ) {}

    public function handle(
        Player $actor,
        Alliance $alliance,
        ContributionRecord $record,
        string $reason,
    ): ContributionRecord {
        $reason = trim($reason);
        if ($reason === '') {
            throw new InvalidArgumentException('A reversal reason is required.');
        }

        return DB::transaction(function () use ($actor, $alliance, $record, $reason): ContributionRecord {
            $context = $this->authority->require($actor, $alliance, PermissionKey::ContributionManage);
            $locked = ContributionRecord::query()
                ->where('alliance_id', $context->alliance->id)
                ->whereKey($record->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status === ContributionRecordStatus::Reversed) {
                return $locked;
            }

            $locked->forceFill([
                'status' => ContributionRecordStatus::Reversed,
                'reversed_at' => now(),
                'reversed_by_player_id' => $context->actor->id,
                'reversal_reason' => $reason,
            ])->save();

            $this->audit->record('contribution.record.reversed', $context->actor, $locked, $context->alliance, [
                'player_id' => $locked->player_id,
                'reason' => $reason,
            ]);
            $this->outbox->record('contribution.record.reversed', $context->alliance->id, $locked, [
                'record_id' => $locked->id,
                'player_id' => $locked->player_id,
            ]);

            return $locked->refresh();
        });
    }
}
