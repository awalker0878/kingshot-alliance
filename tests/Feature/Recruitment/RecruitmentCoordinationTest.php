<?php

declare(strict_types=1);

namespace Tests\Feature\Recruitment;

use App\Domain\Alliances\Actions\CreateAlliance;
use App\Domain\Alliances\Models\Alliance;
use App\Domain\Authorization\Enums\DefaultAllianceRole;
use App\Domain\Authorization\Models\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Memberships\Actions\AcceptInvitation;
use App\Domain\Memberships\Enums\MembershipStatus;
use App\Domain\Memberships\Models\AllianceMembership;
use App\Domain\Platform\Actions\PublishOutboxBatch;
use App\Domain\Recruitment\Actions\AddRecruitmentNote;
use App\Domain\Recruitment\Actions\AssignRecruitmentReviewer;
use App\Domain\Recruitment\Actions\ChangeRecruitmentStage;
use App\Domain\Recruitment\Actions\ConfigureRecruitmentSettings;
use App\Domain\Recruitment\Actions\ConvertAcceptedRecruitmentCandidate;
use App\Domain\Recruitment\Actions\CreateRecruitmentDecisionTemplate;
use App\Domain\Recruitment\Actions\CreateRecruitmentOnboardingItem;
use App\Domain\Recruitment\Actions\MarkRecruitmentCommunicationSent;
use App\Domain\Recruitment\Actions\MergeRecruitmentCandidates;
use App\Domain\Recruitment\Actions\PrepareRecruitmentDecisionCommunication;
use App\Domain\Recruitment\Actions\PurgeExpiredRecruitmentCandidates;
use App\Domain\Recruitment\Actions\SubmitRecruitmentApplication;
use App\Domain\Recruitment\Actions\TagRecruitmentCandidate;
use App\Domain\Recruitment\Enums\RecruitmentApplicationMode;
use App\Domain\Recruitment\Enums\RecruitmentCommunicationStatus;
use App\Domain\Recruitment\Enums\RecruitmentOnboardingStatus;
use App\Domain\Recruitment\Enums\RecruitmentStage;
use App\Domain\Recruitment\Models\RecruitmentCandidateOnboarding;
use App\Domain\Recruitment\Models\RecruitmentStageHistory;
use App\Domain\Recruitment\Queries\RecruitmentDuplicateFinder;
use App\Domain\Recruitment\Queries\RecruitmentMetricsQuery;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class RecruitmentCoordinationTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_reviewer_notes_tags_duplicate_detection_and_merge_preserve_provenance(): void
    {
        $owner = User::factory()->create();
        $reviewerUser = User::factory()->create();
        $alliance = $this->app->make(CreateAlliance::class)->handle($owner, 'Recruitment Ops', 'recruitment-ops');
        $this->configurePublicRecruitment($owner, $alliance->id);
        $reviewer = $this->addActiveMember($alliance->id, $reviewerUser);
        $submit = $this->app->make(SubmitRecruitmentApplication::class);
        $source = $submit->handle($alliance, 'Source Candidate', 'source@example.com', [], contactHandle: 'same-player');
        $target = $submit->handle($alliance, 'Target Candidate', 'target@example.com', [], contactHandle: 'same-player');

        $this->app->make(AssignRecruitmentReviewer::class)->handle($owner, $alliance, $source, $reviewer);
        $this->app->make(TagRecruitmentCandidate::class)->handle($owner, $alliance, $source, 'High Priority');
        $note = $this->app->make(AddRecruitmentNote::class)->handle($owner, $alliance, $source, 'Private recruiter context');
        self::assertSame('Private recruiter context', $note->body);

        $duplicates = $this->app->make(RecruitmentDuplicateFinder::class)->forCandidate($alliance, $target);
        self::assertSame([$source->id], $duplicates->pluck('id')->all());

        $merge = $this->app->make(MergeRecruitmentCandidates::class);
        $mergedTarget = $merge->handle($owner, $alliance, $source, $target, 'Verified same player');
        self::assertSame($target->id, $mergedTarget->id);
        self::assertSame($target->id, $source->refresh()->merged_into_id);
        self::assertCount(1, $target->refresh()->reviewers);
        self::assertCount(1, $target->refresh()->tags);
        self::assertSame('high priority', $target->tags->sole()->name);
        self::assertTrue($target->notes()->where('body', 'Merge reason: Verified same player')->exists());
        self::assertSame($target->id, $merge->handle($owner, $alliance, $source->refresh(), $target->refresh())->id);
    }

    public function test_accepted_candidate_conversion_materializes_onboarding_and_invitation_acceptance_marks_joined(): void
    {
        $owner = User::factory()->create();
        $candidateUser = User::factory()->create(['email' => 'accepted@example.com']);
        $alliance = $this->app->make(CreateAlliance::class)->handle($owner, 'Join Pipeline', 'join-pipeline');
        $this->configurePublicRecruitment($owner, $alliance->id);
        $this->app->make(CreateRecruitmentOnboardingItem::class)->handle(
            $owner,
            $alliance,
            'Read alliance rules',
            'Review the member handbook.',
        );
        $candidate = $this->app->make(SubmitRecruitmentApplication::class)->handle(
            $alliance,
            'Accepted Candidate',
            'accepted@example.com',
            [],
            applicant: $candidateUser,
        );
        $changeStage = $this->app->make(ChangeRecruitmentStage::class);
        $candidate = $changeStage->handle($owner, $alliance, $candidate, RecruitmentStage::Screening);
        $candidate = $changeStage->handle($owner, $alliance, $candidate, RecruitmentStage::Accepted);

        $convert = $this->app->make(ConvertAcceptedRecruitmentCandidate::class);
        $converted = $convert->handle($owner, $alliance, $candidate);
        self::assertTrue($converted->wasCreated);
        self::assertNotNull($converted->token);
        self::assertSame($converted->invitation->id, $candidate->refresh()->membership_invitation_id);
        $onboarding = RecruitmentCandidateOnboarding::query()->where('candidate_id', $candidate->id)->sole();
        self::assertSame(RecruitmentOnboardingStatus::Pending, $onboarding->status);

        $repeat = $convert->handle($owner, $alliance, $candidate->refresh());
        self::assertFalse($repeat->wasCreated);
        self::assertNull($repeat->token);
        self::assertSame($converted->invitation->id, $repeat->invitation->id);

        $this->app->make(AcceptInvitation::class)->handle($candidateUser, (string) $converted->token);
        $this->app->make(PublishOutboxBatch::class)->handle(500);

        self::assertSame(RecruitmentStage::Joined, $candidate->refresh()->stage);
        self::assertNotNull($candidate->joined_at);
        self::assertTrue(RecruitmentStageHistory::query()
            ->where('candidate_id', $candidate->id)
            ->where('to_stage', RecruitmentStage::Joined->value)
            ->exists());
    }

    public function test_decision_communication_is_idempotent_and_delivery_is_attributable(): void
    {
        $owner = User::factory()->create();
        $alliance = $this->app->make(CreateAlliance::class)->handle($owner, 'Decision Pipeline', 'decision-pipeline');
        $this->configurePublicRecruitment($owner, $alliance->id);
        $candidate = $this->app->make(SubmitRecruitmentApplication::class)->handle($alliance, 'Declined Candidate', 'declined@example.com', []);
        $candidate = $this->app->make(ChangeRecruitmentStage::class)->handle(
            $owner,
            $alliance,
            $candidate,
            RecruitmentStage::Declined,
            'Insufficient availability',
        );
        $template = $this->app->make(CreateRecruitmentDecisionTemplate::class)->handle(
            $owner,
            $alliance,
            'Decline standard',
            RecruitmentStage::Declined,
            'Update from {{alliance_name}}',
            'Hello {{candidate_name}}, thank you for applying to {{alliance_name}}.',
        );

        $prepare = $this->app->make(PrepareRecruitmentDecisionCommunication::class);
        $first = $prepare->handle($owner, $alliance, $candidate, $template);
        $second = $prepare->handle($owner, $alliance, $candidate, $template);
        self::assertSame($first->id, $second->id);
        self::assertSame('Update from Decision Pipeline', $first->subject);
        self::assertStringContainsString('Declined Candidate', $first->body);
        self::assertSame(RecruitmentCommunicationStatus::Prepared, $first->status);

        $sent = $this->app->make(MarkRecruitmentCommunicationSent::class)->handle($owner, $alliance, $first);
        self::assertSame(RecruitmentCommunicationStatus::Sent, $sent->status);
        self::assertNotNull($sent->sent_at);
        self::assertSame($sent->id, $this->app->make(MarkRecruitmentCommunicationSent::class)
            ->handle($owner, $alliance, $sent)->id);
    }

    public function test_metrics_are_alliance_scoped_and_expired_unsuccessful_candidate_is_anonymized(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-07 12:00:00', 'UTC'));

        $owner = User::factory()->create();
        $otherOwner = User::factory()->create();
        $createAlliance = $this->app->make(CreateAlliance::class);
        $alliance = $createAlliance->handle($owner, 'Metrics Recruiting', 'metrics-recruiting');
        $other = $createAlliance->handle($otherOwner, 'Other Recruiting', 'other-recruiting');
        $this->app->make(ConfigureRecruitmentSettings::class)->handle(
            $owner,
            $alliance,
            RecruitmentApplicationMode::Public,
            'Apply',
            null,
            1,
            true,
        );
        $this->app->make(ConfigureRecruitmentSettings::class)->handle(
            $otherOwner,
            $other,
            RecruitmentApplicationMode::Public,
            'Apply',
            null,
            90,
            true,
        );
        $submit = $this->app->make(SubmitRecruitmentApplication::class);
        $active = $submit->handle($alliance, 'Active Candidate', 'active@example.com', [], source: 'discord');
        $declined = $submit->handle($alliance, 'Expired Candidate', 'expired@example.com', [], source: 'referral');
        $submit->handle($other, 'Other Candidate', 'other@example.com', [], source: 'discord');

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-07 14:00:00', 'UTC'));
        $active = $this->app->make(ChangeRecruitmentStage::class)->handle($owner, $alliance, $active, RecruitmentStage::Screening);
        $declined = $this->app->make(ChangeRecruitmentStage::class)->handle($owner, $alliance, $declined, RecruitmentStage::Declined, 'Retention test reason');

        $metrics = $this->app->make(RecruitmentMetricsQuery::class)->summary($alliance);
        self::assertSame(2, $metrics['total']);
        self::assertSame(1, $metrics['byStage'][RecruitmentStage::Screening->value]);
        self::assertSame(1, $metrics['byStage'][RecruitmentStage::Declined->value]);
        self::assertSame(['discord' => 1, 'referral' => 1], $metrics['bySource']);
        self::assertSame(2.0, $metrics['averageResponseHours']);

        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-09 15:00:00', 'UTC'));
        self::assertSame(1, $this->app->make(PurgeExpiredRecruitmentCandidates::class)->handle());
        $declined->refresh();
        self::assertNotNull($declined->anonymized_at);
        self::assertSame('Deleted candidate', $declined->full_name);
        self::assertStringStartsWith('deleted+', $declined->email);
        self::assertNull($declined->contact_handle);
        self::assertNull($declined->retention_due_at);
        self::assertNull(RecruitmentStageHistory::query()
            ->where('candidate_id', $declined->id)
            ->latest('changed_at')
            ->value('reason'));
        self::assertSame('Active Candidate', $active->refresh()->full_name);
    }

    private function configurePublicRecruitment(User $owner, string $allianceId): void
    {
        $alliance = Alliance::query()->findOrFail($allianceId);
        $this->app->make(ConfigureRecruitmentSettings::class)->handle(
            $owner,
            $alliance,
            RecruitmentApplicationMode::Public,
            'Apply',
            null,
            90,
            true,
        );
    }

    private function addActiveMember(string $allianceId, User $user): AllianceMembership
    {
        $membership = AllianceMembership::query()->create([
            'alliance_id' => $allianceId,
            'user_id' => $user->id,
            'status' => MembershipStatus::Active,
            'joined_at' => now(),
        ]);
        $role = Role::query()
            ->where('alliance_id', $allianceId)
            ->where('key', DefaultAllianceRole::Member->value)
            ->sole();
        $membership->roles()->attach($role->id, ['alliance_id' => $allianceId]);

        return $membership;
    }
}
