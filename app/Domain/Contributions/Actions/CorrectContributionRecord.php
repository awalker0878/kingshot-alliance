<?php

declare(strict_types=1);

namespace App\Domain\Contributions\Actions;

use App\Domain\Alliances\Models\Alliance;
use App\Domain\Audit\Services\AuditRecorder;
use App\Domain\Contributions\Enums\ContributionRecordSource;
use App\Domain\Contributions\Enums\ContributionRecordStatus;
use App\Domain\Contributions\Models\ContributionRecord;
use App\Domain\Identity\Models\User;
use App\Domain\Platform\Services\OutboxRecorder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class CorrectContributionRecord
{
    public function __construct(
        private readonly AuditRecorder $audit,
        private readonly OutboxRecorder $outbox,
    ) {}

    public function handle(
        User $actor,
        Alliance $alliance,
        ContributionRecord $record,
        float $replacementValue,
        string $reason,
        ?string $replacementEvidence = null,
    ): ContributionRecord {
        if ($record->alliance_id !== $alliance->id) {
            throw new InvalidArgumentException('Contribution record does not belong to the active alliance.');
        }

        if ($record->source === ContributionRecordSource::EventParticipation) {
            throw new InvalidArgumentException('Event-derived records must be corrected by reconciling event attendance.');
        }

        if ($record->status === ContributionRecordStatus::Reversed) {
            throw new InvalidArgumentException('A reversed contribution record cannot be corrected again.');
        }

        if (trim($reason) === '') {
            throw new InvalidArgumentException('A correction reason is required.');
        }

        return DB::transaction(function () use (
            $actor,
            $alliance,
            $record,
            $replacementValue,
            $replacementEvidence,
            $reason,
        ): ContributionRecord {
            $replacementStatus = $record->status;

            $record->forceFill([
                'status' => ContributionRecordStatus::Reversed,
                'reversed_at' => now(),
                'reversed_by_user_id' => $actor->id,
                'reversal_reason' => 'Corrected: '.$reason,
            ])->save();

            $replacement = ContributionRecord::query()->create([
                'alliance_id' => $alliance->id,
                'category_id' => $record->category_id,
                'membership_id' => $record->membership_id,
                'source' => $record->source,
                'data_class' => $record->data_class,
                'value' => $replacementValue,
                'period_start' => $record->period_start->toDateString(),
                'period_end' => $record->period_end->toDateString(),
                'status' => $replacementStatus,
                'evidence' => $replacementEvidence ?? $record->evidence,
                'correction_of_record_id' => $record->id,
                'calculation_key' => $record->calculation_key,
                'calculation_version' => $record->calculation_version,
                'calculation_inputs' => $record->calculation_inputs,
                'recorded_at' => now(),
                'recorded_by_user_id' => $actor->id,
                'approved_at' => $replacementStatus === ContributionRecordStatus::Approved ? now() : null,
                'approved_by_user_id' => $replacementStatus === ContributionRecordStatus::Approved ? $actor->id : null,
                'correction_reason' => $reason,
            ]);

            $this->audit->record('contribution.record.corrected', $actor, $replacement, $alliance, [
                'original_record_id' => $record->id,
                'reason' => $reason,
            ]);
            $this->outbox->record('contribution.record.corrected', $alliance->id, $replacement, [
                'record_id' => $replacement->id,
                'original_record_id' => $record->id,
            ]);

            return $replacement;
        });
    }
}
