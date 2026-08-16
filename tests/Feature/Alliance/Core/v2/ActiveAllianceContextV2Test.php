<?php

declare(strict_types=1);

namespace Tests\Feature\Alliance\Core\v2;

use App\Contexts\Accounts\Models\User;
use App\Contexts\Alliance\Core\ValueObjects\TenantContextSnapshot;
use App\Contexts\Alliance\Membership\Enums\MembershipStatus;
use App\Contexts\Alliance\Membership\Models\AllianceMembership;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Support\V2\ScenarioFactory;
use Tests\TestCase;

final class ActiveAllianceContextV2Test extends TestCase
{
    use RefreshDatabase;

    public function test_alliance_context_requires_an_active_player_with_active_membership(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get('/alliance')->assertStatus(409);

        $scenario = (new ScenarioFactory)->playerFor($user, 4510, 'No Alliance', 'game-4510-none');
        $this->actingAs($user)
            ->withSession([(string) config('game_world.active_player_session_key') => $scenario['player']->id])
            ->get('/alliance')
            ->assertStatus(409);
    }

    public function test_http_creation_uses_active_player_preserves_context_and_emits_request_correlated_audit(): void
    {
        $scenario = (new ScenarioFactory)->claimedPlayer(4511, 'HTTP Creator', 'game-4511-http');
        $sessionKey = (string) config('game_world.active_player_session_key');

        $response = $this->actingAs($scenario['user'])
            ->withSession([$sessionKey => $scenario['player']->id])
            ->post('/alliances', [
                'name' => 'HTTP V2 Alliance',
                'slug' => 'http-v2-alliance-4511',
                'language' => 'en',
                'timezone' => 'America/Toronto',
            ]);

        $membership = AllianceMembership::query()
            ->where('player_id', $scenario['player']->id)
            ->where('status', MembershipStatus::Active->value)
            ->with('alliance')
            ->sole();

        $response->assertRedirect(route('alliance.overview'));
        $response->assertSessionHas($sessionKey, $scenario['player']->id);
        self::assertSame($scenario['kingdom']->id, $membership->alliance->kingdom_id);
        self::assertSame(1, DB::table('audit_events')
            ->where('alliance_id', $membership->alliance->id)
            ->where('actor_player_id', $scenario['player']->id)
            ->whereNull('actor_user_id')
            ->where('event', 'alliance.created')
            ->whereNotNull('request_id')
            ->whereNotNull('trace_id')
            ->count());
    }

    public function test_switching_owned_players_switches_alliance_authority_and_visible_context(): void
    {
        $user = User::factory()->create();
        $factory = new ScenarioFactory;
        $firstPlayer = $factory->playerFor($user, 4512, 'Alpha', 'game-4512-a')['player'];
        $secondPlayer = $factory->playerFor($user, 4512, 'Bravo', 'game-4512-b')['player'];
        $first = $factory->allianceForPlayer($firstPlayer, 'First V2', 'first-v2-4512');
        $second = $factory->allianceForPlayer($secondPlayer, 'Second V2', 'second-v2-4512');
        $sessionKey = (string) config('game_world.active_player_session_key');

        $this->actingAs($user)
            ->withSession([$sessionKey => $firstPlayer->id])
            ->get('/alliance')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Alliance/Overview')
                ->where('alliance.id', $first->id));

        $this->post(route('players.activate', ['player' => $secondPlayer->id]))
            ->assertRedirect()
            ->assertSessionHas($sessionKey, $secondPlayer->id);

        $this->get('/alliance')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Alliance/Overview')
                ->where('alliance.id', $second->id));
    }

    public function test_tenant_snapshot_tracks_active_player_and_suspension_invalidates_only_alliance_context(): void
    {
        Route::middleware(['web', 'auth', 'alliance.context'])
            ->get('/_test/v2/tenant-context', static function (Request $request): JsonResponse {
                $snapshot = $request->attributes->get('tenant_context');

                if (! $snapshot instanceof TenantContextSnapshot) {
                    abort(500, 'Tenant snapshot missing.');
                }

                return response()->json($snapshot->toArray());
            });

        $scenario = (new ScenarioFactory)->alliance(4513, 'Snapshot Owner', 'Snapshot V2', 'snapshot-v2-4513');
        $sessionKey = (string) config('game_world.active_player_session_key');

        $this->actingAs($scenario['user'])
            ->withSession([$sessionKey => $scenario['player']->id])
            ->get('/_test/v2/tenant-context')
            ->assertOk()
            ->assertJsonPath('alliance_id', $scenario['alliance']->id)
            ->assertJsonPath('actor_player_id', $scenario['player']->id);

        AllianceMembership::query()
            ->where('alliance_id', $scenario['alliance']->id)
            ->where('player_id', $scenario['player']->id)
            ->update(['status' => MembershipStatus::Suspended->value]);

        $this->get('/alliance')
            ->assertStatus(409)
            ->assertSessionHas($sessionKey, $scenario['player']->id);
    }
}
