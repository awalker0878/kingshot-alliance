<?php

declare(strict_types=1);

namespace Tests\Feature\Recruitment;

use App\Application\Identity\CreateAlliance;
use App\Application\Recruitment\AddRecruitmentNote;
use App\Application\Recruitment\ConfigureRecruitmentSettings;
use App\Application\Recruitment\CreateRecruitmentQuestion;
use App\Application\Recruitment\IssueRecruitmentApplicationInvite;
use App\Application\Recruitment\SubmitRecruitmentApplication;
use App\Domain\Identity\Enums\MembershipStatus;
use App\Domain\Recruitment\Enums\RecruitmentApplicationMode;
use App\Domain\Recruitment\Enums\RecruitmentQuestionType;
use App\Models\AllianceMembership;
use App\Models\User;
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

        $this->actingAs($owner)->withSession([$sessionKey => $first->id]);

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

    public function test_public_alliance_page_advertises_only_open_public_recruitment(): void
    {
        $owner = User::factory()->create();
        $alliance = $this->app->make(CreateAlliance::class)->handle($owner, 'Recruiting Public Page', 'recruiting-public-page');
        $configure = $this->app->make(ConfigureRecruitmentSettings::class);
        $configure->handle($owner, $alliance, RecruitmentApplicationMode::Public, 'Apply', null, 90, true);

        $this->get('/alliances/recruiting-public-page')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('alliance.recruitmentApplicationUrl', route('public.alliances.recruitment.show', 'recruiting-public-page')));

        $configure->handle($owner, $alliance, RecruitmentApplicationMode::Invitation, 'Invite only', null, 90, true);
        $this->get('/alliances/recruiting-public-page')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('alliance.recruitmentApplicationUrl', null));
    }
}
