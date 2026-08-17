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

final class CorrectContributionRecord
{
    public function __construct(
        private readonly AllianceIntelligenceWriteState $writeState,
        private readonly AuditRecorder $audit,
        private readonly OutboxRecorder $outbox,
    ) {}

    public function handle(
        string $actorPlayerId,
        string $allianceId,
        string $recordId,
        float $replacementValue,
        string $reason,
        ?string $replacementEvidence = null,
    ): void {
        $reason = trim($reason);
        if ($reason === '') {
            throw new InvalidArgumentException('A correction reason is required.');
        }

        DB::transaction(function () use ($actorPlayerId, $allianceId, $recordId, $replacementValue, $replacementEvidence, $reason): void {
            [, $actor] = $this->writeState->authorize($actorPlayerId, $allianceId, IntelligencePermission::ContributionManage);
            $original = ContributionRecord::query()
                ->where('alliance_id', $allianceId)
                ->whereKey($recordId)
                ->lockForUpdate()
                ->firstOrFail();

            if ($original->status === ContributionRecordStatus::Reversed) {
                throw new InvalidArgumentException('A reversed contribution record cannot be corrected again.');
            }

            $replacementStatus = $original->status;
            $original->forceFill([
                'status' => ContributionRecordStatus::Reversed,
                'reversed_at' => now(),
                'reversed_by_player_id' => $actor->playerId,
                'reversal_reason' => 'Corrected: '.$reason,
            ])->save();

            $replacement = ContributionRecord::query()->create([
                'alliance_id' => $allianceId,
                'category_id' => $original->category_id,
                'player_id' => $original->player_id,
                'source' => $original->source,
                'data_class' => $original->data_class,
                'value' => $replacementValue,
                'period_start' => $original->period_start->toDateString(),
                'period_end' => $original->period_end->toDateString(),
                'status' => $replacementStatus,
                'evidence' => $replacementEvidence ?? $original->evidence,
                'correction_of_record_id' => $original->id,
                'calculation_key' => $original->calculation_key,
                'calculation_version' => $original->calculation_version,
                'calculation_inputs' => $original->calculation_inputs,
                'recorded_at' => now(),
                'recorded_by_player_id' => $actor->playerId,
                'approved_at' => $replacementStatus === ContributionRecordStatus::Approved ? now() : null,
                'approved_by_player_id' => $replacementStatus === ContributionRecordStatus::Approved ? $actor->playerId : null,
                'correction_reason' => $reason,
            ]);

            $this->audit->record('contribution.record.corrected', $actor, $replacement, $allianceId, [
                'original_record_id' => $original->id,
                'player_id' => $original->player_id,
                'reason' => $reason,
            ]);
            $this->outbox->record('contribution.record.corrected', $allianceId, $replacement, [
                'record_id' => $replacement->id,
                'original_record_id' => $original->id,
                'player_id' => $original->player_id,
            ]);
        });
    }
}
