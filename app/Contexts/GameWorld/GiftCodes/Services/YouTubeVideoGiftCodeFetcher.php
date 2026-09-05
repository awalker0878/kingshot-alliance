<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\Services;

use App\Contexts\GameWorld\GiftCodes\Adapters\Concerns\HandlesGiftCodeProviderResponses;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeSourceRegistry;
use Illuminate\Support\Facades\Http;
use UnexpectedValueException;

final class YouTubeVideoGiftCodeFetcher
{
    use HandlesGiftCodeProviderResponses;

    public function fetch(GiftCodeSourceRegistry $source, string $videoId): GiftCodeProviderPublication
    {
        if (preg_match('/^[A-Za-z0-9_-]{6,32}$/D', $videoId) !== 1) {
            throw new UnexpectedValueException('YouTube WebSub delivery contains an invalid video id.');
        }
        $policy = $source->provenance_policy ?? [];
        $channelId = is_string($policy['youtube_channel_id'] ?? null) ? trim($policy['youtube_channel_id']) : '';
        $channelTitle = is_string($policy['youtube_channel_title'] ?? null) ? trim($policy['youtube_channel_title']) : '';
        if ($channelId === '' || $channelTitle === '') {
            throw new UnexpectedValueException('YouTube source identity policy is incomplete.');
        }
        $apiKey = trim((string) config('game_world.gift_codes.youtube_api_key', ''));
        if ($apiKey === '') {
            throw new UnexpectedValueException('The YouTube Data API key is not configured.');
        }

        $response = Http::acceptJson()
            ->timeout(max(1, min(30, (int) config('game_world.gift_codes.ingestion_timeout_seconds', 10))))
            ->withOptions(['allow_redirects' => false])
            ->get('https://www.googleapis.com/youtube/v3/videos', [
                'part' => 'snippet',
                'id' => $videoId,
                'key' => $apiKey,
            ]);
        $this->assertGiftCodeProviderSuccess($response, 'YouTube video lookup');
        if (! str_contains(mb_strtolower((string) $response->header('Content-Type')), 'json')) {
            throw new UnexpectedValueException('YouTube video lookup did not return JSON content.');
        }
        $payload = $response->json();
        $items = is_array($payload) ? ($payload['items'] ?? null) : null;
        if (! is_array($items) || ! array_is_list($items) || count($items) !== 1 || ! is_array($items[0])) {
            throw new UnexpectedValueException('YouTube video lookup did not return exactly one video.');
        }
        $video = $items[0];
        if (($video['id'] ?? null) !== $videoId) {
            throw new UnexpectedValueException('YouTube video identity did not match the delivery.');
        }
        $snippet = $video['snippet'] ?? null;
        if (! is_array($snippet)
            || ($snippet['channelId'] ?? null) !== $channelId
            || mb_strtolower(trim((string) ($snippet['channelTitle'] ?? ''))) !== mb_strtolower($channelTitle)) {
            throw new UnexpectedValueException('YouTube video channel identity did not match the configured source.');
        }
        $title = is_string($snippet['title'] ?? null) ? trim($snippet['title']) : '';
        $description = is_string($snippet['description'] ?? null) ? trim($snippet['description']) : '';
        $publishedAt = is_string($snippet['publishedAt'] ?? null) ? trim($snippet['publishedAt']) : null;

        return new GiftCodeProviderPublication(
            provider: 'youtube',
            providerItemId: $videoId,
            sourceUrl: 'https://www.youtube.com/watch?v='.rawurlencode($videoId),
            content: $title."\n".$description,
            publishedAt: $publishedAt,
            retrievalVersion: $this->giftCodeRetrievalVersion($response),
        );
    }
}
