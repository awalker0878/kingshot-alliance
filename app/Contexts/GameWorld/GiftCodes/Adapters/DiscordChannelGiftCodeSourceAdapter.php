<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\Adapters;

use App\Contexts\GameWorld\GiftCodes\Adapters\Concerns\ParsesExplicitGiftCodeLabels;
use App\Contexts\GameWorld\GiftCodes\Contracts\GiftCodeSourceAdapter;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeSourceRegistry;
use App\Contexts\GameWorld\GiftCodes\ValueObjects\GiftCodeIngestionObservation;
use App\Contexts\GameWorld\GiftCodes\ValueObjects\GiftCodeIngestionPage;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use UnexpectedValueException;

final class DiscordChannelGiftCodeSourceAdapter implements GiftCodeSourceAdapter
{
    use ParsesExplicitGiftCodeLabels;

    public const KEY = 'discord-channel-v1';

    public function key(): string
    {
        return self::KEY;
    }

    public function acquire(GiftCodeSourceRegistry $source, ?string $cursor, int $limit): GiftCodeIngestionPage
    {
        if ($cursor !== null && trim($cursor) !== '') {
            throw new UnexpectedValueException('The Discord channel adapter does not accept a source cursor.');
        }

        $policy = $source->provenance_policy ?? [];
        if (($policy['platform_permission_confirmed'] ?? false) !== true
            || ($policy['message_content_access_confirmed'] ?? false) !== true) {
            throw new UnexpectedValueException('Discord ingestion requires confirmed bot installation, channel access, and message-content access.');
        }
        if (mb_strtolower(rtrim(trim((string) $source->canonical_domain), '.')) !== 'discord.com') {
            throw new UnexpectedValueException('The Discord channel adapter requires discord.com as the canonical source domain.');
        }

        $guildId = $this->requiredPolicySnowflake($policy, 'discord_guild_id');
        $channelId = $this->requiredPolicySnowflake($policy, 'discord_channel_id');
        $authorIds = $this->requiredAuthorIds($policy);
        $token = trim((string) config('game_world.gift_codes.discord_bot_token', ''));
        if ($token === '') {
            throw new UnexpectedValueException('The Discord channel adapter requires a configured bot token.');
        }

        $headers = [
            'Authorization' => 'Bot '.$token,
            'Accept' => 'application/json',
        ];
        $timeout = max(1, min(30, (int) config('game_world.gift_codes.ingestion_timeout_seconds', 10)));
        $channel = Http::withHeaders($headers)
            ->timeout($timeout)
            ->withOptions(['allow_redirects' => false])
            ->get('https://discord.com/api/v10/channels/'.rawurlencode($channelId));
        $this->assertJsonSuccess($channel, 'Discord channel lookup');
        $channelPayload = $channel->json();
        if (! is_array($channelPayload)
            || $this->optionalString($channelPayload['id'] ?? null, 32) !== $channelId
            || $this->optionalString($channelPayload['guild_id'] ?? null, 32) !== $guildId) {
            throw new UnexpectedValueException('The Discord channel identity did not match the configured source policy.');
        }

        $pageSize = max(1, min(100, $limit));
        $response = Http::withHeaders($headers)
            ->timeout($timeout)
            ->withOptions(['allow_redirects' => false])
            ->get('https://discord.com/api/v10/channels/'.rawurlencode($channelId).'/messages', [
                'limit' => $pageSize,
            ]);
        $this->assertJsonSuccess($response, 'Discord channel messages');
        $messages = $response->json();
        if (! is_array($messages) || ! array_is_list($messages) || count($messages) > $pageSize) {
            throw new UnexpectedValueException('Discord returned an invalid or unbounded message collection.');
        }

        $retrievalVersion = $this->retrievalVersion($response);
        $observations = [];
        foreach ($messages as $position => $message) {
            if (! is_array($message)) {
                throw new UnexpectedValueException(sprintf('Discord message %d must be an object.', $position + 1));
            }
            $messageId = $this->requiredString($message['id'] ?? null, 'message id', $position + 1, 32);
            $author = $message['author'] ?? null;
            if (! is_array($author)) {
                throw new UnexpectedValueException(sprintf('Discord message %d requires an author object.', $position + 1));
            }
            $authorId = $this->requiredString($author['id'] ?? null, 'author id', $position + 1, 32);
            if (! in_array($authorId, $authorIds, true)) {
                continue;
            }
            $content = $this->optionalString($message['content'] ?? null, 20_000) ?? '';
            foreach ($this->explicitGiftCodes($content) as $code) {
                $sourceUrl = sprintf(
                    'https://discord.com/channels/%s/%s/%s',
                    $guildId,
                    $channelId,
                    $messageId,
                );
                $fingerprint = hash('sha256', json_encode($message, JSON_THROW_ON_ERROR));
                $observations[] = new GiftCodeIngestionObservation(
                    code: $code,
                    assertion: 'available',
                    assertionPayload: null,
                    sourceUrl: $sourceUrl,
                    claimedExpiresAt: null,
                    expiryPrecision: null,
                    expiryTimezone: null,
                    publishedAt: $this->optionalString($message['timestamp'] ?? null, 120),
                    sourceVersion: 'discord-message:'.$messageId,
                    retrievalVersion: $retrievalVersion,
                    parserVersion: self::KEY,
                    contentFingerprint: $fingerprint,
                    rawEvidenceRef: $sourceUrl.'#gift-code='.rawurlencode($code),
                    verificationPassed: true,
                );
            }
        }

        return new GiftCodeIngestionPage($observations, null);
    }

