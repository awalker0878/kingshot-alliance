<?php

declare(strict_types=1);

namespace App\Domain\Contributions\Actions;

use App\Domain\Alliances\Models\Alliance;
use App\Domain\Audit\Services\AuditRecorder;
use App\Domain\Authorization\Enums\PermissionKey;
use App\Domain\Authorization\Services\AllianceMutationAuthority;
use App\Domain\Contributions\Enums\ContributionRecordSource;
use App\Domain\Contributions\Enums\ContributionRecordStatus;
use App\Domain\Contributions\Models\ContributionCategory;
use App\Domain\Contributions\Models\ContributionRecord;
use App\Domain\Contributions\Services\ContributionPeriodResolver;
use App\Domain\Kingdoms\Models\Player;
use App\Domain\Memberships\Enums\MembershipStatus;
use App\Domain\Memberships\Models\AllianceMembership;
use App\Domain\Platform\Services\OutboxRecorder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class RecordContribution
{
    public function __construct(
        private readonly AllianceMutationAuthority $authority,
        private readonly ContributionPeriodResolver $periods,
        private readonly AuditRecorder $audit,
        private readonly OutboxRecorder $outbox,
    ) {}

    public function handle(
        Player $actor,
        Alliance $alliance,
        Player $player,
        ContributionCategory $category,
        float $value,
        ContributionRecordSource $source,
        ?string $evidence = null,
    ): ContributionRecord {
        return DB::transaction(function () use ($actor, $alliance, $player, $category, $value, $source, $evidence): ContributionRecord {
            $permission = $source === ContributionRecordSource::SelfReported
                ? PermissionKey::AllianceView
                : PermissionKey::ContributionManage;
            $context = $this->authority->require($actor, $alliance, $permission);

            if ($source === ContributionRecordSource::SelfReported
                && (string) $player->id !== (string) $context->actor->id) {
                throw new InvalidArgumentException('Self-reported contributions may only be recorded for the active Player.');
            }

            $currentPlayer = Player::query()
                ->whereKey($player->id)
                ->lockForUpdate()
                ->firstOrFail();
            if ((string) $currentPlayer->current_kingdom_id !== (string) $context->alliance->kingdom_id) {
                throw new InvalidArgumentException('Contribution Player must belong to the active Alliance Kingdom.');
            }

            // Manual records require a current active Alliance member. Historical
            // attribution is still Player-based; the membership is only an eligibility check.
            if ($source !== ContributionRecordSource::SelfReported) {
                $membership = AllianceMembership::query()
                    ->where('alliance_id', $context->alliance->id)
                    ->where('player_id', $currentPlayer->id)
                    ->where('status', MembershipStatus::Active->value)
                    ->lockForUpdate()
                    ->first();
                if (! $membership instanceof AllianceMembership) {
                    throw new InvalidArgumentException('Manual contributions may only target a current active Alliance Player.');
                }
            }

            $currentCategory = ContributionCategory::query()
                ->where('alliance_id', $context->alliance->id)
                ->whereKey($category->id)
                ->sharedLock()
                ->firstOrFail();
            if (! $currentCategory->is_active) {
                throw new InvalidArgumentException('Contribution category is inactive.');
            }
            if ($source === ContributionRecordSource::SelfReported && ! $currentCategory->allow_self_report) {
                throw new InvalidArgumentException('This contribution category does not allow member self-reporting.');
            }
            if ($currentCategory->evidence_required && trim((string) $evidence) === '') {
                throw new InvalidArgumentException('Evidence is required for this contribution category.');
            }

            $period = $this->periods->current($currentCategory, $context->alliance->timezone);
            $record = ContributionRecord::query()->create([
                'alliance_id' => $context->alliance->id,
                'category_id' => $currentCategory->id,
                'player_id' => $currentPlayer->id,
                'source' => $source,
                'data_class' => $currentCategory->data_class,
                'value' => $value,
                'period_start' => $period['start']->toDateString(),
                'period_end' => $period['end']->toDateString(),
                'status' => ContributionRecordStatus::Pending,
                'evidence' => $evidence,
                'calculation_key' => $currentCategory->calculation_key,
                'calculation_version' => $currentCategory->calculation_version,
                'recorded_at' => now(),
                'recorded_by_player_id' => $context->actor->id,
            ]);

            $this->audit->record('contribution.record.created', $context->actor, $record, $context->alliance, [
                'source' => $source->value,
                'player_id' => $currentPlayer->id,
                'category_id' => $currentCategory->id,
            ]);
            $this->outbox->record('contribution.record.created', $context->alliance->id, $record, [
                'record_id' => $record->id,
                'status' => $record->status->value,
            ]);

            return $record;
        });
    }
}
