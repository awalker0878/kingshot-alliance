<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\Actions;

use App\Contexts\GameWorld\GiftCodes\Enums\GiftCodeSourceDeliveryStatus;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeSourceDelivery;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeSourceRegistry;
use App\Contexts\GameWorld\GiftCodes\ValueObjects\GiftCodePushDelivery;
use App\Contexts\GameWorld\GiftCodes\ValueObjects\GiftCodePushDeliveryReceipt;

final class RecordGiftCodePushDelivery
{
    public function handle(GiftCodePushDelivery $delivery): GiftCodePushDeliveryReceipt
    {
        $source = GiftCodeSourceRegistry::query()
            ->where('source_key', $delivery->sourceKey)
            ->where('is_active', true)
            ->whereNull('revoked_at')
            ->firstOrFail();

        $record = GiftCodeSourceDelivery::query()->firstOrCreate(
            [
                'gift_code_source_id' => (string) $source->id,
                'replay_key' => $delivery->replayKey,
            ],
            [
                'provider' => $delivery->provider,
                'provider_event_id' => $delivery->providerEventId,
                'provider_item_id' => $delivery->providerItemId,
                'payload_sha256' => $delivery->payloadSha256,
                'received_at' => now(),
                'authenticated_at' => now(),
                'signature_valid' => true,
                'processing_status' => GiftCodeSourceDeliveryStatus::Authenticated->value,
                'correlation_id' => $delivery->correlationId,
            ],
        );

        return new GiftCodePushDeliveryReceipt(
            deliveryId: (string) $record->id,
            created: $record->wasRecentlyCreated,
        );
    }
}
