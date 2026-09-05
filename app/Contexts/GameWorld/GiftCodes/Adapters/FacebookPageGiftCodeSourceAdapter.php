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

final class FacebookPageGiftCodeSourceAdapter implements GiftCodeSourceAdapter
{
    use HandlesGiftCodeProviderResponses;
    use ParsesExplicitGiftCodeLabels;

    public const KEY = 'facebook-page-v1';

    public function key(): string
    {
        return self::KEY;
    }

    public function acquire(GiftCodeSourceRegistry $source, ?string $cursor, int $limit): GiftCodeIngestionPage
    {
        $policy = $source->provenance_policy ?? [];
        if (($policy['platform_permission_confirmed'] ?? false) !== true) {
            throw new UnexpectedValueException('Facebook Page ingestion requires confirmed Page access and platform permission.');
        }
        if (mb_strtolower(rtrim(trim((string) $source->canonical_domain), '.')) !== 'facebook.com') {
            throw new UnexpectedValueException('The Facebook Page adapter requires facebook.com as the canonical source domain.');
        }
        if ($cursor !== null && mb_strlen(trim($cursor)) > 2048) {
            throw new UnexpectedValueException('The Facebook pagination cursor exceeded its maximum length.');
        }

        $pageId = $this->requiredPolicyString($policy, 'facebook_page_id', 64);
        $pageName = $this->requiredPolicyString($policy, 'facebook_page_name', 200);
        if (preg_match('/^[0-9]{1,64}$/D', $pageId) !== 1) {
            throw new UnexpectedValueException('The Facebook Page adapter requires a numeric Page id.');
        }
        $token = trim((string) config('game_world.gift_codes.facebook_access_token', ''));
        $version = trim((string) config('game_world.gift_codes.meta_graph_api_version', 'v26.0'));
        if ($token === '') {
            throw new UnexpectedValueException('The Facebook Page adapter requires a configured Page access token.');
        }
        if (preg_match('/^v[0-9]+\.[0-9]+$/D', $version) !== 1) {
            throw new UnexpectedValueException('The configured Meta Graph API version is invalid.');
        }

        $timeout = max(1, min(30, (int) config('game_world.gift_codes.ingestion_timeout_seconds', 10)));
        $identity = Http::withToken($token)
            ->acceptJson()
            ->timeout($timeout)
            ->withOptions(['allow_redirects' => false])
            ->get(sprintf('https://graph.facebook.com/%s/%s', $version, rawurlencode($pageId)), [
                'fields' => 'id,name',
            ]);
        $this->assertJsonSuccess($identity, 'Facebook Page lookup');
        $identityPayload = $identity->json();
        if (! is_array($identityPayload)
            || $this->optionalString($identityPayload['id'] ?? null, 64) !== $pageId
            || mb_strtolower($this->optionalString($identityPayload['name'] ?? null, 200) ?? '') !== mb_strtolower($pageName)) {
            throw new UnexpectedValueException('The Facebook Page identity did not match the configured source policy.');
        }

        $pageSize = max(1, min(100, $limit));
        $response = Http::withToken($token)
            ->acceptJson()
            ->timeout($timeout)
            ->withOptions(['allow_redirects' => false])
            ->get(sprintf('https://graph.facebook.com/%s/%s/posts', $version, rawurlencode($pageId)), array_filter([
                'fields' => 'id,message,created_time,permalink_url',
                'limit' => $pageSize,
                'after' => $cursor === null ? null : trim($cursor),
            ], static fn (mixed $value): bool => $value !== null && $value !== ''));
        $this->assertJsonSuccess($response, 'Facebook Page posts');
        $payload = $response->json();
        $posts = is_array($payload) ? ($payload['data'] ?? null) : null;
        if (! is_array($posts) || ! array_is_list($posts) || count($posts) > $pageSize) {
            throw new UnexpectedValueException('Facebook returned an invalid or unbounded Page post collection.');
        }

        $retrievalVersion = $this->giftCodeRetrievalVersion($response);
        $observations = [];
        $latestPostId = null;
        foreach ($posts as $position => $post) {
            if (! is_array($post)) {
                throw new UnexpectedValueException(sprintf('Facebook Page post %d must be an object.', $position + 1));
            }
            $postId = $this->requiredString($post['id'] ?? null, 'post id', $position + 1, 160);
            $latestPostId ??= $postId;
            $message = $this->optionalString($post['message'] ?? null, 40_000) ?? '';
            $permalink = $this->optionalString($post['permalink_url'] ?? null, 2048);
            if ($permalink === null) {
                continue;
            }
            $this->assertFacebookUrl($permalink);
            foreach ($this->explicitGiftCodes($message) as $code) {
                $fingerprint = hash('sha256', json_encode($post, JSON_THROW_ON_ERROR));
                $observations[] = new GiftCodeIngestionObservation(
                    code: $code,
                    assertion: 'available',
                    assertionPayload: null,
                    sourceUrl: $permalink,
                    claimedExpiresAt: null,
                    expiryPrecision: null,
                    expiryTimezone: null,
                    publishedAt: $this->optionalString($post['created_time'] ?? null, 120),
                    sourceVersion: 'facebook-post:'.$postId,
                    retrievalVersion: $retrievalVersion,
                    parserVersion: self::KEY,
                    contentFingerprint: $fingerprint,
                    rawEvidenceRef: $permalink.'#gift-code='.rawurlencode($code),
                    verificationPassed: true,
                );
            }
        }

        $paging = is_array($payload) ? ($payload['paging'] ?? null) : null;
        $cursors = is_array($paging) ? ($paging['cursors'] ?? null) : null;
        $hasNext = is_array($paging) && $this->optionalString($paging['next'] ?? null, 4096) !== null;
        $nextCursor = $hasNext && is_array($cursors)
            ? $this->optionalString($cursors['after'] ?? null, 2048)
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
                    'page_id' => $pageId,
                    'latest_post_id' => $latestPostId,
                    'graph_api_version' => $version,
                ],
            ),
            requestCount: 2,
        );
    }

    private function assertFacebookUrl(string $url): void
    {
        $scheme = parse_url($url, PHP_URL_SCHEME);
        $host = parse_url($url, PHP_URL_HOST);
        $host = is_string($host) ? mb_strtolower(rtrim($host, '.')) : null;
        if ($scheme !== 'https'
            || $host === null
            || ($host !== 'facebook.com' && ! str_ends_with($host, '.facebook.com'))) {
            throw new UnexpectedValueException('Facebook evidence links must remain on facebook.com over HTTPS.');
        }
    }

    /** @param array<string,mixed> $policy */
    private function requiredPolicyString(array $policy, string $key, int $maximum): string
    {
        $value = $this->optionalString($policy[$key] ?? null, $maximum);
        if ($value === null) {
            throw new UnexpectedValueException(sprintf('The Facebook Page adapter requires source policy %s.', $key));
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
            throw new UnexpectedValueException(sprintf('Facebook Page post %d requires a non-empty %s.', $position, $field));
        }

        return $value;
    }

    private function optionalString(mixed $value, int $maximum): ?string
    {
        if ($value === null) {
            return null;
        }
        if (! is_string($value)) {
            throw new UnexpectedValueException('Facebook Graph API scalar fields must be strings.');
        }
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        if (mb_strlen($value) > $maximum) {
            throw new UnexpectedValueException('A Facebook Graph API scalar field exceeded its maximum length.');
        }

        return $value;
    }
}
