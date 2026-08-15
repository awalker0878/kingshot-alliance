<?php

declare(strict_types=1);

namespace Tests\Feature\Recruitment;

use App\Domain\Alliances\Actions\CreateAlliance;
use App\Domain\Alliances\Enums\AllianceStatus;
use App\Contexts\Accounts\Models\User;
use App\Contexts\GameWorld\Models\Kingdom;
use App\Contexts\GameWorld\Models\Player;
use App\Domain\Memberships\Enums\AllianceRank;
use App\Domain\Memberships\Enums\MembershipStatus;
use App\Domain\Memberships\Models\AllianceMembership;
use App\Domain\Recruitment\Actions\ChangeRecruitmentStage;
use App\Domain\Recruitment\Actions\ConfigureRecruitmentSettings;
use App\Domain\Recruitment\Actions\CreateRecruitmentQuestion;
use App\Domain\Recruitment\Actions\IssueRecruitmentApplicationInvite;
use App\Domain\Recruitment\Actions\SubmitRecruitmentApplication;
use App\Domain\Recruitment\Enums\RecruitmentApplicationMode;
use App\Domain\Recruitment\Enums\RecruitmentQuestionType;
use App\Domain\Recruitment\Enums\RecruitmentStage;
use App\Domain\Recruitment\Models\RecruitmentStageHistory;
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
        $kingdom = Kingdom::query()->create(['number' => 2101, 'status' => 'active']);
        $ownerPlayer = Player::query()->create([
            'user_id' => $owner->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => 'recruitment-public-owner',
            'current_name' => 'Recruitment Public Owner',
        ]);
        $alliance = $this->app->make(CreateAlliance::class)->handle($ownerPlayer, 'Recruitment Alliance', 'recruitment-alliance');
        $this->app->make(ConfigureRecruitmentSettings::class)->handle(
            $ownerPlayer,
            $alliance,
            RecruitmentApplicationMode::Public,
            'Apply now',
            'Tell us about yourself.',
            90,
            true,
        );
        $question = $this->app->make(CreateRecruitmentQuestion::class)->handle(
            $ownerPlayer,
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
        $kingdom = Kingdom::query()->create(['number' => 2102, 'status' => 'active']);
        $ownerPlayer = Player::query()->create([
            'user_id' => $owner->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => 'recruitment-invite-owner',
            'current_name' => 'Recruitment Invite Owner',
        ]);
        $alliance = $this->app->make(CreateAlliance::class)->handle($ownerPlayer, 'Invite Recruitment', 'invite-recruitment');
        $this->app->make(ConfigureRecruitmentSettings::class)->handle(
            $ownerPlayer,
            $alliance,
            RecruitmentApplicationMode::Invitation,
            'Invitation application',
            null,
            90,
            true,
        );
        $issued = $this->app->make(IssueRecruitmentApplicationInvite::class)->handle(
            $ownerPlayer,
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

    public function test_public_application_is_rejected_when_alliance_is_not_active(): void
    {
        $owner = User::factory()->create();
        $kingdom = Kingdom::query()->create(['number' => 2107, 'status' => 'active']);
        $ownerPlayer = Player::query()->create([
            'user_id' => $owner->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => 'recruitment-suspended-owner',
            'current_name' => 'Recruitment Suspended Owner',
        ]);
        $alliance = $this->app->make(CreateAlliance::class)->handle($ownerPlayer, 'Suspended Recruitment', 'suspended-recruitment');
        $this->app->make(ConfigureRecruitmentSettings::class)->handle(
            $ownerPlayer,
            $alliance,
            RecruitmentApplicationMode::Public,
            'Apply',
            null,
            90,
            true,
        );
        $alliance->forceFill(['status' => AllianceStatus::Suspended, 'suspended_at' => now()])->save();

        $this->expectException(ValidationException::class);
        $this->app->make(SubmitRecruitmentApplication::class)
            ->handle($alliance, 'Suspended Candidate', 'suspended-candidate@example.com', []);
    }

    public function test_active_duplicate_email_is_rejected(): void
    {
        $owner = User::factory()->create();
        $kingdom = Kingdom::query()->create(['number' => 2103, 'status' => 'active']);
        $ownerPlayer = Player::query()->create([
            'user_id' => $owner->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => 'recruitment-duplicate-owner',
            'current_name' => 'Recruitment Duplicate Owner',
        ]);
        $alliance = $this->app->make(CreateAlliance::class)->handle($ownerPlayer, 'Duplicate Recruitment', 'duplicate-recruitment');
        $this->app->make(ConfigureRecruitmentSettings::class)->handle(
            $ownerPlayer,
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
        $kingdom = Kingdom::query()->create(['number' => 2104, 'status' => 'active']);
        $ownerPlayer = Player::query()->create([
            'user_id' => $owner->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => 'recruitment-stage-owner',
            'current_name' => 'Recruitment Stage Owner',
        ]);
        $memberPlayer = Player::query()->create([
            'user_id' => $memberUser->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => 'recruitment-stage-member',
            'current_name' => 'Recruitment Stage Member',
        ]);
        $alliance = $this->app->make(CreateAlliance::class)->handle($ownerPlayer, 'Stage Recruitment', 'stage-recruitment');
        $this->app->make(ConfigureRecruitmentSettings::class)->handle(
            $ownerPlayer,
            $alliance,
            RecruitmentApplicationMode::Public,
            'Apply',
            null,
            30,
            true,
        );
        $candidate = $this->app->make(SubmitRecruitmentApplication::class)->handle($alliance, 'Stage Candidate', 'stage@example.com', []);

        AllianceMembership::query()->create([
            'alliance_id' => $alliance->id,
            'player_id' => $memberPlayer->id,
            'status' => MembershipStatus::Active,
            'rank' => AllianceRank::R1,
            'joined_at' => now(),
        ]);

        try {
            $this->app->make(ChangeRecruitmentStage::class)->handle(
                $memberPlayer,
                $alliance,
                $candidate,
                RecruitmentStage::Screening,
            );
            self::fail('Expected a normal member Player to be denied recruitment stage changes.');
        } catch (AuthorizationException) {
            self::assertSame(RecruitmentStage::New, $candidate->refresh()->stage);
        }

        $changeStage = $this->app->make(ChangeRecruitmentStage::class);
        $screening = $changeStage->handle($ownerPlayer, $alliance, $candidate, RecruitmentStage::Screening, 'Initial review');
        self::assertSame(RecruitmentStage::Screening, $screening->stage);
        self::assertNotNull($screening->first_responded_at);

        $declined = $changeStage->handle($ownerPlayer, $alliance, $screening, RecruitmentStage::Declined, 'Not a fit');
        self::assertSame(RecruitmentStage::Declined, $declined->stage);
        self::assertSame('2026-09-06', $declined->retention_due_at?->toDateString());
        self::assertSame(3, RecruitmentStageHistory::query()->where('candidate_id', $candidate->id)->count());
    }

    public function test_candidate_from_another_alliance_cannot_be_transitioned_by_sibling_player(): void
    {
        $owner = User::factory()->create();
        $firstKingdom = Kingdom::query()->create(['number' => 2105, 'status' => 'active']);
        $secondKingdom = Kingdom::query()->create(['number' => 2106, 'status' => 'active']);
        $firstPlayer = Player::query()->create([
            'user_id' => $owner->id,
            'current_kingdom_id' => $firstKingdom->id,
            'game_player_id' => 'recruitment-first-owner',
            'current_name' => 'Recruitment First Owner',
        ]);
        $secondPlayer = Player::query()->create([
            'user_id' => $owner->id,
            'current_kingdom_id' => $secondKingdom->id,
            'game_player_id' => 'recruitment-second-owner',
            'current_name' => 'Recruitment Second Owner',
        ]);
        $createAlliance = $this->app->make(CreateAlliance::class);
        $first = $createAlliance->handle($firstPlayer, 'First Recruiting', 'first-recruiting');
        $second = $createAlliance->handle($secondPlayer, 'Second Recruiting', 'second-recruiting');
        $settings = $this->app->make(ConfigureRecruitmentSettings::class);
        $settings->handle($firstPlayer, $first, RecruitmentApplicationMode::Public, 'Apply', null, 90, true);
        $candidate = $this->app->make(SubmitRecruitmentApplication::class)->handle($first, 'Candidate', 'candidate@example.com', []);

        $this->expectException(AuthorizationException::class);
        $this->app->make(ChangeRecruitmentStage::class)->handle(
            $secondPlayer,
            $second,
            $candidate,
            RecruitmentStage::Screening,
        );
    }
}
