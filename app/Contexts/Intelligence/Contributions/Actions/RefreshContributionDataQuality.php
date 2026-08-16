<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Contributions\Actions;

use App\Contexts\Alliance\Access\Services\AllianceWriteState;
use App\Contexts\Alliance\Lifecycle\Models\Alliance;
use App\Contexts\Alliance\Membership\Enums\MembershipStatus;
use App\Contexts\Alliance\Membership\Models\AllianceMembership;
use App\Contexts\GameWorld\Players\Models\Player;
use App\Contexts\Intelligence\Access\Enums\IntelligencePermission;
use App\Contexts\Intelligence\Access\Services\AllianceIntelligenceAuthorization;
use App\Contexts\Intelligence\Contributions\Enums\ContributionRecordStatus;
use App\Contexts\Intelligence\Contributions\Models\ContributionCategory;
use App\Contexts\Intelligence\Contributions\Models\ContributionDataQualityFlag;
use App\Contexts\Intelligence\Contributions\Models\ContributionRecord;
use App\Contexts\Intelligence\Contributions\Services\ContributionPeriodResolver;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use Illuminate\Support\Facades\DB;

final class RefreshContributionDataQuality
{
    public function __construct(
        private readonly AllianceWriteState $allianceWriteState,
        private readonly AllianceIntelligenceAuthorization $authority,
        private readonly ContributionPeriodResolver $periods,
        private readonly AuditRecorder $audit,
    ) {}

    /** @return array{missing_evidence: int, missing_records: int} */
    public function handle(Player $actor, Alliance $alliance): array
    {
        return DB::transaction(function () use ($actor, $alliance): array {
            $context = $this->allianceWriteState->lockActiveScope($actor, $alliance);
            $this->authority->authorizeContext($context, IntelligencePermission::ContributionManage);

            // Refresh is a Contributions-wide derived-state rebuild. Stabilize the two
            // source sets in the same order used by contribution entry: membership then category.
            $memberships = AllianceMembership::query()
                ->where('alliance_id', $context->alliance->id)
                ->where('status', MembershipStatus::Active->value)
                ->orderBy('id')
                
                ->get();
            $categories = ContributionCategory::query()
                ->where('alliance_id', $context->alliance->id)
                ->where('is_active', true)
                ->orderBy('id')
                
                ->get();

            ContributionDataQualityFlag::query()
                ->where('alliance_id', $context->alliance->id)
                ->where('status', 'open')
                ->whereIn('code', ['missing_evidence', 'missing_period_record'])
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->each(function (ContributionDataQualityFlag $flag) use ($context): void {
                    $flag->forceFill([
                        'status' => 'resolved',
                        'resolved_at' => now(),
                        'resolved_by_player_id' => $context->actor->id,
                    ])->save();
                });

            $missingEvidence = 0;
            $missingRecords = 0;

            foreach (ContributionRecord::query()
                ->where('alliance_id', $context->alliance->id)
                ->whereIn('status', [ContributionRecordStatus::Pending->value, ContributionRecordStatus::Approved->value])
                ->where(fn ($query) => $query->whereNull('evidence')->orWhere('evidence', ''))
                ->whereHas('category', static fn ($query) => $query->where('evidence_required', true))
                ->orderBy('id')
                ->get() as $record) {
                ContributionDataQualityFlag::query()->create([
                    'alliance_id' => $context->alliance->id,
                    'player_id' => $record->player_id,
                    'category_id' => $record->category_id,
                    'record_id' => $record->id,
                    'code' => 'missing_evidence',
                    'severity' => 'error',
                    'message' => 'A contribution record is missing evidence required by its category.',
                    'status' => 'open',
                    'detected_at' => now(),
                ]);
                $missingEvidence++;
            }

            foreach ($categories as $category) {
                $period = $this->periods->current($category, $context->alliance->timezone);
                foreach ($memberships as $membership) {
                    $hasRecord = ContributionRecord::query()
                        ->where('alliance_id', $context->alliance->id)
                        ->where('category_id', $category->id)
                        ->where('player_id', $membership->player_id)
                        ->whereIn('status', [ContributionRecordStatus::Pending->value, ContributionRecordStatus::Approved->value])
                        ->whereDate('period_start', $period['start']->toDateString())
                        ->whereDate('period_end', $period['end']->toDateString())
                        ->exists();
                    if ($hasRecord) {
                        continue;
                    }

                    ContributionDataQualityFlag::query()->create([
                        'alliance_id' => $context->alliance->id,
                        'player_id' => $membership->player_id,
                        'category_id' => $category->id,
                        'code' => 'missing_period_record',
                        'severity' => 'warning',
                        'message' => sprintf('No %s record exists for the current %s period.', $category->name, $category->period->value),
                        'status' => 'open',
                        'detected_at' => now(),
                    ]);
                    $missingRecords++;
                }
            }

            $this->audit->record('contribution.data-quality.refreshed', $context->actor, $context->alliance, $context->alliance, [
                'missing_evidence' => $missingEvidence,
                'missing_records' => $missingRecords,
            ]);

            return ['missing_evidence' => $missingEvidence, 'missing_records' => $missingRecords];
        });
    }
}
