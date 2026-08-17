<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Contributions\Actions;

use App\Contexts\Alliance\Lifecycle\Queries\AllianceReferenceQuery;
use App\Contexts\Alliance\Membership\Queries\PlayerMembershipQuery;
use App\Contexts\Intelligence\Access\Enums\IntelligencePermission;
use App\Contexts\Intelligence\Access\Services\AllianceIntelligenceWriteState;
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
        private readonly AllianceIntelligenceWriteState $writeState,
        private readonly AllianceReferenceQuery $alliances,
        private readonly PlayerMembershipQuery $memberships,
        private readonly ContributionPeriodResolver $periods,
        private readonly AuditRecorder $audit,
    ) {}

    /** @return array{missing_evidence: int, missing_records: int} */
    public function handle(string $actorPlayerId, string $allianceId): array
    {
        return DB::transaction(function () use ($actorPlayerId, $allianceId): array {
            [, $actor] = $this->writeState->authorize($actorPlayerId, $allianceId, IntelligencePermission::ContributionManage);
            $alliance = $this->alliances->require($allianceId);
            $activePlayerIds = $this->memberships->lockActivePlayerIds($allianceId);

            $categories = ContributionCategory::query()
                ->where('alliance_id', $allianceId)
                ->where('is_active', true)
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

            ContributionDataQualityFlag::query()
                ->where('alliance_id', $allianceId)
                ->where('status', 'open')
                ->whereIn('code', ['missing_evidence', 'missing_period_record'])
                ->orderBy('id')
                ->lockForUpdate()
                ->get()
                ->each(function (ContributionDataQualityFlag $flag) use ($actor): void {
                    $flag->forceFill([
                        'status' => 'resolved',
                        'resolved_at' => now(),
                        'resolved_by_player_id' => $actor->playerId,
                    ])->save();
                });

            $missingEvidence = 0;
            $missingRecords = 0;

            foreach (ContributionRecord::query()
                ->where('alliance_id', $allianceId)
                ->whereIn('status', [ContributionRecordStatus::Pending->value, ContributionRecordStatus::Approved->value])
                ->where(fn ($query) => $query->whereNull('evidence')->orWhere('evidence', ''))
                ->whereHas('category', static fn ($query) => $query->where('evidence_required', true))
                ->orderBy('id')
                ->get() as $record) {
                ContributionDataQualityFlag::query()->create([
                    'alliance_id' => $allianceId,
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
                $period = $this->periods->current($category, $alliance->timezone);
                foreach ($activePlayerIds as $playerId) {
                    $hasRecord = ContributionRecord::query()
                        ->where('alliance_id', $allianceId)
                        ->where('category_id', $category->id)
                        ->where('player_id', $playerId)
                        ->whereIn('status', [ContributionRecordStatus::Pending->value, ContributionRecordStatus::Approved->value])
                        ->whereDate('period_start', $period['start']->toDateString())
                        ->whereDate('period_end', $period['end']->toDateString())
                        ->exists();
                    if ($hasRecord) {
                        continue;
                    }

                    ContributionDataQualityFlag::query()->create([
                        'alliance_id' => $allianceId,
                        'player_id' => $playerId,
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

            $this->audit->record('contribution.data-quality.refreshed', $actor, null, $allianceId, [
                'missing_evidence' => $missingEvidence,
                'missing_records' => $missingRecords,
            ]);

            return ['missing_evidence' => $missingEvidence, 'missing_records' => $missingRecords];
        });
    }
}
