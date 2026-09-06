<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\Actions;

use App\Contexts\GameWorld\GiftCodes\Adapters\DiscordChannelGiftCodeSourceAdapter;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeSourceRegistry;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeSourceSubscription;
use App\Contexts\GameWorld\GiftCodes\Services\DiscordMessageGiftCodeFetcher;
use App\Contexts\GameWorld\GiftCodes\Services\GiftCodePushDeliveryIdentity;
use App\Contexts\GameWorld\GiftCodes\ValueObjects\GiftCodePushDelivery;

final readonly class ProcessDiscordGiftCodeGatewayEvent
{
    public function __construct(
        private GiftCodePushDeliveryIdentity $identity,
        private RecordGiftCodePushDelivery $record,
        private DiscordMessageGiftCodeFetcher $fetcher,
        private IngestGiftCodeProviderPublication $ingest,
    ) {}

    /** @param array<string,mixed> $payload
     * @return array{matched:int,processed:int,duplicates:int,accepted:int}
     */
    public function handle(array $payload): array
    {
        $eventType = is_string($payload['t'] ?? null) ? trim($payload['t']) : '';
        if (! in_array($eventType, ['MESSAGE_CREATE', 'MESSAGE_UPDATE'], true)) {
            return ['matched' => 0, 'processed' => 0, 'duplicates' => 0, 'accepted' => 0];
        }
        $data = $payload['d'] ?? null;
        if (! is_array($data)) {
            return ['matched' => 0, 'processed' => 0, 'duplicates' => 0, 'accepted' => 0];
        }

        $guildId = $this->snowflake($data['guild_id'] ?? null);
        $channelId = $this->snowflake($data['channel_id'] ?? null);
        $messageId = $this->snowflake($data['id'] ?? null);
        if ($guildId === null || $channelId === null || $messageId === null) {
            return ['matched' => 0, 'processed' => 0, 'duplicates' => 0, 'accepted' => 0];
        }

        $sources = GiftCodeSourceRegistry::query()
            ->where('adapter_key', DiscordChannelGiftCodeSourceAdapter::KEY)
            ->where('is_active', true)
            ->where('ingestion_enabled', true)
            ->where('push_enabled', true)
            ->whereNull('revoked_at')
            ->get()
            ->filter(function (GiftCodeSourceRegistry $source) use ($guildId, $channelId): bool {
                $policy = $source->provenance_policy ?? [];

                return ($policy['discord_guild_id'] ?? null) === $guildId
                    && ($policy['discord_channel_id'] ?? null) === $channelId;
            })
            ->values();

        $processed = 0;
        $duplicates = 0;
        $accepted = 0;
        foreach ($sources as $source) {
            $sequence = is_int($payload['s'] ?? null) ? (string) $payload['s'] : '';
            $replayKey = $this->identity->replayKey(
                'discord',
                (string) $source->id,
                $eventType.':'.$sequence,
                $messageId.'|'.$eventType,
            );
            $delivery = $this->record->handle(new GiftCodePushDelivery(
                provider: 'discord',
                sourceKey: $source->source_key,
                providerEventId: $eventType.':'.$sequence,
                providerItemId: $messageId,
                replayKey: $replayKey,
                payloadSha256: hash('sha256', json_encode($payload, JSON_THROW_ON_ERROR)),
                correlationId: $sequence === '' ? null : $sequence,
            ));
            if (! $delivery->wasRecentlyCreated) {
                $duplicates++;
                $source->increment('replay_rejection_count');
                continue;
            }

            try {
                $publication = $this->fetcher->fetch($source, $messageId);
                $outcome = $this->ingest->handle($source, $publication, 'discord-gateway-v1', true);
                $delivery->forceFill([
                    'processing_status' => $outcome->status,
                    'processed_at' => now(),
                ])->save();
                $processed++;
                $accepted += $outcome->accepted;
                $source->forceFill([
                    'last_push_received_at' => now(),
                    'last_provider_event_at' => now(),
                    'last_health_checked_at' => now(),
                ])->save();
                GiftCodeSourceSubscription::query()
                    ->where('gift_code_source_id', $source->id)
                    ->where('provider', 'discord')
                    ->where('transport', 'gateway')
                    ->update(['last_event_received_at' => now(), 'last_error_code' => null]);
            } catch (\Throwable $exception) {
                $delivery->forceFill([
                    'processing_status' => 'failed',
                    'error_code' => 'canonical_fetch_or_ingestion_failed',
                    'processed_at' => now(),
                ])->save();
                throw $exception;
            }
        }

        return [
            'matched' => $sources->count(),
            'processed' => $processed,
            'duplicates' => $duplicates,
            'accepted' => $accepted,
        ];
    }

    private function snowflake(mixed $value): ?string
    {
        if (! is_string($value)) {
            return null;
        }
        $value = trim($value);

        return preg_match('/^[0-9]{1,32}$/D', $value) === 1 ? $value : null;
    }
}
