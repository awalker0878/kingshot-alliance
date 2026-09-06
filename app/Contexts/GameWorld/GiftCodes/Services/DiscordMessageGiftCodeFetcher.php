<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\Services;

use App\Contexts\GameWorld\GiftCodes\Adapters\Concerns\HandlesGiftCodeProviderResponses;
use App\Contexts\GameWorld\GiftCodes\Adapters\DiscordChannelGiftCodeSourceAdapter;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeSourceRegistry;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use UnexpectedValueException;

final class DiscordMessageGiftCodeFetcher
{
    use HandlesGiftCodeProviderResponses;

    public function fetch(GiftCodeSourceRegistry $source, string $messageId): GiftCodeProviderPublication
    {
        if ($source->adapter_key !== DiscordChannelGiftCodeSourceAdapter::KEY) {
            throw new UnexpectedValueException('Discord canonical fetch requires the Discord channel source adapter.');
        }

        $policy = $source->provenance_policy ?? [];
        $guildId = $this->snowflake($policy['discord_guild_id'] ?? null, 'guild id');
        $channelId = $this->snowflake($policy['discord_channel_id'] ?? null, 'channel id');
        $messageId = $this->snowflake($messageId, 'message id');
        $authorIds = $policy['discord_author_ids'] ?? null;
        if (! is_array($authorIds) || $authorIds === []) {
            throw new UnexpectedValueException('Discord canonical fetch requires approved author ids.');
        }
        $authorIds = array_values(array_filter(array_map(
            static fn (mixed $value): ?string => is_string($value) && preg_match('/^[0-9]{1,32}$/D', trim($value)) === 1 ? trim($value) : null,
            $authorIds,
        )));
        if ($authorIds === []) {
            throw new UnexpectedValueException('Discord canonical fetch requires valid approved author ids.');
        }

        $token = trim((string) config('game_world.gift_codes.discord_bot_token', ''));
        if ($token === '') {
            throw new UnexpectedValueException('Discord canonical fetch requires a configured bot token.');
        }
        $headers = ['Authorization' => 'Bot '.$token, 'Accept' => 'application/json'];
        $timeout = max(1, min(30, (int) config('game_world.gift_codes.ingestion_timeout_seconds', 10)));

        $channel = Http::withHeaders($headers)
            ->timeout($timeout)
            ->withOptions(['allow_redirects' => false])
            ->get('https://discord.com/api/v10/channels/'.rawurlencode($channelId));
        $this->assertJsonSuccess($channel, 'Discord canonical channel lookup');
        $channelPayload = $channel->json();
        if (! is_array($channelPayload)
            || ($channelPayload['id'] ?? null) !== $channelId
            || ($channelPayload['guild_id'] ?? null) !== $guildId) {
            throw new UnexpectedValueException('Discord canonical channel identity did not match the configured source.');
        }

        $response = Http::withHeaders($headers)
            ->timeout($timeout)
            ->withOptions(['allow_redirects' => false])
            ->get(sprintf(
                'https://discord.com/api/v10/channels/%s/messages/%s',
                rawurlencode($channelId),
                rawurlencode($messageId),
            ));
        $this->assertJsonSuccess($response, 'Discord canonical message lookup');
        $payload = $response->json();
        if (! is_array($payload) || ($payload['id'] ?? null) !== $messageId) {
            throw new UnexpectedValueException('Discord canonical message identity did not match the Gateway delivery.');
        }
        $author = $payload['author'] ?? null;
        $authorId = is_array($author) && is_string($author['id'] ?? null) ? trim($author['id']) : '';
        if (! in_array($authorId, $authorIds, true)) {
            throw new UnexpectedValueException('Discord canonical message was not authored by an approved source identity.');
        }
        $content = is_string($payload['content'] ?? null) ? $payload['content'] : '';
        if (mb_strlen($content) > 20_000) {
            throw new UnexpectedValueException('Discord canonical message content exceeded its maximum length.');
        }

        $sourceUrl = sprintf('https://discord.com/channels/%s/%s/%s', $guildId, $channelId, $messageId);

        return new GiftCodeProviderPublication(
            provider: 'discord',
            providerItemId: $messageId,
            sourceUrl: $sourceUrl,
            content: $content,
            publishedAt: is_string($payload['timestamp'] ?? null) ? $payload['timestamp'] : null,
            updatedAt: is_string($payload['edited_timestamp'] ?? null) ? $payload['edited_timestamp'] : null,
            retrievalVersion: $this->giftCodeRetrievalVersion($response),
        );
    }

    private function assertJsonSuccess(Response $response, string $operation): void
    {
        $this->assertGiftCodeProviderSuccess($response, $operation);
        if (! str_contains(mb_strtolower((string) $response->header('Content-Type')), 'json')) {
            throw new UnexpectedValueException($operation.' did not return JSON content.');
        }
    }

    private function snowflake(mixed $value, string $field): string
    {
        if (! is_string($value) || preg_match('/^[0-9]{1,32}$/D', trim($value)) !== 1) {
            throw new UnexpectedValueException('Discord canonical fetch requires a valid '.$field.'.');
        }

        return trim($value);
    }
}
