<?php

declare(strict_types=1);

namespace Tests\v3\Contexts\GameWorld\Players;

use App\Contexts\Alliance\Membership\Enums\AllianceRank;
use App\Contexts\Alliance\Membership\Models\AllianceMembership;
use App\Contexts\Alliance\Membership\Queries\PlayerIdentityContextQuery;
use App\Contexts\GameWorld\Governance\Queries\KingdomAuthorityFactsQuery;
use App\Contexts\GameWorld\Players\Http\Middleware\RequireCurrentPlayerContextVersion;
use App\Contexts\GameWorld\Players\Services\PlayerAuthorityContextVersion;
use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\v3\Support\ScenarioFactory;
use Tests\v3\TestCase;

final class PlayerAuthorityContextPreconditionV3Test extends TestCase
{
    use RefreshDatabase;

    public function test_game_mutation_rejects_missing_context_version(): void
    {
        $factory = new ScenarioFactory;
        $user = $factory->authUser();
        $this->verify($user);
        $player = $factory->player((int) $user->id, 19101);

        $this->actingAs($user)
            ->withSession([$this->sessionKey() => $player->playerId])
            ->post('/alliances', [])
            ->assertStatus(409)
            ->assertHeader(RequireCurrentPlayerContextVersion::ERROR_HEADER, 'stale')
            ->assertJsonPath('code', 'CONTEXT_STALE');
    }

    public function test_current_context_version_passes_the_staleness_guard(): void
    {
        $factory = new ScenarioFactory;
        $user = $factory->authUser();
        $this->verify($user);
        $player = $factory->player((int) $user->id, 19102);
        $version = $this->versionFor($player);

        $response = $this->actingAs($user)
            ->withSession([$this->sessionKey() => $player->playerId])
            ->withHeader(RequireCurrentPlayerContextVersion::HEADER_NAME, $version)
            ->post('/alliances', []);

        self::assertNotSame(409, $response->getStatusCode());
    }

    public function test_old_tab_mutation_is_rejected_after_active_governor_changes(): void
    {
        $factory = new ScenarioFactory;
        $user = $factory->authUser();
        $this->verify($user);
        $first = $factory->player((int) $user->id, 19103);
        $second = $factory->player((int) $user->id, 19104);
        $oldVersion = $this->versionFor($first);

        $this->actingAs($user)
            ->withSession([$this->sessionKey() => $second->playerId])
            ->withHeader(RequireCurrentPlayerContextVersion::HEADER_NAME, $oldVersion)
            ->post('/alliances', [])
            ->assertStatus(409)
            ->assertJsonPath('code', 'CONTEXT_STALE');
    }

    public function test_old_mutation_is_rejected_after_alliance_rank_changes(): void
    {
        $factory = new ScenarioFactory;
        $user = $factory->authUser();
        $this->verify($user);
        $player = $factory->player((int) $user->id, 19105);
        $factory->alliance($player);
        $oldVersion = $this->versionFor($player);

        AllianceMembership::query()
            ->where('player_id', $player->playerId)
            ->update(['rank' => AllianceRank::R4->value]);

        $this->actingAs($user)
            ->withSession([$this->sessionKey() => $player->playerId])
            ->withHeader(RequireCurrentPlayerContextVersion::HEADER_NAME, $oldVersion)
            ->post('/alliances', [])
            ->assertStatus(409)
            ->assertJsonPath('code', 'CONTEXT_STALE');
    }

    public function test_player_activation_is_exempt_because_it_establishes_the_new_context(): void
    {
        $factory = new ScenarioFactory;
        $user = $factory->authUser();
        $this->verify($user);
        $first = $factory->player((int) $user->id, 19106);
        $second = $factory->player((int) $user->id, 19107);

        $this->actingAs($user)
            ->withSession([$this->sessionKey() => $first->playerId])
            ->post('/players/'.$second->playerId.'/activate')
            ->assertRedirect()
            ->assertSessionHas($this->sessionKey(), $second->playerId);
    }

    private function versionFor(PlayerReference $player): string
    {
        $alliance = app(PlayerIdentityContextQuery::class)
            ->forPlayers([$player->playerId])[$player->playerId] ?? null;
        $kingdomPermissions = app(KingdomAuthorityFactsQuery::class)
            ->findCurrent($player->playerId, $player->kingdomId)
            ?->permissionKeysObservedAtRead ?? [];

        return app(PlayerAuthorityContextVersion::class)->issue(
            $player,
            $alliance,
            $kingdomPermissions,
        );
    }

    private function verify(object $user): void
    {
        $user->forceFill(['email_verified_at' => now()])->save();
    }

    private function sessionKey(): string
    {
        return (string) config('game_world.active_player_session_key');
    }
}
