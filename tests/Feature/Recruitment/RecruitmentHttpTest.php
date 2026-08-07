<?php

declare(strict_types=1);

namespace Tests\Feature\Recruitment;

use App\Domain\Alliances\Actions\CreateAlliance;
use App\Domain\Identity\Models\User;
use App\Domain\Memberships\Enums\MembershipStatus;
use App\Domain\Memberships\Models\AllianceMembership;
use App\Domain\Recruitment\Actions\AddRecruitmentNote;
use App\Domain\Recruitment\Actions\ConfigureRecruitmentSettings;
use App\Domain\Recruitment\Actions\CreateRecruitmentQuestion;
use App\Domain\Recruitment\Actions\IssueRecruitmentApplicationInvite;
use App\Domain\Recruitment\Actions\SubmitRecruitmentApplication;
use App\Domain\Recruitment\Enums\RecruitmentApplicationMode;
use App\Domain\Recruitment\Enums\RecruitmentQuestionType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class RecruitmentHttpTest extends TestCase
{
    use RefreshDatabase;

    public function test_public_application_exposes_questions_but_not_private_candidate_data(): void
    {
        $owner = User::factory()->create();
        $alliance = $this->app->make(CreateAlliance::class)->handle($owner, 'Public Recruiting', 'public-recruiting');
        $this->app->make(ConfigureRecruitmentSettings::class)->handle(
            $owner,
            $alliance,
            RecruitmentApplicationMode::Public,
            'Apply to Public Recruiting',
            'Public introduction',
            90,
            true,
        );
        $question = $this->app->make(CreateRecruitmentQuestion::class)->handle(
            $owner,
            $alliance,
            'Why should we recruit you?',
            RecruitmentQuestionType::LongText,
            true,
        );
        $candidate = $this->app->make(SubmitRecruitmentApplication::class)->handle(
            $alliance,
            'Private Candidate',
            'private@example.com',
            [$question->id => 'Private answer body'],
        );
        $this->app->make(AddRecruitmentNote::class)->handle(
            $owner,
            $alliance,
            $candidate,
            'PRIVATE RECRUITER NOTE MUST NOT LEAK',
        );

        $response = $this->get('/alliances/public-recruiting/apply');

        $response->assertOk();
        $response->assertInertia(fn (Assert $page) => $page
            ->component('Public/RecruitmentApply')
            ->where('alliance.slug', 'public-recruiting')
            ->where('application.open', true)
            ->has('questions', 1)
            ->where('questions.0.id', $question->id)
            ->where('questions.0.prompt', 'Why should we recruit you?')
            ->missing('candidates')
            ->missing('notes')
            ->missing('metrics'));
        self::assertStringNotContainsString('PRIVATE RECRUITER NOTE MUST NOT LEAK', $response->getContent());
        self::assertStringNotContainsString('Private answer body', $response->getContent());
    }

    public function test_public_application_submission_creates_candidate_without_exposing_private_record(): void
    {
        $owner = User::factory()->create();
        $alliance = $this->app->make(CreateAlliance::class)->handle($owner, 'Submit Recruiting', 'submit-recruiting');
        $this->app->make(ConfigureRecruitmentSettings::class)->handle(
            $owner,
            $alliance,
            RecruitmentApplicationMode::Public,
            'Apply',
            null,
            90,
            true,
        );

        $this->post('/alliances/submit-recruiting/apply', [
            'full_name' => 'HTTP Applicant',
            'email' => 'http-applicant@example.com',
            'contact_handle' => 'http-player',
            'source' => 'referral',
            'answers' => [],
        ])->assertRedirect('/alliances/submit-recruiting/apply');

        $this->assertDatabaseHas('recruitment_candidates', [
            'alliance_id' => $alliance->id,
            'full_name' => 'HTTP Applicant',
            'email' => 'http-applicant@example.com',
            'stage' => 'new',
        ]);
    }

    public function test_invitation_only_application_requires_valid_unused_token(): void
    {
        $owner = User::factory()->create();
        $alliance = $this->app->make(CreateAlliance::class)->handle($owner, 'Invite Only Recruiting', 'invite-only-recruiting');
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

        $this->get('/alliances/invite-only-recruiting/apply')->assertNotFound();
        $this->get('/alliances/invite-only-recruiting/apply?token='.str_repeat('a', 64))->assertNotFound();
        $this->get('/alliances/invite-only-recruiting/apply?token='.$issued->token)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('application.mode', 'invitation')
                ->where('application.open', true)
                ->where('prefill.email', 'invited@example.com')
                ->where('prefill.emailLocked', true));

        $this->post('/alliances/invite-only-recruiting/apply', [
            'full_name' => 'Invited HTTP Applicant',
            'email' => 'invited@example.com',
            'application_token' => $issued->token,
            'answers' => [],
        ])->assertRedirect();

        $this->get('/alliances/invite-only-recruiting/apply?token='.$issued->token)->assertNotFound();
    }

    public function test_member_without_recruitment_permission_cannot_open_private_pipeline(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $alliance = $this->app->make(CreateAlliance::class)->handle($owner, 'Private Recruiting', 'private-recruiting');
        AllianceMembership::query()->create([
            'alliance_id' => $alliance->id,
            'user_id' => $member->id,
            'status' => MembershipStatus::Active,
            'joined_at' => now(),
        ]);
        $sessionKey = (string) config('identity.active_alliance_session_key');

        $this->actingAs($member)
            ->withSession([$sessionKey => $alliance->id])
            ->get('/alliance/recruitment')
            ->assertForbidden();
    }

    public function test_private_pipeline_and_candidate_ids_are_scoped_to_active_alliance(): void
    {
        $owner = User::factory()->create();
        $createAlliance = $this->app->make(CreateAlliance::class);
        $first = $createAlliance->handle($owner, 'First Recruiting', 'first-http-recruiting');
        $second = $createAlliance->handle($owner, 'Second Recruiting', 'second-http-recruiting');
        $configure = $this->app->make(ConfigureRecruitmentSettings::class);
        $configure->handle($owner, $first, RecruitmentApplicationMode::Public, 'Apply', null, 90, true);
        $configure->handle($owner, $second, RecruitmentApplicationMode::Public, 'Apply', null, 90, true);
        $submit = $this->app->make(SubmitRecruitmentApplication::class);
        $firstCandidate = $submit->handle($first, 'Visible Candidate', 'visible@example.com', []);
        $secondCandidate = $submit->handle($second, 'Hidden Candidate', 'hidden@example.com', []);
        $sessionKey = (string) config('identity.active_alliance_session_key');

        $this->actingAs($owner)->withSession([
            $sessionKey => $first->id,
            'auth.password_confirmed_at' => time(),
        ]);

        $this->get('/alliance/recruitment')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Alliance/Recruitment/Manage')
                ->where('alliance.id', $first->id)
                ->has('candidates', 1)
                ->where('candidates.0.id', $firstCandidate->id)
                ->where('candidates.0.name', 'Visible Candidate'));

        $this->get('/alliance/recruitment/'.$firstCandidate->id)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Alliance/Recruitment/Candidate')
                ->where('candidate.id', $firstCandidate->id));

        $this->get('/alliance/recruitment/'.$secondCandidate->id)->assertNotFound();
        $this->patch('/alliance/recruitment/'.$secondCandidate->id.'/stage', [
            'stage' => 'screening',
        ])->assertNotFound();
    }

    public function test_recruitment_mutations_require_recent_password_confirmation(): void
    {
        $owner = User::factory()->create();
        $alliance = $this->app->make(CreateAlliance::class)
            ->handle($owner, 'Confirmed Recruiting', 'confirmed-recruiting');
        $sessionKey = (string) config('identity.active_alliance_session_key');

        $this->actingAs($owner)
            ->withSession([$sessionKey => $alliance->id])
            ->post('/alliance/recruitment/questions', [
                'prompt' => 'Blocked until confirmed',
                'help_text' => null,
                'type' => RecruitmentQuestionType::ShortText->value,
                'options' => [],
                'required' => false,
                'position' => 0,
                'active' => true,
            ])
            ->assertRedirect(route('password.confirm'));

        $this->assertDatabaseMissing('recruitment_questions', [
            'alliance_id' => $alliance->id,
            'prompt' => 'Blocked until confirmed',
        ]);
    }

    public function test_recruiter_can_edit_only_active_alliance_questions(): void
    {
        $owner = User::factory()->create();
        $createAlliance = $this->app->make(CreateAlliance::class);
        $first = $createAlliance->handle($owner, 'Editable Recruiting', 'editable-recruiting');
        $second = $createAlliance->handle($owner, 'Foreign Recruiting', 'foreign-recruiting');
        $createQuestion = $this->app->make(CreateRecruitmentQuestion::class);
        $firstQuestion = $createQuestion->handle(
            $owner,
            $first,
            'Original question',
            RecruitmentQuestionType::ShortText,
            false,
        );
        $secondQuestion = $createQuestion->handle(
            $owner,
            $second,
            'Foreign question',
            RecruitmentQuestionType::ShortText,
            false,
        );
        $sessionKey = (string) config('identity.active_alliance_session_key');

        $this->actingAs($owner)->withSession([
            $sessionKey => $first->id,
            'auth.password_confirmed_at' => time(),
        ]);

        $this->post('/alliance/recruitment/questions', [
            'question_id' => $firstQuestion->id,
            'prompt' => 'Updated question',
            'help_text' => 'Choose one option.',
            'type' => RecruitmentQuestionType::Select->value,
            'options' => ['Alpha', 'Bravo'],
            'required' => true,
            'position' => 4,
            'active' => true,
        ])->assertRedirect();

        $this->assertDatabaseHas('recruitment_questions', [
            'id' => $firstQuestion->id,
            'alliance_id' => $first->id,
            'prompt' => 'Updated question',
            'question_type' => RecruitmentQuestionType::Select->value,
            'is_required' => true,
            'position' => 4,
            'is_active' => true,
            'updated_by_user_id' => $owner->id,
        ]);

        $this->post('/alliance/recruitment/questions', [
            'question_id' => $secondQuestion->id,
            'prompt' => 'Attempted foreign edit',
            'help_text' => null,
            'type' => RecruitmentQuestionType::ShortText->value,
            'options' => [],
            'required' => false,
            'position' => 0,
            'active' => true,
        ])->assertNotFound();

        $this->assertDatabaseHas('recruitment_questions', [
            'id' => $secondQuestion->id,
            'alliance_id' => $second->id,
            'prompt' => 'Foreign question',
        ]);
    }

    public function test_alliance_home_exposes_recruitment_navigation_for_authorized_owner(): void
    {
        $owner = User::factory()->create();
        $alliance = $this->app->make(CreateAlliance::class)->handle($owner, 'Recruitment Navigation', 'recruitment-navigation');
        $sessionKey = (string) config('identity.active_alliance_session_key');

        $this->actingAs($owner)
            ->withSession([$sessionKey => $alliance->id])
            ->get('/alliance')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Alliance/Overview')
                ->where('contentHub.canManageRecruitment', true));
    }

    public function test_public_alliance_page_uses_authoritative_recruitment_settings(): void
    {
        $owner = User::factory()->create();
        $alliance = $this->app->make(CreateAlliance::class)->handle($owner, 'Recruiting Public Page', 'recruiting-public-page');
        $configure = $this->app->make(ConfigureRecruitmentSettings::class);
        $configure->handle($owner, $alliance, RecruitmentApplicationMode::Public, 'Apply', null, 90, true);

        $this->get('/alliances/recruiting-public-page')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('alliance.recruitmentStatus', 'open')
                ->where('alliance.recruitmentApplicationUrl', route('public.alliances.recruitment.show', 'recruiting-public-page')));

        $configure->handle($owner, $alliance, RecruitmentApplicationMode::Invitation, 'Invite only', null, 90, true);
        $this->get('/alliances/recruiting-public-page')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('alliance.recruitmentStatus', 'invitation_only')
                ->where('alliance.recruitmentApplicationUrl', null));

        $configure->handle($owner, $alliance, RecruitmentApplicationMode::Invitation, 'Closed', null, 90, false);
        $this->get('/alliances/recruiting-public-page')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('alliance.recruitmentStatus', 'closed')
                ->where('alliance.recruitmentApplicationUrl', null));
    }
}
