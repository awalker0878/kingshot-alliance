<?php

declare(strict_types=1);

namespace Tests\TenantIsolation\Contributions;

use App\Domain\Alliances\Actions\CreateAlliance;
use App\Domain\Alliances\Models\Alliance;
use App\Domain\Contributions\Actions\CreateContributionCategory;
use App\Domain\Contributions\Actions\RecordContribution;
use App\Domain\Contributions\Enums\ContributionDataClass;
use App\Domain\Contributions\Enums\ContributionPeriod;
use App\Domain\Contributions\Enums\ContributionRecordSource;
use App\Domain\Identity\Models\User;
use App\Domain\Memberships\Actions\AcceptInvitation;
use App\Domain\Memberships\Actions\CreateInvitation;
use App\Domain\Memberships\Models\AllianceMembership;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ContributionAuthorizationIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_can_view_own_reporting_but_cannot_open_management_dashboard(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create(['email' => 'contribution-member@example.com']);
        $alliance = $this->app->make(CreateAlliance::class)->handle($owner, 'Contribution Access', 'contribution-access');
        $invitation = $this->app->make(CreateInvitation::class)->handle($alliance, $owner, $member->email);
        $this->app->make(AcceptInvitation::class)->handle($member, $invitation->token);
        $sessionKey = (string) config('identity.active_alliance_session_key');

        $this->actingAs($member)
            ->withSession([$sessionKey => $alliance->id])
            ->get('/alliance/contributions')
            ->assertOk();

        $this->actingAs($member)
            ->withSession([$sessionKey => $alliance->id])
            ->get('/alliance/contributions/manage')
            ->assertForbidden();
    }

    public function test_privileged_contribution_mutations_require_recent_password_confirmation(): void
    {
        $owner = User::factory()->create();
        $alliance = $this->app->make(CreateAlliance::class)->handle($owner, 'Contribution Confirmation', 'contribution-confirmation');
        $sessionKey = (string) config('identity.active_alliance_session_key');

        $response = $this->actingAs($owner)
            ->withSession([$sessionKey => $alliance->id])
            ->post('/alliance/contributions/categories', [
                'name' => 'Needs confirmation',
                'unit' => 'points',
                'period' => ContributionPeriod::Weekly->value,
                'goal_value' => 100,
                'evidence_required' => false,
                'allow_self_report' => true,
                'leaderboard_enabled' => true,
                'data_class' => ContributionDataClass::RecordedFact->value,
            ]);

        $response->assertRedirect(route('password.confirm'));
        $this->assertDatabaseMissing('contribution_categories', [
            'alliance_id' => $alliance->id,
            'name' => 'Needs confirmation',
        ]);
    }

    public function test_cross_alliance_contribution_record_identifier_fails_closed(): void
    {
        $firstOwner = User::factory()->create();
        $secondOwner = User::factory()->create();
        $createAlliance = $this->app->make(CreateAlliance::class);
        $first = $createAlliance->handle($firstOwner, 'First Contribution Tenant', 'first-contribution-tenant');
        $second = $createAlliance->handle($secondOwner, 'Second Contribution Tenant', 'second-contribution-tenant');
        $secondMembership = AllianceMembership::query()
            ->where('alliance_id', $second->id)
            ->where('user_id', $secondOwner->id)
            ->sole();
        $secondCategory = $this->app->make(CreateContributionCategory::class)->handle(
            $secondOwner,
            $second,
            'Second tenant points',
            'points',
            ContributionPeriod::Weekly,
            ContributionDataClass::RecordedFact,
        );
        $foreignRecord = $this->app->make(RecordContribution::class)->handle(
            $secondOwner,
            $second,
            $secondMembership,
            $secondCategory,
            10,
            ContributionRecordSource::Manual,
        );
        $sessionKey = (string) config('identity.active_alliance_session_key');

        $this->actingAs($firstOwner)
            ->withSession([
                $sessionKey => $first->id,
                'auth.password_confirmed_at' => time(),
            ])
            ->patch('/alliance/contributions/records/'.$foreignRecord->id.'/approve')
            ->assertNotFound();
    }
}
