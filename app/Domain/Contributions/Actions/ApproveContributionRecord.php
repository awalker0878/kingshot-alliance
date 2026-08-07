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

final class ApproveContributionRecord
{
    public function __construct(
        private readonly AuditRecorder $audit,
        private readonly OutboxRecorder $outbox,
    ) {}

    public function handle(User $actor, Alliance $alliance, ContributionRecord $record): ContributionRecord
    {
        if ($record->alliance_id !== $alliance->id) {
            throw new InvalidArgumentException('Contribution record does not belong to the active alliance.');
        }

        if ($record->status === ContributionRecordStatus::Approved) {
            return $record;
        }

        if ($record->status !== ContributionRecordStatus::Pending) {
            throw new InvalidArgumentException('Only pending contribution records can be approved.');
        }

        return DB::transaction(function () use ($actor, $alliance, $record): ContributionRecord {
            $record->forceFill([
                'status' => ContributionRecordStatus::Approved,
                'approved_at' => now(),
                'approved_by_user_id' => $actor->id,
            ])->save();

            $this->audit->record('contribution.record.approved', $actor, $record, $alliance);
            $this->outbox->record('contribution.record.approved', $alliance->id, $record, [
                'record_id' => $record->id,
            ]);

            return $record->refresh();
        });
    }
}
