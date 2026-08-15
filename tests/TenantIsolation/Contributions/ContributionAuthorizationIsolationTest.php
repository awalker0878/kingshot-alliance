<?php

declare(strict_types=1);

namespace Tests\TenantIsolation\Contributions;

use App\Contexts\Accounts\Models\User;
use App\Contexts\Alliance\Core\Actions\CreateAlliance;
use App\Contexts\Alliance\Membership\Enums\AllianceRank;
use App\Contexts\Alliance\Membership\Enums\MembershipStatus;
use App\Contexts\Alliance\Membership\Models\AllianceMembership;
use App\Contexts\GameWorld\Models\Kingdom;
use App\Contexts\GameWorld\Models\Player;
use App\Domain\Contributions\Actions\CreateContributionCategory;
use App\Domain\Contributions\Actions\RecordContribution;
use App\Domain\Contributions\Enums\ContributionDataClass;
use App\Domain\Contributions\Enums\ContributionPeriod;
use App\Domain\Contributions\Enums\ContributionRecordSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class ContributionAuthorizationIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_member_can_view_own_reporting_but_cannot_open_management_dashboard(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create(['email' => 'contribution-member@example.com']);
        $kingdom = Kingdom::query()->create(['number' => 4201]);
        $ownerPlayer = Player::query()->create([
            'user_id' => $owner->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => 'contribution-owner',
            'current_name' => 'Contribution Owner',
        ]);
        $memberPlayer = Player::query()->create([
            'user_id' => $member->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => 'contribution-member',
            'current_name' => 'Contribution Member',
        ]);
        $alliance = $this->app->make(CreateAlliance::class)->handle($ownerPlayer, 'Contribution Access', 'contribution-access');
        AllianceMembership::query()->create([
            'alliance_id' => $alliance->id,
            'player_id' => $memberPlayer->id,
            'status' => MembershipStatus::Active,
            'rank' => AllianceRank::R1,
            'joined_at' => now(),
        ]);
        $sessionKey = (string) config('game_world.active_player_session_key');

        $this->actingAs($member)
            ->withSession([$sessionKey => $memberPlayer->id])
            ->get('/alliance/contributions')
            ->assertOk();

        $this->actingAs($member)
            ->withSession([$sessionKey => $memberPlayer->id])
            ->get('/alliance/contributions/manage')
            ->assertForbidden();
    }

    public function test_privileged_contribution_mutations_require_recent_password_confirmation(): void
    {
        $owner = User::factory()->create();
        $kingdom = Kingdom::query()->create(['number' => 4211]);
        $ownerPlayer = Player::query()->create([
            'user_id' => $owner->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => 'contribution-confirmation-owner',
            'current_name' => 'Contribution Confirmation Owner',
        ]);
        $alliance = $this->app->make(CreateAlliance::class)->handle($ownerPlayer, 'Contribution Confirmation', 'contribution-confirmation');
        $sessionKey = (string) config('game_world.active_player_session_key');

        $response = $this->actingAs($owner)
            ->withSession([$sessionKey => $ownerPlayer->id])
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
        $firstKingdom = Kingdom::query()->create(['number' => 4221]);
        $secondKingdom = Kingdom::query()->create(['number' => 4222]);
        $firstPlayer = Player::query()->create([
            'user_id' => $firstOwner->id,
            'current_kingdom_id' => $firstKingdom->id,
            'game_player_id' => 'contribution-tenant-owner-1',
            'current_name' => 'Contribution Tenant One',
        ]);
        $secondPlayer = Player::query()->create([
            'user_id' => $secondOwner->id,
            'current_kingdom_id' => $secondKingdom->id,
            'game_player_id' => 'contribution-tenant-owner-2',
            'current_name' => 'Contribution Tenant Two',
        ]);
        $createAlliance = $this->app->make(CreateAlliance::class);
        $first = $createAlliance->handle($firstPlayer, 'First Contribution Tenant', 'first-contribution-tenant');
        $second = $createAlliance->handle($secondPlayer, 'Second Contribution Tenant', 'second-contribution-tenant');
        $secondCategory = $this->app->make(CreateContributionCategory::class)->handle(
            $secondPlayer,
            $second,
            'Second tenant points',
            'points',
            ContributionPeriod::Weekly,
            ContributionDataClass::RecordedFact,
        );
        $foreignRecord = $this->app->make(RecordContribution::class)->handle(
            $secondPlayer,
            $second,
            $secondPlayer,
            $secondCategory,
            10,
            ContributionRecordSource::Manual,
        );
        $sessionKey = (string) config('game_world.active_player_session_key');

        $this->actingAs($firstOwner)
            ->withSession([
                $sessionKey => $firstPlayer->id,
                'auth.password_confirmed_at' => time(),
            ])
            ->patch('/alliance/contributions/records/'.$foreignRecord->id.'/approve')
            ->assertNotFound();
    }
}
