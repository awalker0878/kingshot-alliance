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

final class ApproveContributionRecord
{
    public function __construct(
        private readonly AllianceIntelligenceWriteState $writeState,
        private readonly AuditRecorder $audit,
        private readonly OutboxRecorder $outbox,
    ) {}

    public function handle(string $actorPlayerId, string $allianceId, string $recordId): void
    {
        DB::transaction(function () use ($actorPlayerId, $allianceId, $recordId): void {
            [, $actor] = $this->writeState->authorize($actorPlayerId, $allianceId, IntelligencePermission::ContributionManage);
            $record = ContributionRecord::query()
                ->where('alliance_id', $allianceId)
                ->whereKey($recordId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($record->status === ContributionRecordStatus::Approved) {
                return;
            }
            if ($record->status !== ContributionRecordStatus::Pending) {
                throw new InvalidArgumentException('Only pending contribution records can be approved.');
            }

            $record->forceFill([
                'status' => ContributionRecordStatus::Approved,
                'approved_at' => now(),
                'approved_by_player_id' => $actor->playerId,
            ])->save();

            $this->audit->record('contribution.record.approved', $actor, $record, $allianceId);
            $this->outbox->record('contribution.record.approved', $allianceId, $record, [
                'record_id' => $record->id,
            ]);
        });
    }
}
