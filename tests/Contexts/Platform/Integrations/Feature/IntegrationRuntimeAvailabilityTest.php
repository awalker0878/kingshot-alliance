<?php

declare(strict_types=1);

namespace Tests\Contexts\Platform\Integrations\Feature;

use App\Contexts\Platform\AllianceAdministration\Actions\InitializeAlliancePlatform;
use App\Contexts\Platform\AllianceAdministration\Models\AlliancePlatformSetting;
use App\Contexts\Platform\Integrations\Actions\CreateApiCredential;
use App\Contexts\Platform\Integrations\Actions\CreateWebhookSubscription;
use App\Contexts\Platform\Integrations\Actions\DeliverWebhook;
use App\Contexts\Platform\Integrations\Actions\QueueWebhookTestDelivery;
use App\Contexts\Platform\Integrations\Enums\WebhookDeliveryStatus;
use App\Contexts\Platform\Integrations\Models\ApiCredential;
use App\Contexts\Platform\Integrations\Models\WebhookDelivery;
use App\Contexts\Platform\Integrations\Services\WebhookHostResolver;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\Support\ScenarioFactory;
use Tests\TestCase;

final class IntegrationRuntimeAvailabilityTest extends TestCase
{
    use DatabaseTruncation;

    public function test_disabled_api_blocks_existing_credentials_without_recording_successful_use(): void
    {
        $factory = app(ScenarioFactory::class);
        $player = $factory->player($factory->account()->userId);
        $alliance = $factory->alliance($player);
        app(InitializeAlliancePlatform::class)->handle($alliance->allianceId);
        $issued = app(CreateApiCredential::class)->handle($alliance->allianceId, $player->playerId, 'Runtime switch', ['alliance:read']);
        AlliancePlatformSetting::query()->whereKey($alliance->allianceId)->update(['api_access_enabled' => false]);
        $this->withToken($issued->token)->getJson('/api/v1/alliance')->assertForbidden();
        self::assertNull(ApiCredential::query()->findOrFail($issued->credentialId)->last_used_at);
        AlliancePlatformSetting::query()->whereKey($alliance->allianceId)->update(['api_access_enabled' => true]);
        $this->withToken($issued->token)->getJson('/api/v1/alliance')->assertOk();
    }

    public function test_disabling_webhooks_after_queueing_prevents_dns_and_delivery(): void
    {
        $delivery = $this->delivery();
        AlliancePlatformSetting::query()->whereKey($delivery->alliance_id)->update(['webhooks_enabled' => false]);
        $this->app->instance(WebhookHostResolver::class, new class extends WebhookHostResolver
        {
            public function resolve(string $host): array
            {
                IntegrationRuntimeAvailabilityTest::fail('A disabled integration must not resolve a destination.');
            }
        });
        Http::preventStrayRequests();
        app(DeliverWebhook::class)->handle((string) $delivery->id);
        Http::assertNothingSent();
        self::assertSame(WebhookDeliveryStatus::Failed, $delivery->fresh()->status);
        self::assertSame(0, $delivery->fresh()->attempts);
    }

    public function test_disable_during_dns_is_rechecked_at_the_provider_handoff(): void
    {
        $delivery = $this->delivery();
        $this->app->instance(WebhookHostResolver::class, new class((string) $delivery->alliance_id) extends WebhookHostResolver
        {
            public function __construct(private string $allianceId) {}

            public function resolve(string $host): array
            {
                AlliancePlatformSetting::query()->whereKey($this->allianceId)->update(['webhooks_enabled' => false]);

                return ['203.10.20.30'];
            }
        });
        Http::preventStrayRequests();
        app(DeliverWebhook::class)->handle((string) $delivery->id);
        Http::assertNothingSent();
        self::assertSame(WebhookDeliveryStatus::Failed, $delivery->fresh()->status);
        self::assertNull($delivery->fresh()->attempt_token);
    }

    public function test_archived_source_kingdom_prevents_previously_queued_delivery(): void
    {
        $delivery = $this->delivery();
        $kingdom = DB::table('alliances')->where('id', $delivery->alliance_id)->value('kingdom_id');
        DB::table('kingdoms')->where('id', $kingdom)->update(['status' => 'archived']);
        Http::preventStrayRequests();
        app(DeliverWebhook::class)->handle((string) $delivery->id);
        Http::assertNothingSent();
        self::assertSame(WebhookDeliveryStatus::Failed, $delivery->fresh()->status);
        self::assertSame(0, $delivery->fresh()->attempts);
    }

    private function delivery(): WebhookDelivery
    {
        Queue::fake();
        $factory = app(ScenarioFactory::class);
        $player = $factory->player($factory->account()->userId);
        $alliance = $factory->alliance($player);
        app(InitializeAlliancePlatform::class)->handle($alliance->allianceId);
        $issued = app(CreateWebhookSubscription::class)->handle($alliance->allianceId, $player->playerId,
            'Runtime switch', 'https://hooks.example.test/events', ['event.created']);
        $id = app(QueueWebhookTestDelivery::class)->handle($alliance->allianceId, $player->playerId, $issued->subscriptionId);

        return WebhookDelivery::query()->findOrFail($id);
    }
}
