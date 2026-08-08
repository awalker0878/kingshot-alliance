<?php

declare(strict_types=1);

namespace Tests\TenantIsolation\Integrations;

use App\Domain\Alliances\Actions\CreateAlliance;
use App\Domain\Identity\Models\User;
use App\Domain\Integrations\Actions\CreateApiCredential;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class IntegrationTenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_credential_cannot_read_another_alliance(): void
    {
        $firstOwner = User::factory()->create();
        $secondOwner = User::factory()->create();
        $createAlliance = $this->app->make(CreateAlliance::class);
        $first = $createAlliance->handle($firstOwner, 'First API Tenant', 'first-api-tenant');
        $second = $createAlliance->handle($secondOwner, 'Second API Tenant', 'second-api-tenant');
        $issued = $this->app->make(CreateApiCredential::class)->handle($first, $firstOwner, 'First credential', ['alliance:read']);

        $response = $this->withHeader('Authorization', 'Bearer '.$issued->token)->getJson('/api/v1/alliance');

        $response->assertOk()->assertJsonPath('data.id', (string) $first->id);
        self::assertNotSame((string) $second->id, (string) $response->json('data.id'));
    }

    public function test_cross_alliance_integration_identifier_fails_closed(): void
    {
        $firstOwner = User::factory()->create();
        $secondOwner = User::factory()->create();
        $createAlliance = $this->app->make(CreateAlliance::class);
        $first = $createAlliance->handle($firstOwner, 'First Management Tenant', 'first-management-tenant');
        $second = $createAlliance->handle($secondOwner, 'Second Management Tenant', 'second-management-tenant');
        $foreign = $this->app->make(CreateApiCredential::class)->handle($second, $secondOwner, 'Foreign credential', ['alliance:read']);
        $sessionKey = (string) config('identity.active_alliance_session_key');

        $this->actingAs($firstOwner)
            ->withSession([
                $sessionKey => $first->id,
                'auth.password_confirmed_at' => time(),
            ])
            ->delete('/alliance/integrations/api-credentials/'.$foreign->credential->id)
            ->assertNotFound();
    }
}
