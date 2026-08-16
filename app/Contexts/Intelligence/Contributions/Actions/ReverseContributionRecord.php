<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Contributions\Actions;

use App\Contexts\Alliance\Access\Services\AllianceMutationAuthority;
use App\Contexts\Alliance\Core\Models\Alliance;
use App\Contexts\GameWorld\Models\Player;
use App\Contexts\Intelligence\Access\Enums\IntelligencePermission;
use App\Contexts\Intelligence\Contributions\Enums\ContributionRecordStatus;
use App\Contexts\Intelligence\Contributions\Models\ContributionRecord;
use App\Shared\Audit\Services\AuditRecorder;
use App\Shared\Messaging\Services\OutboxRecorder;
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
            $context = $this->authority->require($actor, $alliance, IntelligencePermission::ContributionManage);
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
