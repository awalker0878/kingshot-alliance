<?php

declare(strict_types=1);

namespace App\Domain\Contributions\Actions;

use App\Domain\Alliances\Models\Alliance;
use App\Domain\Audit\Services\AuditRecorder;
use App\Domain\Contributions\Enums\ContributionRecordStatus;
use App\Domain\Contributions\Models\ContributionRecord;
use App\Domain\Identity\Models\User;
use App\Domain\Platform\Services\OutboxRecorder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class ReverseContributionRecord
{
    public function __construct(
        private readonly AuditRecorder $audit,
        private readonly OutboxRecorder $outbox,
    ) {}

    public function handle(
        User $actor,
        Alliance $alliance,
        ContributionRecord $record,
        string $reason,
    ): ContributionRecord {
        if ($record->alliance_id !== $alliance->id) {
            throw new InvalidArgumentException('Contribution record does not belong to the active alliance.');
        }

        if ($record->status === ContributionRecordStatus::Reversed) {
            return $record;
        }

        if (trim($reason) === '') {
            throw new InvalidArgumentException('A reversal reason is required.');
        }

        return DB::transaction(function () use ($actor, $alliance, $record, $reason): ContributionRecord {
            $record->forceFill([
                'status' => ContributionRecordStatus::Reversed,
                'reversed_at' => now(),
                'reversed_by_user_id' => $actor->id,
                'reversal_reason' => $reason,
            ])->save();

            $this->audit->record('contribution.record.reversed', $actor, $record, $alliance, [
                'reason' => $reason,
            ]);
            $this->outbox->record('contribution.record.reversed', $alliance->id, $record, [
                'record_id' => $record->id,
            ]);

            return $record->refresh();
        });
    }
}
