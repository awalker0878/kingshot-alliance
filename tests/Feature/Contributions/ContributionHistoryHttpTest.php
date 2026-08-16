<?php

declare(strict_types=1);

namespace Tests\Feature\Contributions;

use App\Contexts\Accounts\Models\User;
use App\Contexts\Alliance\Core\Actions\CreateAlliance;
use App\Contexts\GameWorld\Models\Kingdom;
use App\Contexts\GameWorld\Models\Player;
use App\Contexts\Intelligence\Contributions\Actions\CreateContributionCategory;
use App\Contexts\Intelligence\Contributions\Actions\RecordContribution;
use App\Contexts\Intelligence\Contributions\Enums\ContributionDataClass;
use App\Contexts\Intelligence\Contributions\Enums\ContributionPeriod;
use App\Contexts\Intelligence\Contributions\Enums\ContributionRecordSource;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class ContributionHistoryHttpTest extends TestCase
{
    use RefreshDatabase;

    public function test_personal_history_route_uses_only_the_selected_active_player(): void
    {
        $user = User::factory()->create();
        $kingdom = Kingdom::query()->create(['number' => 8941, 'status' => 'active']);
        $first = $this->player($user, $kingdom, 'History Alpha', '8941-alpha');
        $second = $this->player($user, $kingdom, 'History Bravo', '8941-bravo');
        $alliance = $this->app->make(CreateAlliance::class)->handle($first, 'History HTTP', 'history-http');
        $category = $this->app->make(CreateContributionCategory::class)->handle(
            $first,
            $alliance,
            'History Points',
            'points',
            ContributionPeriod::Weekly,
            ContributionDataClass::RecordedFact,
            allowSelfReport: true,
        );
        $this->app->make(RecordContribution::class)->handle(
            $first,
            $alliance,
            $first,
            $category,
            12,
            ContributionRecordSource::SelfReported,
        );

        $sessionKey = (string) config('game_world.active_player_session_key');

        $this->actingAs($user)
            ->withSession([$sessionKey => $second->id])
            ->get(route('contributions.history'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Contributions/History')
                ->where('player.id', $second->id)
                ->where('player.name', 'History Bravo')
                ->has('history', 0));

        $this->withSession([$sessionKey => $first->id])
            ->get(route('contributions.history'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Contributions/History')
                ->where('player.id', $first->id)
                ->has('history', 1)
                ->where('history.0.kind', 'contribution')
                ->where('history.0.contribution.playerId', $first->id));
    }

    public function test_alliance_history_route_requires_current_active_player_authority(): void
    {
        $user = User::factory()->create();
        $kingdom = Kingdom::query()->create(['number' => 8942, 'status' => 'active']);
        $leader = $this->player($user, $kingdom, 'Alliance Leader', '8942-leader');
        $sibling = $this->player($user, $kingdom, 'Sibling Player', '8942-sibling');
        $alliance = $this->app->make(CreateAlliance::class)->handle($leader, 'Authority History', 'authority-history-http');
        $sessionKey = (string) config('game_world.active_player_session_key');

        $this->actingAs($user)
            ->withSession([$sessionKey => $leader->id])
            ->get(route('alliances.events.history', ['alliance' => $alliance->id]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Events/OrganizationHistory')
                ->where('organization.id', $alliance->id)
                ->where('organization.scope', 'alliance'));

        $this->withSession([$sessionKey => $sibling->id])
            ->get(route('alliances.events.history', ['alliance' => $alliance->id]))
            ->assertForbidden();
    }

    private function player(User $user, Kingdom $kingdom, string $name, string $gamePlayerId): Player
    {
        return Player::query()->create([
            'user_id' => $user->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => $gamePlayerId,
            'current_name' => $name,
        ]);
    }
}
