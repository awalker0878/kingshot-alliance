<?php

declare(strict_types=1);

namespace Tests\Contexts\Platform\DataGovernance\Feature;

use App\Contexts\Platform\DataGovernance\Actions\EnforcePlatformRetention;
use App\Contexts\Platform\Integrations\Actions\CreateApiCredential;
use App\Contexts\Platform\Integrations\Actions\CreateWebhookSubscription;
use App\Contexts\Platform\Integrations\Actions\QueueWebhookTestDelivery;
use App\Contexts\Platform\Integrations\Actions\RetryWebhookDelivery;
use App\Contexts\Platform\Integrations\Models\WebhookDelivery;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Tests\Support\ScenarioFactory;
use Tests\TestCase;

final class PlatformRetentionBoundsTest extends TestCase
{
    use DatabaseTruncation;

    public function test_each_category_advances_through_its_backlog_without_touching_live_records(): void
    {
        Queue::fake();
        $factory = app(ScenarioFactory::class);
        $account = $factory->account();
        $player = $factory->player($account->userId);
        $alliance = $factory->alliance($player);
        $issued = app(CreateWebhookSubscription::class)->handle($alliance->allianceId, $player->playerId,
            'Retention', 'https://hooks.example.test/events', ['event.created']);
        $deliveryId = app(QueueWebhookTestDelivery::class)->handle($alliance->allianceId, $player->playerId, $issued->subscriptionId);
        $delivery = WebhookDelivery::query()->findOrFail($deliveryId);
        $credential = app(CreateApiCredential::class)->handle($alliance->allianceId, $player->playerId, 'Retention', ['alliance:read']);
        $rawCredential = (array) DB::table('api_credentials')->where('id', $credential->credentialId)->first();
        for ($i = 0; $i < 7; $i++) {
            $copy = $delivery->replicate();
            $copy->forceFill(['idempotency_key' => 'retention-'.$i, 'status' => 'failed', 'updated_at' => now()->subDays(31)])->save();
            DB::table('api_credentials')->insert([...$rawCredential, 'id' => (string) Str::ulid(), 'prefix' => 'retention-'.$i, 'revoked_at' => now()->subDays(91)]);
            DB::table('alliance_usage_snapshots')->insert([
                'id' => (string) Str::ulid(), 'alliance_id' => $alliance->allianceId,
                'active_members' => 1, 'storage_bytes' => 0, 'active_api_credentials' => 1,
                'active_webhook_subscriptions' => 1, 'pending_outbox_messages' => 0, 'captured_at' => now()->subDays(366),
            ]);
            DB::table('alliance_data_exports')->insert([
                'id' => (string) Str::ulid(), 'alliance_id' => $alliance->allianceId, 'requested_by_user_id' => $account->userId,
                'schema_version' => 'v3.1', 'format' => 'json', 'row_count' => 1, 'sha256' => str_repeat('0', 64), 'generated_at' => now()->subDays(366),
            ]);
        }
        foreach ([2, 2, 2, 1, 0] as $expected) {
            self::assertSame([
                'webhookPayloadsRedacted' => $expected, 'credentialsPurged' => $expected,
                'usageSnapshotsPurged' => $expected, 'exportMetadataPurged' => $expected,
            ], app(EnforcePlatformRetention::class)->handle(2));
        }
        self::assertNotNull($delivery->fresh()->payload);
        self::assertTrue(DB::table('api_credentials')->where('id', $credential->credentialId)->exists());
        self::assertSame(7, WebhookDelivery::query()->whereNull('payload')->count());
    }

    public function test_locked_terminal_delivery_is_skipped_and_a_committed_retry_keeps_its_payload(): void
    {
        Queue::fake();
        $factory = app(ScenarioFactory::class);
        $player = $factory->player($factory->account()->userId);
        $alliance = $factory->alliance($player);
        $issued = app(CreateWebhookSubscription::class)->handle($alliance->allianceId, $player->playerId,
            'Retention race', 'https://hooks.example.test/events', ['event.created']);
        $id = app(QueueWebhookTestDelivery::class)->handle($alliance->allianceId, $player->playerId, $issued->subscriptionId);
        WebhookDelivery::query()->whereKey($id)->update(['status' => 'failed', 'updated_at' => now()->subDays(31)]);
        config()->set('database.connections.retention_competitor', DB::connection()->getConfig());
        $other = DB::connection('retention_competitor');
        try {
            $other->beginTransaction();
            $other->table('webhook_deliveries')->where('id', $id)->lockForUpdate()->first();
            self::assertSame(0, app(EnforcePlatformRetention::class)->handle(1)['webhookPayloadsRedacted']);
            $other->rollBack();
            app(RetryWebhookDelivery::class)->handle($alliance->allianceId, $player->playerId, $id);
            self::assertSame(0, app(EnforcePlatformRetention::class)->handle(1)['webhookPayloadsRedacted']);
            self::assertNotNull(WebhookDelivery::query()->findOrFail($id)->payload);
            WebhookDelivery::query()->whereKey($id)->update(['status' => 'failed', 'updated_at' => now()->subDays(31)]);
            self::assertSame(1, app(EnforcePlatformRetention::class)->handle(1)['webhookPayloadsRedacted']);
            $this->expectException(ValidationException::class);
            app(RetryWebhookDelivery::class)->handle($alliance->allianceId, $player->playerId, $id);
        } finally {
            if ($other->transactionLevel() > 0) {
                $other->rollBack();
            }
            DB::purge('retention_competitor');
        }
    }
}
