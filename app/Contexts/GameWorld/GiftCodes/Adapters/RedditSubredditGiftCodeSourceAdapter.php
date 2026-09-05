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

final class RedditSubredditGiftCodeSourceAdapter implements GiftCodeSourceAdapter
{
    use HandlesGiftCodeProviderResponses;
    use ParsesExplicitGiftCodeLabels;

    public const KEY = 'reddit-data-api-v1';

    public function key(): string
    {
        return self::KEY;
    }

    public function acquire(GiftCodeSourceRegistry $source, ?string $cursor, int $limit): GiftCodeIngestionPage
    {
        $policy = $source->provenance_policy ?? [];
        if (($policy['platform_api_access_confirmed'] ?? false) !== true) {
            throw new UnexpectedValueException('Reddit ingestion requires confirmed Data API registration and access.');
        }
        if (mb_strtolower(rtrim(trim((string) $source->canonical_domain), '.')) !== 'reddit.com') {
            throw new UnexpectedValueException('The Reddit adapter requires reddit.com as the canonical source domain.');
        }
        if ($source->classification !== 'independent' || ($policy['auto_verify'] ?? false) === true) {
            throw new UnexpectedValueException('Reddit is a discovery source and must remain independent with automatic verification disabled.');
        }
        if ($cursor !== null && mb_strlen(trim($cursor)) > 256) {
            throw new UnexpectedValueException('The Reddit pagination cursor exceeded its maximum length.');
        }

        $subreddit = $this->requiredPolicyString($policy, 'reddit_subreddit', 32);
        if (preg_match('/^[A-Za-z0-9_]{2,32}$/D', $subreddit) !== 1) {
            throw new UnexpectedValueException('The Reddit adapter requires a valid subreddit name.');
        }
        $clientId = trim((string) config('game_world.gift_codes.reddit_client_id', ''));
        $clientSecret = trim((string) config('game_world.gift_codes.reddit_client_secret', ''));
        $userAgent = trim((string) config('game_world.gift_codes.reddit_user_agent', ''));
        if ($clientId === '' || $clientSecret === '' || $userAgent === '') {
            throw new UnexpectedValueException('The Reddit adapter requires configured OAuth credentials and a descriptive User-Agent.');
        }

        $timeout = max(1, min(30, (int) config('game_world.gift_codes.ingestion_timeout_seconds', 10)));
        $tokenResponse = Http::withBasicAuth($clientId, $clientSecret)
            ->withHeaders(['User-Agent' => $userAgent, 'Accept' => 'application/json'])
            ->asForm()
            ->timeout($timeout)
            ->withOptions(['allow_redirects' => false])
            ->post('https://www.reddit.com/api/v1/access_token', [
                'grant_type' => 'client_credentials',
            ]);
        $this->assertJsonSuccess($tokenResponse, 'Reddit OAuth token request');
        $tokenPayload = $tokenResponse->json();
        $accessToken = is_array($tokenPayload)
            ? $this->optionalString($tokenPayload['access_token'] ?? null, 4096)
            : null;
        if ($accessToken === null) {
            throw new UnexpectedValueException('Reddit OAuth did not return an access token.');
        }

        $pageSize = max(1, min(100, $limit));
        $response = Http::withToken($accessToken)
            ->withHeaders(['User-Agent' => $userAgent, 'Accept' => 'application/json'])
            ->timeout($timeout)
            ->withOptions(['allow_redirects' => false])
            ->get('https://oauth.reddit.com/r/'.rawurlencode($subreddit).'/new', array_filter([
                'limit' => $pageSize,
                'after' => $cursor === null ? null : trim($cursor),
                'raw_json' => 1,
            ], static fn (mixed $value): bool => $value !== null && $value !== ''));
        $this->assertJsonSuccess($response, 'Reddit subreddit listing');
        $payload = $response->json();
        $listing = is_array($payload) ? ($payload['data'] ?? null) : null;
        $children = is_array($listing) ? ($listing['children'] ?? null) : null;
        if (! is_array($children) || ! array_is_list($children) || count($children) > $pageSize) {
            throw new UnexpectedValueException('Reddit returned an invalid or unbounded listing.');
        }

        $retrievalVersion = $this->giftCodeRetrievalVersion($response);
        $observations = [];
        $latestPostFullname = null;
        foreach ($children as $position => $child) {
            $post = is_array($child) ? ($child['data'] ?? null) : null;
            if (! is_array($post)) {
                throw new UnexpectedValueException(sprintf('Reddit post %d must contain a data object.', $position + 1));
            }
            $postId = $this->requiredString($post['id'] ?? null, 'post id', $position + 1, 32);
            $latestPostFullname ??= 't3_'.$postId;
            $title = $this->optionalString($post['title'] ?? null, 1000) ?? '';
            $selfText = $this->optionalString($post['selftext'] ?? null, 40_000) ?? '';
            $permalink = $this->requiredString($post['permalink'] ?? null, 'permalink', $position + 1, 2048);
            if (! str_starts_with($permalink, '/') || str_starts_with($permalink, '//')) {
                throw new UnexpectedValueException(sprintf('Reddit post %d returned an invalid permalink.', $position + 1));
            }
            $codes = array_values(array_unique([
                ...$this->explicitGiftCodes($title),
                ...$this->explicitGiftCodes($selfText),
            ]));
            foreach ($codes as $code) {
                $sourceUrl = 'https://www.reddit.com'.$permalink;
                $fingerprint = hash('sha256', json_encode($post, JSON_THROW_ON_ERROR));
                $created = $post['created_utc'] ?? null;
                $publishedAt = is_int($created) || is_float($created)
                    ? gmdate(DATE_ATOM, (int) $created)
                    : null;
                $observations[] = new GiftCodeIngestionObservation(
                    code: $code,
                    assertion: 'available',
                    assertionPayload: null,
                    sourceUrl: $sourceUrl,
                    claimedExpiresAt: null,
                    expiryPrecision: null,
                    expiryTimezone: null,
                    publishedAt: $publishedAt,
                    sourceVersion: 'reddit-post:'.$postId,
                    retrievalVersion: $retrievalVersion,
                    parserVersion: self::KEY,
                    contentFingerprint: $fingerprint,
                    rawEvidenceRef: $sourceUrl.'#gift-code='.rawurlencode($code),
                    verificationPassed: true,
                );
            }
        }

        $nextCursor = is_array($listing)
            ? $this->optionalString($listing['after'] ?? null, 256)
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
                    'subreddit' => $subreddit,
                    'latest_post_fullname' => $latestPostFullname,
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
            throw new UnexpectedValueException(sprintf('The Reddit adapter requires source policy %s.', $key));
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

    private function requiredString(mixed $value, string $field, int $position, int $maximum): string
    {
        $value = $this->optionalString($value, $maximum);
        if ($value === null) {
            throw new UnexpectedValueException(sprintf('Reddit post %d requires a non-empty %s.', $position, $field));
        }

        return $value;
    }

    private function optionalString(mixed $value, int $maximum): ?string
    {
        if ($value === null) {
            return null;
        }
        if (! is_string($value)) {
            throw new UnexpectedValueException('Reddit API scalar fields must be strings.');
        }
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        if (mb_strlen($value) > $maximum) {
            throw new UnexpectedValueException('A Reddit API scalar field exceeded its maximum length.');
        }

        return $value;
    }
}
