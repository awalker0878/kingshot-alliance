<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Contributions\Actions;

use App\Contexts\Alliance\Access\Enums\AlliancePermission;
use App\Contexts\Alliance\Core\Models\Alliance;
use App\Contexts\Alliance\Membership\Enums\MembershipStatus;
use App\Contexts\Alliance\Membership\Models\AllianceMembership;
use App\Contexts\GameWorld\Models\Player;
use App\Contexts\Intelligence\Access\Enums\IntelligencePermission;
use App\Contexts\Intelligence\Access\Services\AllianceIntelligenceAuthorization;
use App\Contexts\Intelligence\Contributions\Enums\ContributionRecordSource;
use App\Contexts\Intelligence\Contributions\Enums\ContributionRecordStatus;
use App\Contexts\Intelligence\Contributions\Models\ContributionCategory;
use App\Contexts\Intelligence\Contributions\Models\ContributionRecord;
use App\Contexts\Intelligence\Contributions\Services\ContributionPeriodResolver;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Services\OutboxRecorder;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

final class RecordContribution
{
    public function __construct(
        private readonly AllianceIntelligenceAuthorization $authority,
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
                ? AlliancePermission::View
                : IntelligencePermission::ContributionManage;
            $context = $this->authority->require($actor, $alliance, $permission);

            $isActorTarget = (string) $player->id === (string) $context->actor->id;
            if ($source === ContributionRecordSource::SelfReported && ! $isActorTarget) {
                throw new InvalidArgumentException('Self-reported contributions may only be recorded for the active Player.');
            }

            // AllianceAuthorization already stabilized the actor membership. Avoid
            // re-locking that same actor Player after its membership; target other Players
            // using the normal Player -> target-membership eligibility order.
            $currentPlayer = $isActorTarget
                ? $context->actor
                : Player::query()->whereKey($player->id)->lockForUpdate()->firstOrFail();
            if ((string) $currentPlayer->current_kingdom_id !== (string) $context->alliance->kingdom_id) {
                throw new InvalidArgumentException('Contribution Player must belong to the active Alliance Kingdom.');
            }

            if ($source !== ContributionRecordSource::SelfReported) {
                $membership = $isActorTarget
                    ? $context->membership
                    : AllianceMembership::query()
                        ->where('alliance_id', $context->alliance->id)
                        ->where('player_id', $currentPlayer->id)
                        ->where('status', MembershipStatus::Active->value)
                        ->lockForUpdate()
                        ->first();
                if (! $membership instanceof AllianceMembership || $membership->status !== MembershipStatus::Active) {
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
                'player_id' => $currentPlayer->id,
                'status' => $record->status->value,
            ]);

            return $record;
        });
    }
}
