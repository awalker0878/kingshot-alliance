<?php

declare(strict_types=1);

namespace Tests\Feature\Recruitment;

use App\Application\Identity\CreateAlliance;
use App\Application\Recruitment\ChangeRecruitmentStage;
use App\Application\Recruitment\ConfigureRecruitmentSettings;
use App\Application\Recruitment\CreateRecruitmentQuestion;
use App\Application\Recruitment\IssueRecruitmentApplicationInvite;
use App\Application\Recruitment\SubmitRecruitmentApplication;
use App\Domain\Identity\Authorization\DefaultAllianceRole;
use App\Domain\Identity\Enums\MembershipStatus;
use App\Domain\Recruitment\Enums\RecruitmentApplicationMode;
use App\Domain\Recruitment\Enums\RecruitmentQuestionType;
use App\Domain\Recruitment\Enums\RecruitmentStage;
use App\Models\AllianceMembership;
use App\Models\RecruitmentStageHistory;
use App\Models\Role;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class RecruitmentApplicationTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_public_application_snapshots_required_answers_and_initial_history(): void
    {
        $owner = User::factory()->create();
        $alliance = $this->app->make(CreateAlliance::class)->handle($owner, 'Recruitment Alliance', 'recruitment-alliance');
        $this->app->make(ConfigureRecruitmentSettings::class)->handle(
            $owner,
            $alliance,
            RecruitmentApplicationMode::Public,
            'Apply now',
            'Tell us about yourself.',
            90,
            true,
        );
        $question = $this->app->make(CreateRecruitmentQuestion::class)->handle(
            $owner,
            $alliance,
            'Why do you want to join?',
            RecruitmentQuestionType::LongText,
            true,
        );

        $candidate = $this->app->make(SubmitRecruitmentApplication::class)->handle(
            alliance: $alliance,
            fullName: '  Candidate One  ',
            email: 'Candidate@Example.COM',
            answers: [$question->id => 'Because I want to help.'],
            contactHandle: 'candidate#1',
            source: 'discord',
        );

        self::assertSame('Candidate One', $candidate->full_name);
        self::assertSame('candidate@example.com', $candidate->email);
        self::assertSame(RecruitmentStage::New, $candidate->stage);
        self::assertCount(1, $candidate->answers);
        self::assertSame('Why do you want to join?', $candidate->answers->sole()->prompt_snapshot);
        self::assertSame(['value' => 'Because I want to help.'], $candidate->answers->sole()->answer);

        $history = RecruitmentStageHistory::query()->where('candidate_id', $candidate->id)->sole();
        self::assertNull($history->from_stage);
        self::assertSame(RecruitmentStage::New, $history->to_stage);
    }

    public function test_invitation_application_token_is_email_restricted_and_single_use(): void
    {
        $owner = User::factory()->create();
        $alliance = $this->app->make(CreateAlliance::class)->handle($owner, 'Invite Recruitment', 'invite-recruitment');
        $this->app->make(ConfigureRecruitmentSettings::class)->handle(
            $owner,
            $alliance,
            RecruitmentApplicationMode::Invitation,
            'Invitation application',
            null,
            90,
            true,
        );
        $issued = $this->app->make(IssueRecruitmentApplicationInvite::class)->handle(
            $owner,
            $alliance,
            'invited@example.com',
        );

        try {
            $this->app->make(SubmitRecruitmentApplication::class)->handle(
                $alliance,
                'Wrong Email',
                'wrong@example.com',
                [],
                applicationToken: $issued->token,
            );
            self::fail('Expected an email-restricted recruitment invitation to reject another address.');
        } catch (ValidationException) {
            self::assertDatabaseMissing('recruitment_candidates', ['email' => 'wrong@example.com']);
        }

        $candidate = $this->app->make(SubmitRecruitmentApplication::class)->handle(
            $alliance,
            'Invited Candidate',
            'INVITED@example.com',
            [],
            applicationToken: $issued->token,
        );
        self::assertSame($issued->invite->id, $candidate->application_invite_id);
        self::assertNotNull($issued->invite->refresh()->used_at);

        $this->expectException(ValidationException::class);
        $this->app->make(SubmitRecruitmentApplication::class)->handle(
            $alliance,
            'Second Candidate',
            'invited@example.com',
            [],
            applicationToken: $issued->token,
        );
    }

    public function test_active_duplicate_email_is_rejected(): void
    {
        $owner = User::factory()->create();
        $alliance = $this->app->make(CreateAlliance::class)->handle($owner, 'Duplicate Recruitment', 'duplicate-recruitment');
        $this->app->make(ConfigureRecruitmentSettings::class)->handle(
            $owner,
            $alliance,
            RecruitmentApplicationMode::Public,
            'Apply',
            null,
            90,
            true,
        );
        $submit = $this->app->make(SubmitRecruitmentApplication::class);
        $submit->handle($alliance, 'First Candidate', 'same@example.com', []);

        $this->expectException(ValidationException::class);
        $submit->handle($alliance, 'Duplicate Candidate', 'SAME@example.com', []);
    }

    public function test_stage_transition_requires_recruiter_permission_and_sets_retention_due_date(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-08-07 15:00:00', 'UTC'));

        $owner = User::factory()->create();
        $memberUser = User::factory()->create();
        $alliance = $this->app->make(CreateAlliance::class)->handle($owner, 'Stage Recruitment', 'stage-recruitment');
        $this->app->make(ConfigureRecruitmentSettings::class)->handle(
            $owner,
            $alliance,
            RecruitmentApplicationMode::Public,
            'Apply',
            null,
            30,
            true,
        );
        $candidate = $this->app->make(SubmitRecruitmentApplication::class)->handle($alliance, 'Stage Candidate', 'stage@example.com', []);

        $membership = AllianceMembership::query()->create([
            'alliance_id' => $alliance->id,
            'user_id' => $memberUser->id,
            'status' => MembershipStatus::Active,
            'joined_at' => now(),
        ]);
        $memberRole = Role::query()
            ->where('alliance_id', $alliance->id)
            ->where('key', DefaultAllianceRole::Member->value)
            ->sole();
        $membership->roles()->attach($memberRole->id, ['alliance_id' => $alliance->id]);

        try {
            $this->app->make(ChangeRecruitmentStage::class)->handle(
                $memberUser,
                $alliance,
                $candidate,
                RecruitmentStage::Screening,
            );
            self::fail('Expected a normal member to be denied recruitment stage changes.');
        } catch (AuthorizationException) {
            self::assertSame(RecruitmentStage::New, $candidate->refresh()->stage);
        }

        $changeStage = $this->app->make(ChangeRecruitmentStage::class);
        $screening = $changeStage->handle($owner, $alliance, $candidate, RecruitmentStage::Screening, 'Initial review');
        self::assertSame(RecruitmentStage::Screening, $screening->stage);
        self::assertNotNull($screening->first_responded_at);

        $declined = $changeStage->handle($owner, $alliance, $screening, RecruitmentStage::Declined, 'Not a fit');
        self::assertSame(RecruitmentStage::Declined, $declined->stage);
        self::assertSame('2026-09-06', $declined->retention_due_at?->toDateString());
        self::assertSame(3, RecruitmentStageHistory::query()->where('candidate_id', $candidate->id)->count());
    }

    public function test_candidate_from_another_alliance_cannot_be_transitioned(): void
    {
        $owner = User::factory()->create();
        $createAlliance = $this->app->make(CreateAlliance::class);
        $first = $createAlliance->handle($owner, 'First Recruiting', 'first-recruiting');
        $second = $createAlliance->handle($owner, 'Second Recruiting', 'second-recruiting');
        $settings = $this->app->make(ConfigureRecruitmentSettings::class);
        $settings->handle($owner, $first, RecruitmentApplicationMode::Public, 'Apply', null, 90, true);
        $candidate = $this->app->make(SubmitRecruitmentApplication::class)->handle($first, 'Candidate', 'candidate@example.com', []);

        $this->expectException(AuthorizationException::class);
        $this->app->make(ChangeRecruitmentStage::class)->handle(
            $owner,
            $second,
            $candidate,
            RecruitmentStage::Screening,
        );
    }
}
