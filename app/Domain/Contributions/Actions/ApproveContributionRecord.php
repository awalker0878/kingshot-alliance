<?php

declare(strict_types=1);

namespace App\Domain\Contributions\Actions;

use App\Contexts\Alliance\Access\Services\AllianceMutationAuthority;
use App\Contexts\Alliance\Core\Models\Alliance;
use App\Contexts\GameWorld\Models\Player;
use App\Domain\Authorization\Enums\PermissionKey;
use App\Domain\Contributions\Enums\ContributionRecordStatus;
use App\Domain\Contributions\Models\ContributionRecord;
use App\Shared\Audit\Services\AuditRecorder;
use App\Shared\Messaging\Services\OutboxRecorder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class ApproveContributionRecord
{
    public function __construct(
        private readonly AllianceMutationAuthority $authority,
        private readonly AuditRecorder $audit,
        private readonly OutboxRecorder $outbox,
    ) {}

    public function handle(Player $actor, Alliance $alliance, ContributionRecord $record): ContributionRecord
    {
        return DB::transaction(function () use ($actor, $alliance, $record): ContributionRecord {
            $context = $this->authority->require($actor, $alliance, PermissionKey::ContributionManage);
            $locked = ContributionRecord::query()
                ->where('alliance_id', $context->alliance->id)
                ->whereKey($record->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($locked->status === ContributionRecordStatus::Approved) {
                return $locked;
            }
            if ($locked->status !== ContributionRecordStatus::Pending) {
                throw new InvalidArgumentException('Only pending contribution records can be approved.');
            }

            $locked->forceFill([
                'status' => ContributionRecordStatus::Approved,
                'approved_at' => now(),
                'approved_by_player_id' => $context->actor->id,
            ])->save();

            $this->audit->record('contribution.record.approved', $context->actor, $locked, $context->alliance);
            $this->outbox->record('contribution.record.approved', $context->alliance->id, $locked, [
                'record_id' => $locked->id,
            ]);

            return $locked->refresh();
        });
    }
}
