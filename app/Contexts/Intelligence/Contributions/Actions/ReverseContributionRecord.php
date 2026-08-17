<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Contributions\Actions;

use App\Contexts\Intelligence\Access\Enums\IntelligencePermission;
use App\Contexts\Intelligence\Access\Services\AllianceIntelligenceWriteState;
use App\Contexts\Intelligence\Contributions\Enums\ContributionRecordStatus;
use App\Contexts\Intelligence\Contributions\Models\ContributionRecord;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class ReverseContributionRecord
{
    public function __construct(
        private readonly AllianceIntelligenceWriteState $writeState,
        private readonly AuditRecorder $audit,
        private readonly OutboxRecorder $outbox,
    ) {}

    public function handle(string $actorPlayerId, string $allianceId, string $recordId, string $reason): void
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw new InvalidArgumentException('A reversal reason is required.');
        }

        DB::transaction(function () use ($actorPlayerId, $allianceId, $recordId, $reason): void {
            [, $actor] = $this->writeState->authorize($actorPlayerId, $allianceId, IntelligencePermission::ContributionManage);
            $record = ContributionRecord::query()
                ->where('alliance_id', $allianceId)
                ->whereKey($recordId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($record->status === ContributionRecordStatus::Reversed) {
                return;
            }

            $record->forceFill([
                'status' => ContributionRecordStatus::Reversed,
                'reversed_at' => now(),
                'reversed_by_player_id' => $actor->playerId,
                'reversal_reason' => $reason,
            ])->save();

            $this->audit->record('contribution.record.reversed', $actor, $record, $allianceId, [
                'player_id' => $record->player_id,
                'reason' => $reason,
            ]);
            $this->outbox->record('contribution.record.reversed', $allianceId, $record, [
                'record_id' => $record->id,
                'player_id' => $record->player_id,
            ]);
        });
    }
}
