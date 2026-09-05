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

final class InstagramMediaGiftCodeSourceAdapter implements GiftCodeSourceAdapter
{
    use ParsesExplicitGiftCodeLabels;

    public const KEY = 'instagram-media-v1';

    public function key(): string
    {
        return self::KEY;
    }

    public function acquire(GiftCodeSourceRegistry $source, ?string $cursor, int $limit): GiftCodeIngestionPage
    {
        $policy = $source->provenance_policy ?? [];
        if (($policy['platform_permission_confirmed'] ?? false) !== true) {
            throw new UnexpectedValueException('Instagram ingestion requires confirmed professional-account API access.');
        }
        if (mb_strtolower(rtrim(trim((string) $source->canonical_domain), '.')) !== 'instagram.com') {
            throw new UnexpectedValueException('The Instagram media adapter requires instagram.com as the canonical source domain.');
        }
        if ($cursor !== null && mb_strlen(trim($cursor)) > 2048) {
            throw new UnexpectedValueException('The Instagram pagination cursor exceeded its maximum length.');
        }

        $userId = $this->requiredPolicyString($policy, 'instagram_user_id', 64);
        $username = $this->requiredPolicyString($policy, 'instagram_username', 80);
        if (preg_match('/^[0-9]{1,64}$/D', $userId) !== 1
            || preg_match('/^[A-Za-z0-9._]{1,80}$/D', $username) !== 1) {
            throw new UnexpectedValueException('The Instagram media adapter requires a valid professional account id and username.');
        }
        $token = trim((string) config('game_world.gift_codes.instagram_access_token', ''));
        $version = trim((string) config('game_world.gift_codes.meta_graph_api_version', 'v26.0'));
        if ($token === '') {
            throw new UnexpectedValueException('The Instagram media adapter requires a configured access token.');
        }
        if (preg_match('/^v[0-9]+\.[0-9]+$/D', $version) !== 1) {
            throw new UnexpectedValueException('The configured Meta Graph API version is invalid.');
        }

        $timeout = max(1, min(30, (int) config('game_world.gift_codes.ingestion_timeout_seconds', 10)));
        $base = 'https://graph.instagram.com/'.$version.'/'.rawurlencode($userId);
        $identity = Http::withToken($token)
            ->acceptJson()
            ->timeout($timeout)
            ->withOptions(['allow_redirects' => false])
            ->get($base, ['fields' => 'id,username']);
        $this->assertJsonSuccess($identity, 'Instagram account lookup');
        $identityPayload = $identity->json();
        if (! is_array($identityPayload)
            || $this->optionalString($identityPayload['id'] ?? null, 64) !== $userId
            || mb_strtolower($this->optionalString($identityPayload['username'] ?? null, 80) ?? '') !== mb_strtolower($username)) {
            throw new UnexpectedValueException('The Instagram account identity did not match the configured source policy.');
        }

        $pageSize = max(1, min(100, $limit));
        $response = Http::withToken($token)
            ->acceptJson()
            ->timeout($timeout)
            ->withOptions(['allow_redirects' => false])
            ->get($base.'/media', array_filter([
                'fields' => 'id,caption,permalink,timestamp,username',
                'limit' => $pageSize,
                'after' => $cursor === null ? null : trim($cursor),
            ], static fn (mixed $value): bool => $value !== null && $value !== ''));
        $this->assertJsonSuccess($response, 'Instagram media lookup');
        $payload = $response->json();
        $media = is_array($payload) ? ($payload['data'] ?? null) : null;
        if (! is_array($media) || ! array_is_list($media) || count($media) > $pageSize) {
            throw new UnexpectedValueException('Instagram returned an invalid or unbounded media collection.');
        }

        $retrievalVersion = $this->retrievalVersion($response);
        $observations = [];
        foreach ($media as $position => $item) {
            if (! is_array($item)) {
                throw new UnexpectedValueException(sprintf('Instagram media item %d must be an object.', $position + 1));
            }
            $mediaId = $this->requiredString($item['id'] ?? null, 'media id', $position + 1, 128);
            $itemUsername = $this->optionalString($item['username'] ?? null, 80);
            if ($itemUsername !== null && mb_strtolower($itemUsername) !== mb_strtolower($username)) {
                throw new UnexpectedValueException(sprintf('Instagram media item %d was not owned by the configured account.', $position + 1));
            }
            $caption = $this->optionalString($item['caption'] ?? null, 40_000) ?? '';
            $permalink = $this->optionalString($item['permalink'] ?? null, 2048);
            if ($permalink === null) {
                continue;
            }
            $this->assertInstagramUrl($permalink);
            foreach ($this->explicitGiftCodes($caption) as $code) {
                $fingerprint = hash('sha256', json_encode($item, JSON_THROW_ON_ERROR));
                $observations[] = new GiftCodeIngestionObservation(
                    code: $code,
                    assertion: 'available',
                    assertionPayload: null,
                    sourceUrl: $permalink,
                    claimedExpiresAt: null,
                    expiryPrecision: null,
                    expiryTimezone: null,
                    publishedAt: $this->optionalString($item['timestamp'] ?? null, 120),
                    sourceVersion: 'instagram-media:'.$mediaId,
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

        return new GiftCodeIngestionPage($observations, $nextCursor);
    }

    private function assertInstagramUrl(string $url): void
    {
        $scheme = parse_url($url, PHP_URL_SCHEME);
        $host = parse_url($url, PHP_URL_HOST);
        $host = is_string($host) ? mb_strtolower(rtrim($host, '.')) : null;
        if ($scheme !== 'https'
            || $host === null
            || ($host !== 'instagram.com' && ! str_ends_with($host, '.instagram.com'))) {
            throw new UnexpectedValueException('Instagram evidence links must remain on instagram.com over HTTPS.');
        }
    }

    /** @param array<string,mixed> $policy */
    private function requiredPolicyString(array $policy, string $key, int $maximum): string
    {
        $value = $this->optionalString($policy[$key] ?? null, $maximum);
        if ($value === null) {
            throw new UnexpectedValueException(sprintf('The Instagram media adapter requires source policy %s.', $key));
        }

        return $value;
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
            throw new UnexpectedValueException(sprintf('Instagram media item %d requires a non-empty %s.', $position, $field));
        }

        return $value;
    }

    private function optionalString(mixed $value, int $maximum): ?string
    {
        if ($value === null) {
            return null;
        }
        if (! is_string($value)) {
            throw new UnexpectedValueException('Instagram Graph API scalar fields must be strings.');
        }
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        if (mb_strlen($value) > $maximum) {
            throw new UnexpectedValueException('An Instagram Graph API scalar field exceeded its maximum length.');
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
