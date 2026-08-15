<?php

declare(strict_types=1);

namespace Tests\Feature\Integrations;

use App\Domain\Alliances\Actions\CreateAlliance;
use App\Domain\Events\Actions\CreateEvent;
use App\Domain\Events\Enums\EventScope;
use App\Domain\Events\Models\EventType;
use App\Domain\Events\Services\EventTypeRegistry;
use App\Contexts\Accounts\Models\User;
use App\Domain\Integrations\Actions\CreateApiCredential;
use App\Domain\Integrations\Actions\CreateWebhookSubscription;
use App\Domain\Integrations\Actions\DeliverWebhook;
use App\Domain\Integrations\Actions\QueueWebhookDeliveries;
use App\Domain\Integrations\Actions\RevokeApiCredential;
use App\Domain\Integrations\Models\WebhookDelivery;
use App\Contexts\GameWorld\Models\Kingdom;
use App\Contexts\GameWorld\Models\Player;
use App\Shared\Messaging\Events\OutboxPublished;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Validation\ValidationException;
use Tests\TestCase;

final class IntegrationManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_scoped_api_credential_is_one_way_stored_and_tenant_bound(): void
    {
        $owner = User::factory()->create();
        $kingdom = Kingdom::query()->create(['number' => 2200]);
        $ownerPlayer = Player::query()->create([
            'user_id' => $owner->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => 'api-tenant-r5',
            'current_name' => 'API Tenant R5',
        ]);
        $alliance = $this->app->make(CreateAlliance::class)->handle($ownerPlayer, 'API Tenant', 'api-tenant');
        $issued = $this->app->make(CreateApiCredential::class)->handle(
            $alliance,
            $ownerPlayer,
            'Reporting client',
            ['alliance:read'],
        );

        self::assertStringStartsWith('ks_live_', $issued->token);
        self::assertNotSame($issued->token, $issued->credential->secret_hash);
        self::assertSame(64, strlen((string) $issued->credential->secret_hash));

        $this->withHeader('Authorization', 'Bearer '.$issued->token)
            ->getJson('/api/v1/alliance')
            ->assertOk()
            ->assertJsonPath('data.id', (string) $alliance->id)
            ->assertJsonPath('data.kingdom', '2200');

        $this->withHeader('Authorization', 'Bearer '.$issued->token)
            ->getJson('/api/v1/events')
            ->assertUnauthorized();

        $this->app->make(RevokeApiCredential::class)->handle($alliance, $ownerPlayer, $issued->credential);

        $this->withHeader('Authorization', 'Bearer '.$issued->token)
            ->getJson('/api/v1/alliance')
            ->assertUnauthorized();
    }

    public function test_events_api_returns_only_alliance_scoped_events_for_the_credential_alliance(): void
    {
        $owner = User::factory()->create();
        $otherOwner = User::factory()->create();
        $kingdom = Kingdom::query()->create(['number' => 2202]);
        $otherKingdom = Kingdom::query()->create(['number' => 2203]);
        $ownerPlayer = Player::query()->create([
            'user_id' => $owner->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => 'api-events-r5',
            'current_name' => 'API Events R5',
        ]);
        $otherPlayer = Player::query()->create([
            'user_id' => $otherOwner->id,
            'current_kingdom_id' => $otherKingdom->id,
            'game_player_id' => 'other-api-events-r5',
            'current_name' => 'Other API Events R5',
        ]);
        $alliance = $this->app->make(CreateAlliance::class)->handle($ownerPlayer, 'API Events', 'api-events');
        $otherAlliance = $this->app->make(CreateAlliance::class)->handle($otherPlayer, 'Other API Events', 'other-api-events');
        $issued = $this->app->make(CreateApiCredential::class)->handle(
            $alliance,
            $ownerPlayer,
            'Events client',
            ['events:read'],
        );

        $type = EventType::query()->where('slug', 'custom')->sole();
        $configuration = $this->app->make(EventTypeRegistry::class)->scope($type, EventScope::Alliance);
        $create = $this->app->make(CreateEvent::class);

        $create->handle(
            actor: $ownerPlayer,
            configuration: $configuration,
            target: $alliance,
            firstLocalStart: CarbonImmutable::now('UTC')->addHours(2),
            title: 'Credential Alliance Event',
        );
        $create->handle(
            actor: $otherPlayer,
            configuration: $configuration,
            target: $otherAlliance,
            firstLocalStart: CarbonImmutable::now('UTC')->addHours(3),
            title: 'Other Alliance Event',
        );

        $this->withHeader('Authorization', 'Bearer '.$issued->token)
            ->getJson('/api/v1/events')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Credential Alliance Event');
    }

    public function test_webhook_policy_rejects_private_destination(): void
    {
        $owner = User::factory()->create();
        $kingdom = Kingdom::query()->create(['number' => 2210]);
        $ownerPlayer = Player::query()->create([
            'user_id' => $owner->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => 'webhook-policy-r5',
            'current_name' => 'Webhook Policy R5',
        ]);
        $alliance = $this->app->make(CreateAlliance::class)->handle($ownerPlayer, 'Webhook Policy', 'webhook-policy');

        $this->expectException(ValidationException::class);
        $this->app->make(CreateWebhookSubscription::class)->handle(
            $alliance,
            $ownerPlayer,
            'Private target',
            'https://127.0.0.1/hook',
            ['alliance.created'],
        );
    }

    public function test_outbox_webhook_fanout_is_idempotent_and_delivery_is_signed(): void
    {
        Queue::fake();
        Http::fake(['https://example.com/hooks' => Http::response('', 204)]);
        $owner = User::factory()->create();
        $kingdom = Kingdom::query()->create(['number' => 2211]);
        $ownerPlayer = Player::query()->create([
            'user_id' => $owner->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => 'webhook-tenant-r5',
            'current_name' => 'Webhook Tenant R5',
        ]);
        $alliance = $this->app->make(CreateAlliance::class)->handle($ownerPlayer, 'Webhook Tenant', 'webhook-tenant');
        $subscription = $this->app->make(CreateWebhookSubscription::class)->handle(
            $alliance,
            $ownerPlayer,
            'Example webhook',
            'https://example.com/hooks',
            ['member.joined'],
        );
        $event = new OutboxPublished(
            messageId: '01K00000000000000000000000',
            allianceId: (string) $alliance->id,
            eventType: 'member.joined',
            aggregateType: 'membership',
            aggregateId: '01K00000000000000000000001',
            idempotencyKey: 'member.joined:test',
            payload: [
                'membership_id' => '01K00000000000000000000001',
                'player_id' => '01K00000000000000000000002',
            ],
            occurredAt: now()->toIso8601String(),
        );
        $queue = $this->app->make(QueueWebhookDeliveries::class);

        self::assertSame(1, $queue->handle($event));
        self::assertSame(0, $queue->handle($event));
        self::assertDatabaseCount('webhook_deliveries', 1);

        $delivery = WebhookDelivery::query()->sole();
        $this->app->make(DeliverWebhook::class)->handle($delivery);

        Http::assertSent(function (Request $request) use ($subscription): bool {
            $timestamp = (string) $request->header('X-Kingshot-Timestamp')[0];
            $body = $request->body();
            $expected = 'sha256='.hash_hmac('sha256', $timestamp.'.'.$body, (string) $subscription->signing_secret);

            return $request->url() === 'https://example.com/hooks'
                && $request->header('X-Kingshot-Event')[0] === 'member.joined'
                && hash_equals($expected, (string) $request->header('X-Kingshot-Signature')[0]);
        });
        self::assertSame('delivered', $delivery->refresh()->status->value);
    }

    public function test_uncontracted_kingdoms_events_never_enter_webhook_fanout_even_for_wildcard_subscriptions(): void
    {
        Queue::fake();
        $owner = User::factory()->create();
        $kingdom = Kingdom::query()->create(['number' => 2201]);
        $ownerPlayer = Player::query()->create([
            'user_id' => $owner->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => 'internal-kingdom-events-r5',
            'current_name' => 'Internal Kingdom Events R5',
        ]);
        $alliance = $this->app->make(CreateAlliance::class)->handle(
            $ownerPlayer,
            'Internal Kingdoms Events',
            'internal-kingdoms-events',
        );
        $this->app->make(CreateWebhookSubscription::class)->handle(
            $alliance,
            $ownerPlayer,
            'Wildcard webhook',
            'https://example.com/hooks',
            ['*'],
        );
        $queue = $this->app->make(QueueWebhookDeliveries::class);

        foreach ([
            'kingdoms.roster_entry_created',
            'kingdoms.player_snapshot_recorded',
            'kingdoms.transfer_plan_opened',
            'kingdoms.transfer_participant_completed',
            'kingdoms.alliance_intelligence_tracking_started',
            'kingdoms.alliance_intelligence_observation_recorded',
            'kingdoms.alliance_intelligence_observation_corrected',
            'kingdoms.diplomacy_transitioned',
            'kingdoms.diplomacy_contact_saved',
            'kingdoms.diplomacy_contact_deactivated',
        ] as $index => $eventType) {
            $event = new OutboxPublished(
                messageId: sprintf('01K00000000000000000000%03d', $index),
                allianceId: (string) $alliance->id,
                eventType: $eventType,
                aggregateType: 'kingdoms-test',
                aggregateId: sprintf('01K00000000000000000001%03d', $index),
                idempotencyKey: $eventType.':test',
                payload: ['private_reference' => 'must-not-leave-tenant'],
                occurredAt: now()->toIso8601String(),
            );

            self::assertSame(0, $queue->handle($event));
        }

        $this->assertDatabaseCount('webhook_deliveries', 0);
        Queue::assertNothingPushed();
    }
}
