<?php

declare(strict_types=1);

namespace Tests\v3\Contexts\GameWorld\GiftCodes;

use App\Contexts\GameWorld\GiftCodes\Actions\CompleteGiftCodePushDelivery;
use App\Contexts\GameWorld\GiftCodes\Actions\RecordGiftCodePushDelivery;
use App\Contexts\GameWorld\GiftCodes\Actions\RecordGiftCodeSourcePushActivity;
use App\Contexts\GameWorld\GiftCodes\Enums\GiftCodeSourceDeliveryStatus;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeSourceDelivery;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeSourceRegistry;
use App\Contexts\GameWorld\GiftCodes\ValueObjects\GiftCodePushDelivery;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\v3\TestCase;

final class GiftCodePushTransportConformanceV3Test extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_push_delivery_is_durable_before_processing_and_replay_is_idempotent(): void
    {
        $source = $this->source('push-durable');
        $delivery = new GiftCodePushDelivery(
            provider: 'conformance',
            sourceKey: $source->source_key,
            providerEventId: 'event-1',
            providerItemId: 'item-1',
            replayKey: hash('sha256', 'push-durable:event-1'),
            payloadSha256: hash('sha256', '{"event":1}'),
            correlationId: 'correlation-1',
        );

        $first = app(RecordGiftCodePushDelivery::class)->handle($delivery);
        $stored = GiftCodeSourceDelivery::query()->findOrFail($first->deliveryId);

        self::assertTrue($first->created);
        self::assertSame(GiftCodeSourceDeliveryStatus::Authenticated->value, $stored->processing_status);
        self::assertTrue($stored->signature_valid);
        self::assertNotNull($stored->authenticated_at);
        self::assertNull($stored->processed_at);

        $replay = app(RecordGiftCodePushDelivery::class)->handle($delivery);

        self::assertFalse($replay->created);
        self::assertSame($first->deliveryId, $replay->deliveryId);
        self::assertSame(1, GiftCodeSourceDelivery::query()->count());
    }

    public function test_push_processing_completion_is_explicit_and_duplicate_reservation_never_resets_failure_state(): void
    {
        $source = $this->source('push-completion');
        $delivery = new GiftCodePushDelivery(
            provider: 'conformance',
            sourceKey: $source->source_key,
            providerEventId: 'event-2',
            providerItemId: null,
            replayKey: hash('sha256', 'push-completion:event-2'),
            payloadSha256: hash('sha256', '{"event":2}'),
        );
        $receipt = app(RecordGiftCodePushDelivery::class)->handle($delivery);

        app(CompleteGiftCodePushDelivery::class)->handle($receipt->deliveryId, 'failed', 'provider_item_fetch_failed');
        $failed = GiftCodeSourceDelivery::query()->findOrFail($receipt->deliveryId);
        self::assertSame(GiftCodeSourceDeliveryStatus::Failed->value, $failed->processing_status);
        self::assertSame('provider_item_fetch_failed', $failed->error_code);
        self::assertNotNull($failed->processed_at);

        $replay = app(RecordGiftCodePushDelivery::class)->handle($delivery);
        $failed->refresh();

        self::assertFalse($replay->created);
        self::assertSame(GiftCodeSourceDeliveryStatus::Failed->value, $failed->processing_status);
        self::assertSame('provider_item_fetch_failed', $failed->error_code);
    }

    public function test_push_security_activity_projects_signature_replay_and_receive_health_independently(): void
    {
        $source = $this->source('push-health');
        $activity = app(RecordGiftCodeSourcePushActivity::class);

        $activity->handle((string) $source->id, 'signature_failure');
        $activity->handle((string) $source->id, 'replay_rejection');
        $activity->handle((string) $source->id, 'received');
        $source->refresh();

        self::assertSame(1, $source->signature_failure_count);
        self::assertSame(1, $source->replay_rejection_count);
        self::assertNotNull($source->last_push_received_at);
        self::assertNotNull($source->last_provider_event_at);
        self::assertNotNull($source->last_health_checked_at);
    }

    public function test_revoked_source_cannot_reserve_a_push_delivery(): void
    {
        $source = $this->source('push-revoked');
        $source->forceFill(['revoked_at' => now()])->save();

        $this->expectException(ModelNotFoundException::class);
        app(RecordGiftCodePushDelivery::class)->handle(new GiftCodePushDelivery(
            provider: 'conformance',
            sourceKey: $source->source_key,
            providerEventId: 'event-revoked',
            providerItemId: null,
            replayKey: hash('sha256', 'push-revoked:event'),
            payloadSha256: hash('sha256', '{}'),
        ));
    }

    private function source(string $key): GiftCodeSourceRegistry
    {
        return GiftCodeSourceRegistry::query()->create([
            'source_key' => $key,
            'name' => $key,
            'classification' => 'official',
            'canonical_domain' => 'provider.example.test',
            'verification_method' => 'push_conformance',
            'provenance_policy' => [],
            'is_active' => true,
            'ingestion_enabled' => true,
            'push_enabled' => true,
            'head_poll_enabled' => true,
            'reconciliation_enabled' => true,
            'backfill_enabled' => true,
            'authority_promotion_enabled' => true,
            'activation_status' => 'enabled',
            'health_status' => 'pending',
            'policy_revision' => 1,
        ]);
    }
}
