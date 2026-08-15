<?php

declare(strict_types=1);

namespace App\Domain\Contributions\Actions;

use App\Contexts\Alliance\Access\Services\AllianceMutationAuthority;
use App\Contexts\Alliance\Core\Models\Alliance;
use App\Contexts\GameWorld\Models\Player;
use App\Contexts\Intelligence\Access\Enums\IntelligencePermission;
use App\Domain\Contributions\Enums\ContributionRecordStatus;
use App\Domain\Contributions\Models\ContributionRecord;
use App\Shared\Audit\Services\AuditRecorder;
use App\Shared\Messaging\Services\OutboxRecorder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class CorrectContributionRecord
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
        float $replacementValue,
        string $reason,
        ?string $replacementEvidence = null,
    ): ContributionRecord {
        $reason = trim($reason);
        if ($reason === '') {
            throw new InvalidArgumentException('A correction reason is required.');
        }

        return DB::transaction(function () use ($actor, $alliance, $record, $replacementValue, $replacementEvidence, $reason): ContributionRecord {
            $context = $this->authority->require($actor, $alliance, IntelligencePermission::ContributionManage);
            $original = ContributionRecord::query()
                ->where('alliance_id', $context->alliance->id)
                ->whereKey($record->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($original->status === ContributionRecordStatus::Reversed) {
                throw new InvalidArgumentException('A reversed contribution record cannot be corrected again.');
            }

            $replacementStatus = $original->status;
            $original->forceFill([
                'status' => ContributionRecordStatus::Reversed,
                'reversed_at' => now(),
                'reversed_by_player_id' => $context->actor->id,
                'reversal_reason' => 'Corrected: '.$reason,
            ])->save();

            $replacement = ContributionRecord::query()->create([
                'alliance_id' => $context->alliance->id,
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
                'recorded_by_player_id' => $context->actor->id,
                'approved_at' => $replacementStatus === ContributionRecordStatus::Approved ? now() : null,
                'approved_by_player_id' => $replacementStatus === ContributionRecordStatus::Approved ? $context->actor->id : null,
                'correction_reason' => $reason,
            ]);

            $this->audit->record('contribution.record.corrected', $context->actor, $replacement, $context->alliance, [
                'original_record_id' => $original->id,
                'player_id' => $original->player_id,
                'reason' => $reason,
            ]);
            $this->outbox->record('contribution.record.corrected', $context->alliance->id, $replacement, [
                'record_id' => $replacement->id,
                'original_record_id' => $original->id,
                'player_id' => $original->player_id,
            ]);

            return $replacement;
        });
    }
}