    /** @param array<string,mixed> $policy */
    private function requiredPolicySnowflake(array $policy, string $key): string
    {
        $value = $this->optionalString($policy[$key] ?? null, 32);
        if ($value === null || preg_match('/^[0-9]{1,32}$/D', $value) !== 1) {
            throw new UnexpectedValueException(sprintf('The Discord channel adapter requires source policy %s.', $key));
        }

        return $value;
    }

    /** @param array<string,mixed> $policy
     *  @return list<string>
     */
    private function requiredAuthorIds(array $policy): array
    {
        $values = $policy['discord_author_ids'] ?? null;
        if (! is_array($values) || $values === [] || count($values) > 50) {
            throw new UnexpectedValueException('The Discord channel adapter requires one to fifty approved author ids.');
        }
        $ids = [];
        foreach ($values as $value) {
            if (! is_string($value) || preg_match('/^[0-9]{1,32}$/D', trim($value)) !== 1) {
                throw new UnexpectedValueException('Discord approved author ids must be numeric snowflakes.');
            }
            $ids[] = trim($value);
        }

        return array_values(array_unique($ids));
    }

    private function assertJsonSuccess(Response $response, string $operation): void
    {
        if (! $response->successful()) {
            throw new \RuntimeException(sprintf('%s returned HTTP %d.', $operation, $response->status()));
        }
        if (! str_contains(mb_strtolower((string) $response->header('Content-Type')), 'json')) {
            throw new UnexpectedValueException($operation.' did not return JSON content.');
        }
    }

    private function requiredString(mixed $value, string $field, int $position, int $maximum): string
    {
        $value = $this->optionalString($value, $maximum);
        if ($value === null) {
            throw new UnexpectedValueException(sprintf('Discord message %d requires a non-empty %s.', $position, $field));
        }

        return $value;
    }

    private function optionalString(mixed $value, int $maximum): ?string
    {
        if ($value === null) {
            return null;
        }
        if (! is_string($value)) {
            throw new UnexpectedValueException('Discord API scalar fields must be strings.');
        }
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        if (mb_strlen($value) > $maximum) {
            throw new UnexpectedValueException('A Discord API scalar field exceeded its maximum length.');
        }

        return $value;
    }

    private function retrievalVersion(Response $response): string
    {
        foreach (['ETag', 'Last-Modified'] as $header) {
            $value = trim((string) $response->header($header));
            if ($value !== '') {
                return mb_substr($header.':'.$value, 0, 120);
            }
        }

        return 'sha256:'.hash('sha256', $response->body());
    }
}
