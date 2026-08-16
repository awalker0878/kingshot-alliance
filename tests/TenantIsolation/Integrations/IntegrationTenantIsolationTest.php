<?php

declare(strict_types=1);

namespace Tests\TenantIsolation\Integrations;

use App\Contexts\Accounts\Models\User;
use App\Contexts\Alliance\Core\Actions\CreateAlliance;
use App\Contexts\GameWorld\Models\Kingdom;
use App\Contexts\GameWorld\Models\Player;
use App\Contexts\Platform\Integrations\Actions\CreateApiCredential;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class IntegrationTenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_credential_cannot_read_another_alliance(): void
    {
        $firstOwner = User::factory()->create();
        $secondOwner = User::factory()->create();
        $firstKingdom = Kingdom::query()->create(['number' => 4101]);
        $secondKingdom = Kingdom::query()->create(['number' => 4102]);
        $firstPlayer = Player::query()->create([
            'user_id' => $firstOwner->id,
            'current_kingdom_id' => $firstKingdom->id,
            'game_player_id' => 'integration-owner-1',
            'current_name' => 'Integration Owner One',
        ]);
        $secondPlayer = Player::query()->create([
            'user_id' => $secondOwner->id,
            'current_kingdom_id' => $secondKingdom->id,
            'game_player_id' => 'integration-owner-2',
            'current_name' => 'Integration Owner Two',
        ]);

        $createAlliance = $this->app->make(CreateAlliance::class);
        $first = $createAlliance->handle($firstPlayer, 'First API Tenant', 'first-api-tenant');
        $second = $createAlliance->handle($secondPlayer, 'Second API Tenant', 'second-api-tenant');
        $issued = $this->app->make(CreateApiCredential::class)->handle($first, $firstPlayer, 'First credential', ['alliance:read']);

        $response = $this->withHeader('Authorization', 'Bearer '.$issued->token)->getJson('/api/v1/alliance');

        $response->assertOk()->assertJsonPath('data.id', (string) $first->id);
        self::assertNotSame((string) $second->id, (string) $response->json('data.id'));
    }

    public function test_cross_alliance_integration_identifier_fails_closed(): void
    {
        $firstOwner = User::factory()->create();
        $secondOwner = User::factory()->create();
        $firstKingdom = Kingdom::query()->create(['number' => 4111]);
        $secondKingdom = Kingdom::query()->create(['number' => 4112]);
        $firstPlayer = Player::query()->create([
            'user_id' => $firstOwner->id,
            'current_kingdom_id' => $firstKingdom->id,
            'game_player_id' => 'integration-management-owner-1',
            'current_name' => 'Integration Manager One',
        ]);
        $secondPlayer = Player::query()->create([
            'user_id' => $secondOwner->id,
            'current_kingdom_id' => $secondKingdom->id,
            'game_player_id' => 'integration-management-owner-2',
            'current_name' => 'Integration Manager Two',
        ]);

        $createAlliance = $this->app->make(CreateAlliance::class);
        $first = $createAlliance->handle($firstPlayer, 'First Management Tenant', 'first-management-tenant');
        $second = $createAlliance->handle($secondPlayer, 'Second Management Tenant', 'second-management-tenant');
        $foreign = $this->app->make(CreateApiCredential::class)->handle($second, $secondPlayer, 'Foreign credential', ['alliance:read']);
        $sessionKey = (string) config('game_world.active_player_session_key');

        $this->actingAs($firstOwner)
            ->withSession([
                $sessionKey => $firstPlayer->id,
                'auth.password_confirmed_at' => time(),
            ])
            ->delete('/alliance/integrations/api-credentials/'.$foreign->credential->id)
            ->assertNotFound();
    }
}
