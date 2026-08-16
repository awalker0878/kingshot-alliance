<?php

declare(strict_types=1);

namespace Tests\Feature\Alliance\Core;

use App\Contexts\Accounts\Models\User;
use App\Contexts\Alliance\Core\Actions\CreateAlliance;
use App\Contexts\Alliance\Core\ValueObjects\TenantContextSnapshot;
use App\Contexts\Alliance\Membership\Enums\MembershipStatus;
use App\Contexts\Alliance\Membership\Models\AllianceMembership;
use App\Contexts\GameWorld\Models\Kingdom;
use App\Contexts\GameWorld\Models\Player;
use App\Shared\Infrastructure\AuditTrail\Models\AuditEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class ActiveAllianceHttpTest extends TestCase
{
    use RefreshDatabase;

    public function test_alliance_route_requires_an_active_player(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/alliance')
            ->assertStatus(409);
    }

    public function test_active_player_without_active_membership_cannot_open_alliance_context(): void
    {
        $user = User::factory()->create();
        $kingdom = Kingdom::query()->create(['number' => 3100, 'status' => 'active']);
        $player = Player::query()->create([
            'user_id' => $user->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => 'context-no-alliance',
            'current_name' => 'No Alliance',
        ]);

        $this->actingAs($user)
            ->withSession([(string) config('game_world.active_player_session_key') => $player->id])
            ->get('/alliance')
            ->assertStatus(409);
    }

    public function test_alliance_creation_uses_active_player_and_preserves_player_context(): void
    {
        $user = User::factory()->create();
        $kingdom = Kingdom::query()->create(['number' => 3101, 'status' => 'active']);
        $player = Player::query()->create([
            'user_id' => $user->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => 'http-create-player',
            'current_name' => 'HTTP Creator',
        ]);
        $sessionKey = (string) config('game_world.active_player_session_key');

        $response = $this->actingAs($user)
            ->withSession([$sessionKey => $player->id])
            ->post('/alliances', [
                'name' => 'Created Through HTTP',
                'slug' => 'created-through-http',
                'language' => 'en',
                'timezone' => 'America/Toronto',
            ]);

        $membership = AllianceMembership::query()
            ->where('player_id', $player->id)
            ->where('status', MembershipStatus::Active->value)
            ->with('alliance')
            ->sole();
        $alliance = $membership->alliance;

        $response->assertRedirect(route('alliance.overview'));
        $response->assertSessionHas($sessionKey, $player->id);
        self::assertSame($kingdom->id, $alliance->kingdom_id);

        $audit = AuditEvent::query()->where('event', 'alliance.created')->sole();
        self::assertSame($alliance->id, $audit->alliance_id);
        self::assertSame($player->id, $audit->actor_player_id);
        self::assertNull($audit->actor_user_id);
        self::assertNotNull($audit->request_id);
        self::assertNotNull($audit->trace_id);
    }

    public function test_switching_owned_players_switches_game_authority_and_alliance_context(): void
    {
        $user = User::factory()->create();
        $kingdom = Kingdom::query()->create(['number' => 3102, 'status' => 'active']);
        $firstPlayer = Player::query()->create([
            'user_id' => $user->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => 'switch-alpha',
            'current_name' => 'Alpha',
        ]);
        $secondPlayer = Player::query()->create([
            'user_id' => $user->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => 'switch-bravo',
            'current_name' => 'Bravo',
        ]);
        $createAlliance = $this->app->make(CreateAlliance::class);
        $first = $createAlliance->handle($firstPlayer, 'First', 'player-context-first');
        $second = $createAlliance->handle($secondPlayer, 'Second', 'player-context-second');
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
                ->where('alliance.id', $second->id)
                ->where('alliance.name', 'Second'));
    }

    public function test_tenant_middleware_exposes_active_player_context_for_downstream_jobs_and_storage(): void
    {
        Route::middleware(['web', 'auth', 'alliance.context'])
            ->get('/_test/tenant-context', static function (Request $request): JsonResponse {
                $snapshot = $request->attributes->get('tenant_context');

                if (! $snapshot instanceof TenantContextSnapshot) {
                    abort(500, 'Tenant snapshot missing.');
                }

                return response()->json($snapshot->toArray());
            });

        $owner = User::factory()->create();
        $kingdom = Kingdom::query()->create(['number' => 3103, 'status' => 'active']);
        $ownerPlayer = Player::query()->create([
            'user_id' => $owner->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => 'snapshot-authority',
            'current_name' => 'Snapshot Authority',
        ]);
        $alliance = $this->app->make(CreateAlliance::class)
            ->handle($ownerPlayer, 'Snapshot Alliance', 'snapshot-alliance');
        $sessionKey = (string) config('game_world.active_player_session_key');

        $response = $this->actingAs($owner)
            ->withSession([$sessionKey => $ownerPlayer->id])
            ->get('/_test/tenant-context');

        $response->assertOk();
        $response->assertJsonPath('alliance_id', $alliance->id);
        $response->assertJsonPath('actor_player_id', $ownerPlayer->id);
        self::assertIsString($response->json('request_id'));
        self::assertIsString($response->json('trace_id'));
    }

    public function test_suspended_membership_invalidates_alliance_context_but_not_player_identity(): void
    {
        $owner = User::factory()->create();
        $kingdom = Kingdom::query()->create(['number' => 3104, 'status' => 'active']);
        $ownerPlayer = Player::query()->create([
            'user_id' => $owner->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => 'suspended-authority',
            'current_name' => 'Suspended Authority',
        ]);
        $alliance = $this->app->make(CreateAlliance::class)
            ->handle($ownerPlayer, 'Suspended', 'suspended');
        $sessionKey = (string) config('game_world.active_player_session_key');

        AllianceMembership::query()
            ->where('alliance_id', $alliance->id)
            ->where('player_id', $ownerPlayer->id)
            ->update(['status' => MembershipStatus::Suspended->value]);

        $this->actingAs($owner)
            ->withSession([$sessionKey => $ownerPlayer->id])
            ->get('/alliance')
            ->assertStatus(409)
            ->assertSessionHas($sessionKey, $ownerPlayer->id);
    }
}
