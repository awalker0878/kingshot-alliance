<?php

declare(strict_types=1);

namespace Tests\Feature\Contributions;

use App\Domain\Alliances\Actions\CreateAlliance;
use App\Domain\Alliances\Models\Alliance;
use App\Domain\Authorization\Enums\DefaultAllianceRole;
use App\Domain\Authorization\Models\Role;
use App\Domain\Contributions\Actions\ApproveContributionRecord;
use App\Domain\Contributions\Actions\CorrectContributionRecord;
use App\Domain\Contributions\Actions\CreateContributionCategory;
use App\Domain\Contributions\Actions\CreateContributionReportSchedule;
use App\Domain\Contributions\Actions\ReconcileEventParticipationContributions;
use App\Domain\Contributions\Actions\RecordContribution;
use App\Domain\Contributions\Actions\ReverseContributionRecord;
use App\Domain\Contributions\Enums\ContributionDataClass;
use App\Domain\Contributions\Enums\ContributionPeriod;
use App\Domain\Contributions\Enums\ContributionRecordSource;
use App\Domain\Contributions\Enums\ContributionRecordStatus;
use App\Domain\Contributions\Models\ContributionRecord;
use App\Domain\Contributions\Models\ContributionReportRun;
use App\Domain\Contributions\Services\ContributionReportExporter;
use App\Domain\Events\Enums\EventRegistrationStatus;
use App\Domain\Events\Models\Event;
use App\Domain\Events\Models\EventOccurrence;
use App\Domain\Events\Models\EventRegistration;
use App\Domain\Identity\Models\User;
use App\Domain\Memberships\Enums\MembershipStatus;
use App\Domain\Memberships\Models\AllianceMembership;
use App\Domain\Notifications\Actions\QueueDueContributionReports;
use App\Domain\Platform\Models\OutboxMessage;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ContributionWorkflowTest extends TestCase
{
    use RefreshDatabase;

    public function test_self_report_approval_correction_and_reversal_preserve_history(): void
    {
        $owner = User::factory()->create();
        $memberUser = User::factory()->create();
        $alliance = $this->app->make(CreateAlliance::class)->handle($owner, 'Contribution Ops', 'contribution-ops');
        $member = $this->addActiveMember($alliance, $memberUser);
        $category = $this->app->make(CreateContributionCategory::class)->handle(
            $owner,
            $alliance,
            'Donation points',
            'points',
            ContributionPeriod::Weekly,
            ContributionDataClass::RecordedFact,
            goalValue: 100,
            evidenceRequired: true,
            allowSelfReport: true,
        );

        $record = $this->app->make(RecordContribution::class)->handle(
            $memberUser,
            $alliance,
            $member,
            $category,
            40,
            ContributionRecordSource::SelfReported,
            'Screenshot reference 123',
        );
        self::assertSame(ContributionRecordStatus::Pending, $record->status);

        $approved = $this->app->make(ApproveContributionRecord::class)->handle($owner, $alliance, $record);
        self::assertSame(ContributionRecordStatus::Approved, $approved->status);

        $corrected = $this->app->make(CorrectContributionRecord::class)->handle(
            $owner,
            $alliance,
            $approved,
            45,
            'Screenshot was read incorrectly.',
        );
        self::assertSame($approved->id, $corrected->correction_of_record_id);
        self::assertSame(ContributionRecordStatus::Reversed, $approved->refresh()->status);
        self::assertSame(ContributionRecordStatus::Approved, $corrected->status);
        self::assertSame('45.00', $corrected->value);

        $this->app->make(ReverseContributionRecord::class)->handle(
            $owner,
            $alliance,
            $corrected,
            'Duplicate evidence discovered.',
        );
        self::assertSame(ContributionRecordStatus::Reversed, $corrected->refresh()->status);
        self::assertSame(2, ContributionRecord::query()->where('alliance_id', $alliance->id)->count());
    }

    public function test_event_attendance_reconciliation_is_versioned_idempotent_and_reversible(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-07 16:00:00', 'UTC'));
        $owner = User::factory()->create();
        $memberUser = User::factory()->create();
        $alliance = $this->app->make(CreateAlliance::class)->handle($owner, 'Attendance Metrics', 'attendance-metrics');
        $member = $this->addActiveMember($alliance, $memberUser);
        $category = $this->app->make(CreateContributionCategory::class)->handle(
            $owner,
            $alliance,
            'Event participation',
            'events',
            ContributionPeriod::Monthly,
            ContributionDataClass::CalculatedMetric,
            goalValue: 4,
            calculationKey: 'event_attendance',
            calculationVersion: '1',
            calculationDescription: 'One point is awarded for each event registration recorded as attended.',
        );
        $event = Event::query()->create([
            'alliance_id' => $alliance->id,
            'title' => 'Bear Trap',
            'timezone' => 'UTC',
            'starts_at' => now()->subHour(),
            'duration_minutes' => 30,
            'registration_closes_minutes_before' => 0,
            'recurrence_frequency' => 'none',
            'recurrence_interval' => 1,
            'status' => 'published',
            'created_by_user_id' => $owner->id,
            'updated_by_user_id' => $owner->id,
        ]);
        $occurrence = EventOccurrence::query()->create([
            'alliance_id' => $alliance->id,
            'event_id' => $event->id,
            'starts_at' => now()->subHour(),
            'ends_at' => now()->subMinutes(30),
            'registration_closes_at' => now()->subHour(),
            'status' => 'completed',
        ]);
        $registration = EventRegistration::query()->create([
            'alliance_id' => $alliance->id,
            'occurrence_id' => $occurrence->id,
            'membership_id' => $member->id,
            'status' => EventRegistrationStatus::Attended,
            'registered_at' => now()->subDays(2),
            'attendance_recorded_at' => now(),
            'attendance_recorded_by_user_id' => $owner->id,
        ]);

        $reconcile = $this->app->make(ReconcileEventParticipationContributions::class);
        self::assertSame(['created' => 1, 'restored' => 0, 'reversed' => 0], $reconcile->handle($owner, $alliance));
        self::assertSame(['created' => 0, 'restored' => 0, 'reversed' => 0], $reconcile->handle($owner, $alliance));
        $record = ContributionRecord::query()->where('category_id', $category->id)->sole();
        self::assertSame(ContributionRecordSource::EventParticipation, $record->source);
        self::assertSame('1', $record->calculation_version);
        self::assertSame(ContributionRecordStatus::Approved, $record->status);

        $registration->forceFill(['status' => EventRegistrationStatus::NoShow])->save();
        self::assertSame(1, $reconcile->handle($owner, $alliance)['reversed']);
        self::assertSame(ContributionRecordStatus::Reversed, $record->refresh()->status);

        $registration->forceFill(['status' => EventRegistrationStatus::Attended])->save();
        self::assertSame(1, $reconcile->handle($owner, $alliance)['restored']);
        self::assertSame(ContributionRecordStatus::Approved, $record->refresh()->status);
        CarbonImmutable::setTestNow();
    }

    public function test_exports_are_versioned_checksummed_and_audited_as_report_runs(): void
    {
        $owner = User::factory()->create();
        $alliance = $this->app->make(CreateAlliance::class)->handle($owner, 'Report Export', 'report-export');
        $ownerMembership = AllianceMembership::query()
            ->where('alliance_id', $alliance->id)
            ->where('user_id', $owner->id)
            ->sole();
        $category = $this->app->make(CreateContributionCategory::class)->handle(
            $owner,
            $alliance,
            'Helping members',
            'actions',
            ContributionPeriod::Monthly,
            ContributionDataClass::RecordedFact,
        );
        $record = $this->app->make(RecordContribution::class)->handle(
            $owner,
            $alliance,
            $ownerMembership,
            $category,
            2,
            ContributionRecordSource::Manual,
        );
        $this->app->make(ApproveContributionRecord::class)->handle($owner, $alliance, $record);

        $exporter = $this->app->make(ContributionReportExporter::class);
        $csv = $exporter->export($alliance, $owner, 'csv');
        self::assertStringContainsString('report_version,alliance_id,record_id', $csv['content']);
        self::assertStringContainsString('phase5.v1', $csv['content']);
        self::assertSame(hash('sha256', $csv['content']), $csv['run']->checksum);
        self::assertSame('completed', $csv['run']->status);

        $spreadsheet = $exporter->export($alliance, $owner, 'spreadsheet');
        self::assertStringContainsString('Excel.Sheet', $spreadsheet['content']);
        self::assertSame(2, ContributionReportRun::query()->where('alliance_id', $alliance->id)->count());
    }

    public function test_scheduled_reports_queue_once_and_advance_in_recipient_timezone(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-07 16:00:00', 'UTC'));
        $owner = User::factory()->create();
        $alliance = $this->app->make(CreateAlliance::class)->handle($owner, 'Scheduled Reports', 'scheduled-reports');
        $membership = AllianceMembership::query()
            ->where('alliance_id', $alliance->id)
            ->where('user_id', $owner->id)
            ->sole();
        $schedule = $this->app->make(CreateContributionReportSchedule::class)->handle(
            $owner,
            $alliance,
            $membership,
            'Weekly summary',
            'weekly',
            'America/Toronto',
            CarbonImmutable::now()->subMinute(),
        );

        $queue = $this->app->make(QueueDueContributionReports::class);
        self::assertSame(1, $queue->handle());
        self::assertSame(0, $queue->handle());
        self::assertTrue($schedule->refresh()->next_due_at->isFuture());
        self::assertSame(1, ContributionReportRun::query()->where('schedule_id', $schedule->id)->count());
        self::assertSame(1, OutboxMessage::query()
            ->where('event_type', 'contribution.report.requested')
            ->where('alliance_id', $alliance->id)
            ->count());
        CarbonImmutable::setTestNow();
    }

    private function addActiveMember(Alliance $alliance, User $user): AllianceMembership
    {
        $membership = AllianceMembership::query()->create([
            'alliance_id' => $alliance->id,
            'user_id' => $user->id,
            'status' => MembershipStatus::Active,
            'joined_at' => now(),
        ]);
        $role = Role::query()
            ->where('alliance_id', $alliance->id)
            ->where('key', DefaultAllianceRole::Member->value)
            ->sole();
        $membership->roles()->attach($role->id, ['alliance_id' => $alliance->id]);

        return $membership;
    }
}
