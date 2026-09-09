<?php

declare(strict_types=1);

namespace Tests\v3\Infrastructure;

use App\Contexts\Accounts\Identity\Models\User;
use App\Contexts\Alliance\Membership\Queries\PlayerIdentityContextQuery;
use App\Contexts\GameWorld\Governance\Queries\KingdomAuthorityFactsQuery;
use App\Contexts\GameWorld\Players\Http\Middleware\RequireCurrentPlayerContextVersion;
use App\Contexts\GameWorld\Players\Services\PlayerAuthorityContextVersion;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Str;
use Tests\v3\Support\ScenarioFactory;
use Tests\v3\TestCase;

final class RouteThrottleBoundaryV3Test extends TestCase
{
    use RefreshDatabase;

    public function test_booted_numeric_budgets_have_explicit_scopes_and_consistent_limits(): void
    {
        $budgets = [];
        foreach (Route::getRoutes() as $route) {
            foreach ($route->gatherMiddleware() as $middleware) {
                if (! is_string($middleware) || preg_match('/^throttle:([0-9]+)(?:,|$)/', $middleware) !== 1) {
                    continue;
                }
                $parts = explode(',', substr($middleware, strlen('throttle:')));
                self::assertCount(3, $parts, $route->getName().' needs an explicit budget.');
                self::assertNotSame('', $parts[2]);
                $definition = [$parts[0], $parts[1]];
                self::assertSame($budgets[$parts[2]] ?? $definition, $definition, 'One budget must have one limit: '.$parts[2]);
                $budgets[$parts[2]] = $definition;
            }
        }
        self::assertNotEmpty($budgets);
    }

    public function test_reset_requests_do_not_consume_reset_completion_or_another_clients_budget(): void
    {
        for ($attempt = 0; $attempt < 6; $attempt++) {
            $this->postJson('/forgot-password')->assertUnprocessable()->assertJsonValidationErrors('email');
        }
        $this->postJson('/forgot-password')->assertStatus(429)->assertHeader('Retry-After');
        $this->postJson('/reset-password')->assertUnprocessable()->assertJsonValidationErrors('token');
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.49'])
            ->postJson('/forgot-password')->assertUnprocessable()->assertJsonValidationErrors('email');
    }

    public function test_package_upload_admission_keeps_signature_checks_and_a_separate_budget(): void
    {
        for ($attempt = 0; $attempt < 60; $attempt++) {
            $this->postJson(route('livewire.upload-file'))->assertUnauthorized();
        }
        $this->postJson(route('livewire.upload-file'))->assertStatus(429)->assertHeader('Retry-After');
        $this->postJson('/forgot-password')->assertUnprocessable();
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.52'])
            ->postJson(route('livewire.upload-file'))->assertUnauthorized();
    }

    public function test_provider_ingress_retains_one_shared_budget_across_providers_and_source_ids(): void
    {
        config(['game_world.gift_codes.approved_source_ingestion' => false]);
        for ($attempt = 0; $attempt < 120; $attempt++) {
            $provider = $attempt % 2 === 0 ? 'youtube-websub' : 'facebook-webhook';
            $this->getJson('/api/gift-code-sources/'.Str::ulid().'/'.$provider)->assertNotFound();
        }
        $this->getJson('/api/gift-code-sources/'.Str::ulid().'/x-webhook')->assertStatus(429);
        $this->postJson('/api/internal/gift-code-sources/'.Str::ulid().'/observations')->assertStatus(429);
        $this->postJson('/forgot-password')->assertUnprocessable();
        $this->getJson('/api/v1/alliance')->assertUnauthorized();
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.50'])
            ->getJson('/api/gift-code-sources/'.Str::ulid().'/youtube-websub')->assertNotFound();
    }

    public function test_v1_api_retains_its_aggregate_client_budget_across_endpoints(): void
    {
        for ($attempt = 0; $attempt < 120; $attempt++) {
            $this->getJson($attempt % 2 === 0 ? '/api/v1/alliance' : '/api/v1/events')->assertUnauthorized();
        }
        $this->getJson('/api/v1/contributions')->assertStatus(429);
        $this->postJson('/api/v1/actor-links/claims')->assertStatus(429);
        $this->postJson('/forgot-password')->assertUnprocessable();
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.51'])
            ->getJson('/api/v1/alliance')->assertUnauthorized();
    }

    public function test_internal_observations_keep_their_lower_limit_within_the_ingress_budget(): void
    {
        config(['game_world.gift_codes.approved_source_ingestion' => false]);
        for ($attempt = 0; $attempt < 60; $attempt++) {
            $this->postJson('/api/internal/gift-code-sources/'.Str::ulid().'/observations')->assertNotFound();
        }
        $this->postJson('/api/internal/gift-code-sources/'.Str::ulid().'/observations')->assertStatus(429);
        // The rejected inner operation also consumed one outer ingress attempt.
        for ($attempt = 0; $attempt < 59; $attempt++) {
            $this->getJson('/api/gift-code-sources/'.Str::ulid().'/youtube-websub')->assertNotFound();
        }
        $this->getJson('/api/gift-code-sources/'.Str::ulid().'/youtube-websub')->assertStatus(429);
    }

    public function test_gift_redemption_budget_spans_workflows_without_exhausting_email_verification(): void
    {
        $scenarios = app(ScenarioFactory::class);
        $user = $scenarios->authUser();
        $user->forceFill(['email_verified_at' => now()])->save();
        $player = $scenarios->player((int) $user->id, 59249);
        $alliance = app(PlayerIdentityContextQuery::class)->forPlayers([$player->playerId])[$player->playerId] ?? null;
        $kingdom = app(KingdomAuthorityFactsQuery::class)->findCurrent($player->playerId, $player->kingdomId)?->permissionKeysObservedAtRead ?? [];
        $version = app(PlayerAuthorityContextVersion::class)->issue($player, $alliance, $kingdom);
        $this->actingAs($user)
            ->withSession([(string) config('game_world.active_player_session_key') => $player->playerId])
            ->withHeader(RequireCurrentPlayerContextVersion::HEADER_NAME, $version);

        for ($attempt = 0; $attempt < 30; $attempt++) {
            $this->postJson('/gift-codes/'.Str::ulid().'/redeem')
                ->assertUnprocessable()->assertJsonValidationErrors('player_ids');
        }
        $this->postJson('/gift-codes/workspace/sessions/'.Str::ulid().'/items/'.Str::ulid().'/prepare')->assertStatus(429);
        $this->get($this->verificationUrl($user))->assertRedirect();
    }

    public function test_verification_resends_do_not_block_a_valid_signed_completion(): void
    {
        $user = User::factory()->unverified()->create();
        $this->actingAs($user);
        for ($attempt = 0; $attempt < 6; $attempt++) {
            $this->post('/email/verification-notification')->assertRedirect();
        }
        $this->post('/email/verification-notification')->assertStatus(429);
        $this->get($this->verificationUrl($user))->assertRedirect();
        self::assertTrue($user->refresh()->hasVerifiedEmail());
    }

    private function verificationUrl(User $user): string
    {
        return URL::temporarySignedRoute('verification.verify', now()->addMinutes(5), [
            'id' => $user->id,
            'hash' => sha1($user->getEmailForVerification()),
        ]);
    }
}
