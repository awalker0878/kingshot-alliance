<?php

declare(strict_types=1);

namespace App\Domain\Contributions\Actions;

use App\Domain\Alliances\Models\Alliance;
use App\Domain\Audit\Services\AuditRecorder;
use App\Domain\Contributions\Enums\ContributionRecordStatus;
use App\Domain\Contributions\Models\ContributionCategory;
use App\Domain\Contributions\Models\ContributionDataQualityFlag;
use App\Domain\Contributions\Models\ContributionRecord;
use App\Domain\Contributions\Services\ContributionPeriodResolver;
use App\Domain\Identity\Models\User;
use App\Domain\Memberships\Enums\MembershipStatus;
use App\Domain\Memberships\Models\AllianceMembership;
use Illuminate\Support\Facades\DB;

final class RefreshContributionDataQuality
{
    public function __construct(
        private readonly ContributionPeriodResolver $periods,
        private readonly AuditRecorder $audit,
    ) {}

    /** @return array{missing_evidence: int, missing_records: int} */
    public function handle(User $actor, Alliance $alliance): array
    {
        return DB::transaction(function () use ($actor, $alliance): array {
            ContributionDataQualityFlag::query()
                ->where('alliance_id', $alliance->id)
                ->where('status', 'open')
                ->whereIn('code', ['missing_evidence', 'missing_period_record'])
                ->update([
                    'status' => 'resolved',
                    'resolved_at' => now(),
                    'resolved_by_user_id' => $actor->id,
                    'updated_at' => now(),
                ]);

            $missingEvidence = 0;
            $missingRecords = 0;

            foreach (ContributionRecord::query()
                ->where('alliance_id', $alliance->id)
                ->whereIn('status', [
                    ContributionRecordStatus::Pending->value,
                    ContributionRecordStatus::Approved->value,
                ])
                ->where(function ($query): void {
                    $query->whereNull('evidence')->orWhere('evidence', '');
                })
                ->whereHas('category', static function ($query): void {
                    $query->where('evidence_required', true);
                })
                ->get() as $record) {
                ContributionDataQualityFlag::query()->create([
                    'alliance_id' => $alliance->id,
                    'membership_id' => $record->membership_id,
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

            $memberships = AllianceMembership::query()
                ->where('alliance_id', $alliance->id)
                ->where('status', MembershipStatus::Active->value)
                ->orderBy('id')
                ->get();

            foreach (ContributionCategory::query()
                ->where('alliance_id', $alliance->id)
                ->where('is_active', true)
                ->orderBy('id')
                ->get() as $category) {
                $period = $this->periods->current($category, $alliance->timezone);

                foreach ($memberships as $membership) {
                    $hasRecord = ContributionRecord::query()
                        ->where('alliance_id', $alliance->id)
                        ->where('category_id', $category->id)
                        ->where('membership_id', $membership->id)
                        ->whereIn('status', [
                            ContributionRecordStatus::Pending->value,
                            ContributionRecordStatus::Approved->value,
                        ])
                        ->whereDate('period_start', $period['start']->toDateString())
                        ->whereDate('period_end', $period['end']->toDateString())
                        ->exists();

                    if ($hasRecord) {
                        continue;
                    }

                    ContributionDataQualityFlag::query()->create([
                        'alliance_id' => $alliance->id,
                        'membership_id' => $membership->id,
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

            $this->audit->record('contribution.data-quality.refreshed', $actor, $alliance, $alliance, [
                'missing_evidence' => $missingEvidence,
                'missing_records' => $missingRecords,
            ]);

            return [
                'missing_evidence' => $missingEvidence,
                'missing_records' => $missingRecords,
            ];
        });
    }
}
