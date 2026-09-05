<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\Adapters;

use App\Contexts\GameWorld\GiftCodes\Adapters\Concerns\HandlesGiftCodeProviderResponses;
use App\Contexts\GameWorld\GiftCodes\Adapters\Concerns\ParsesExplicitGiftCodeLabels;
use App\Contexts\GameWorld\GiftCodes\Contracts\GiftCodeSourceAdapter;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeSourceRegistry;
use App\Contexts\GameWorld\GiftCodes\ValueObjects\GiftCodeIngestionObservation;
use App\Contexts\GameWorld\GiftCodes\ValueObjects\GiftCodeIngestionPage;
use App\Contexts\GameWorld\GiftCodes\ValueObjects\GiftCodeSourceCheckpoint;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use UnexpectedValueException;

final class YouTubeChannelGiftCodeSourceAdapter implements GiftCodeSourceAdapter
{
    use HandlesGiftCodeProviderResponses;
    use ParsesExplicitGiftCodeLabels;

    public const KEY = 'youtube-channel-v1';

    public function key(): string
    {
        return self::KEY;
    }

    public function acquire(GiftCodeSourceRegistry $source, ?string $cursor, int $limit): GiftCodeIngestionPage
    {
        $policy = $source->provenance_policy ?? [];
        if (($policy['platform_api_access_confirmed'] ?? false) !== true) {
            throw new UnexpectedValueException('YouTube ingestion requires confirmed YouTube Data API access.');
        }
        if (mb_strtolower(rtrim(trim((string) $source->canonical_domain), '.')) !== 'youtube.com') {
            throw new UnexpectedValueException('The YouTube channel adapter requires youtube.com as the canonical source domain.');
        }
        if ($cursor !== null && mb_strlen(trim($cursor)) > 2000) {
            throw new UnexpectedValueException('The YouTube pagination cursor exceeded its maximum length.');
        }

        $channelId = $this->requiredPolicyString($policy, 'youtube_channel_id', 80);
        $channelTitle = $this->requiredPolicyString($policy, 'youtube_channel_title', 200);
        if (preg_match('/^UC[A-Za-z0-9_-]{20,40}$/D', $channelId) !== 1) {
            throw new UnexpectedValueException('The YouTube channel adapter requires a stable channel id.');
        }
        $apiKey = trim((string) config('game_world.gift_codes.youtube_api_key', ''));
        if ($apiKey === '') {
            throw new UnexpectedValueException('The YouTube channel adapter requires a configured Data API key.');
        }

        $timeout = max(1, min(30, (int) config('game_world.gift_codes.ingestion_timeout_seconds', 10)));
        $identity = Http::acceptJson()
            ->timeout($timeout)
            ->withOptions(['allow_redirects' => false])
            ->get('https://www.googleapis.com/youtube/v3/channels', [
                'part' => 'snippet,contentDetails',
                'id' => $channelId,
                'key' => $apiKey,
            ]);
        $this->assertJsonSuccess($identity, 'YouTube channel lookup');
        $identityPayload = $identity->json();
        $items = is_array($identityPayload) ? ($identityPayload['items'] ?? null) : null;
        if (! is_array($items) || ! array_is_list($items) || count($items) !== 1 || ! is_array($items[0])) {
            throw new UnexpectedValueException('The YouTube channel identity response did not contain exactly one channel.');
        }
        $channel = $items[0];
        if ($this->optionalString($channel['id'] ?? null, 80) !== $channelId) {
            throw new UnexpectedValueException('The YouTube channel id did not match the configured source policy.');
        }
        $snippet = $channel['snippet'] ?? null;
        $actualTitle = is_array($snippet) ? $this->optionalString($snippet['title'] ?? null, 200) : null;
        if ($actualTitle === null || mb_strtolower($actualTitle) !== mb_strtolower($channelTitle)) {
            throw new UnexpectedValueException('The YouTube channel title did not match the configured source policy.');
        }
        $contentDetails = $channel['contentDetails'] ?? null;
        $related = is_array($contentDetails) ? ($contentDetails['relatedPlaylists'] ?? null) : null;
        $uploadsPlaylist = is_array($related) ? $this->optionalString($related['uploads'] ?? null, 120) : null;
        if ($uploadsPlaylist === null) {
            throw new UnexpectedValueException('The YouTube channel response did not include its uploads playlist.');
        }

        $pageSize = max(1, min(50, $limit));
        $response = Http::acceptJson()
            ->timeout($timeout)
            ->withOptions(['allow_redirects' => false])
            ->get('https://www.googleapis.com/youtube/v3/playlistItems', array_filter([
                'part' => 'snippet,contentDetails',
                'playlistId' => $uploadsPlaylist,
                'maxResults' => $pageSize,
                'pageToken' => $cursor === null ? null : trim($cursor),
                'key' => $apiKey,
            ], static fn (mixed $value): bool => $value !== null && $value !== ''));
        $this->assertJsonSuccess($response, 'YouTube uploads lookup');
        $payload = $response->json();
        $uploads = is_array($payload) ? ($payload['items'] ?? null) : null;
        if (! is_array($uploads) || ! array_is_list($uploads) || count($uploads) > $pageSize) {
            throw new UnexpectedValueException('YouTube returned an invalid or unbounded uploads collection.');
        }

        $retrievalVersion = $this->giftCodeRetrievalVersion($response);
        $observations = [];
        $latestVideoId = null;
        foreach ($uploads as $position => $upload) {
            if (! is_array($upload)) {
                throw new UnexpectedValueException(sprintf('YouTube upload %d must be an object.', $position + 1));
            }
            $uploadSnippet = $upload['snippet'] ?? null;
            if (! is_array($uploadSnippet)) {
                throw new UnexpectedValueException(sprintf('YouTube upload %d requires snippet metadata.', $position + 1));
            }
            $resourceId = $uploadSnippet['resourceId'] ?? null;
            $videoId = is_array($resourceId) ? $this->optionalString($resourceId['videoId'] ?? null, 32) : null;
            if ($videoId === null || preg_match('/^[A-Za-z0-9_-]{6,32}$/D', $videoId) !== 1) {
                throw new UnexpectedValueException(sprintf('YouTube upload %d requires a valid video id.', $position + 1));
            }
            $latestVideoId ??= $videoId;
            $publishedAt = $this->optionalString($uploadSnippet['publishedAt'] ?? null, 120);
            $title = $this->optionalString($uploadSnippet['title'] ?? null, 1000) ?? '';
            $description = $this->optionalString($uploadSnippet['description'] ?? null, 20_000) ?? '';
            $codes = array_values(array_unique([
                ...$this->explicitGiftCodes($title),
                ...$this->explicitGiftCodes($description),
            ]));
            foreach ($codes as $code) {
                $sourceUrl = 'https://www.youtube.com/watch?v='.rawurlencode($videoId);
                $fingerprint = hash('sha256', json_encode($upload, JSON_THROW_ON_ERROR));
                $observations[] = new GiftCodeIngestionObservation(
                    code: $code,
                    assertion: 'available',
                    assertionPayload: null,
                    sourceUrl: $sourceUrl,
                    claimedExpiresAt: null,
                    expiryPrecision: null,
                    expiryTimezone: null,
                    publishedAt: $publishedAt,
                    sourceVersion: 'youtube-video:'.$videoId,
                    retrievalVersion: $retrievalVersion,
                    parserVersion: self::KEY,
                    contentFingerprint: $fingerprint,
                    rawEvidenceRef: $sourceUrl.'#gift-code='.rawurlencode($code),
                    verificationPassed: true,
                );
            }
        }

        $nextCursor = is_array($payload)
            ? $this->optionalString($payload['nextPageToken'] ?? null, 2000)
            : null;
        $providerRequestId = $this->giftCodeProviderRequestId($response);

        return new GiftCodeIngestionPage(
            observations: $observations,
            nextCursor: $nextCursor,
            retrievalVersion: $retrievalVersion,
            providerRequestId: $providerRequestId,
            rateLimit: $this->giftCodeRateLimit($response),
            checkpoint: new GiftCodeSourceCheckpoint(
                cursor: $nextCursor,
                retrievalVersion: $retrievalVersion,
                providerRequestId: $providerRequestId,
                providerState: [
                    'channel_id' => $channelId,
                    'uploads_playlist_id' => $uploadsPlaylist,
                    'latest_video_id' => $latestVideoId,
                ],
            ),
            requestCount: 2,
        );
    }

    /** @param array<string,mixed> $policy */
    private function requiredPolicyString(array $policy, string $key, int $maximum): string
    {
        $value = $this->optionalString($policy[$key] ?? null, $maximum);
        if ($value === null) {
            throw new UnexpectedValueException(sprintf('The YouTube channel adapter requires source policy %s.', $key));
        }

        return $value;
    }

    private function assertJsonSuccess(Response $response, string $operation): void
    {
        $this->assertGiftCodeProviderSuccess($response, $operation);
        if (! str_contains(mb_strtolower((string) $response->header('Content-Type')), 'json')) {
            throw new UnexpectedValueException($operation.' did not return JSON content.');
        }
    }

    private function optionalString(mixed $value, int $maximum): ?string
    {
        if ($value === null) {
            return null;
        }
        if (! is_string($value)) {
            throw new UnexpectedValueException('YouTube API scalar fields must be strings.');
        }
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        if (mb_strlen($value) > $maximum) {
            throw new UnexpectedValueException('A YouTube API scalar field exceeded its maximum length.');
        }

        return $value;
    }
}
