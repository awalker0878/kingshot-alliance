<?php

declare(strict_types=1);

namespace App\Domain\Contributions\Actions;

use App\Domain\Alliances\Models\Alliance;
use App\Domain\Audit\Services\AuditRecorder;
use App\Domain\Contributions\Enums\ContributionDataClass;
use App\Domain\Contributions\Enums\ContributionRecordSource;
use App\Domain\Contributions\Enums\ContributionRecordStatus;
use App\Domain\Contributions\Models\ContributionCategory;
use App\Domain\Contributions\Models\ContributionRecord;
use App\Domain\Contributions\Services\ContributionPeriodResolver;
use App\Domain\Events\Enums\EventRegistrationStatus;
use App\Domain\Events\Models\EventRegistration;
use App\Domain\Identity\Models\User;
use App\Domain\Platform\Services\OutboxRecorder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

final class ReconcileEventParticipationContributions
{
    public function __construct(
        private readonly ContributionPeriodResolver $periods,
        private readonly AuditRecorder $audit,
        private readonly OutboxRecorder $outbox,
    ) {}

    /** @return array{created: int, restored: int, reversed: int} */
    public function handle(User $actor, Alliance $alliance): array
    {
        $result = ['created' => 0, 'restored' => 0, 'reversed' => 0];

        foreach (ContributionCategory::query()
            ->where('alliance_id', $alliance->id)
            ->where('is_active', true)
            ->where('data_class', ContributionDataClass::CalculatedMetric->value)
            ->where('calculation_key', 'event_attendance')
            ->orderBy('id')
            ->get() as $category) {
            $period = $this->periods->current($category, $alliance->timezone);
            $startUtc = $period['start']->utc();
            $endUtc = $period['end']->utc();

            $registrations = EventRegistration::query()
                ->where('alliance_id', $alliance->id)
                ->where('status', EventRegistrationStatus::Attended->value)
                ->whereHas('occurrence', static function (Builder $query) use ($startUtc, $endUtc): void {
                    $query->whereBetween('starts_at', [$startUtc, $endUtc]);
                })
                ->orderBy('id')
                ->get();
            $attendedIds = $registrations->pluck('id')->map(static fn (mixed $id): string => (string) $id)->all();

            DB::transaction(function () use (
                $actor,
                $alliance,
                $category,
                $period,
                $registrations,
                $attendedIds,
                &$result,
            ): void {
                foreach ($registrations as $registration) {
                    $record = ContributionRecord::query()
                        ->where('category_id', $category->id)
                        ->where('event_registration_id', $registration->id)
                        ->first();

                    if (! $record instanceof ContributionRecord) {
                        ContributionRecord::query()->create([
                            'alliance_id' => $alliance->id,
                            'category_id' => $category->id,
                            'membership_id' => $registration->membership_id,
                            'source' => ContributionRecordSource::EventParticipation,
                            'data_class' => ContributionDataClass::CalculatedMetric,
                            'value' => 1,
                            'period_start' => $period['start']->toDateString(),
                            'period_end' => $period['end']->toDateString(),
                            'status' => ContributionRecordStatus::Approved,
                            'event_registration_id' => $registration->id,
                            'calculation_key' => 'event_attendance',
                            'calculation_version' => $category->calculation_version,
                            'calculation_inputs' => [
                                'event_registration_id' => $registration->id,
                                'attendance_status' => EventRegistrationStatus::Attended->value,
                                'value_per_attendance' => 1,
                            ],
                            'recorded_at' => $registration->attendance_recorded_at ?? now(),
                            'recorded_by_user_id' => $actor->id,
                            'approved_at' => now(),
                            'approved_by_user_id' => $actor->id,
                        ]);
                        $result['created']++;

                        continue;
                    }

                    if ($record->status === ContributionRecordStatus::Reversed) {
                        $record->forceFill([
                            'status' => ContributionRecordStatus::Approved,
                            'approved_at' => now(),
                            'approved_by_user_id' => $actor->id,
                            'reversed_at' => null,
                            'reversed_by_user_id' => null,
                            'reversal_reason' => null,
                            'calculation_inputs' => [
                                'event_registration_id' => $registration->id,
                                'attendance_status' => EventRegistrationStatus::Attended->value,
                                'value_per_attendance' => 1,
                            ],
                        ])->save();
                        $result['restored']++;
                    }
                }

                $stale = ContributionRecord::query()
                    ->where('alliance_id', $alliance->id)
                    ->where('category_id', $category->id)
                    ->where('source', ContributionRecordSource::EventParticipation->value)
                    ->where('status', ContributionRecordStatus::Approved->value)
                    ->whereDate('period_start', $period['start']->toDateString())
                    ->whereDate('period_end', $period['end']->toDateString());

                if ($attendedIds !== []) {
                    $stale->whereNotIn('event_registration_id', $attendedIds);
                }

                foreach ($stale->get() as $record) {
                    $record->forceFill([
                        'status' => ContributionRecordStatus::Reversed,
                        'reversed_at' => now(),
                        'reversed_by_user_id' => $actor->id,
                        'reversal_reason' => 'Event attendance no longer qualifies for this calculated record.',
                    ])->save();
                    $result['reversed']++;
                }

                $this->audit->record('contribution.event-participation.reconciled', $actor, $category, $alliance, [
                    'period_start' => $period['start']->toDateString(),
                    'period_end' => $period['end']->toDateString(),
                    'created' => $result['created'],
                    'restored' => $result['restored'],
                    'reversed' => $result['reversed'],
                    'calculation_version' => $category->calculation_version,
                ]);
                $this->outbox->record('contribution.event-participation.reconciled', $alliance->id, $category, [
                    'category_id' => $category->id,
                    'calculation_version' => $category->calculation_version,
                ]);
            });
        }

        return $result;
    }
}
