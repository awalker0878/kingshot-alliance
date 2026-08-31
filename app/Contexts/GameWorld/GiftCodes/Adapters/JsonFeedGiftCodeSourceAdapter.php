<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\Adapters;

use App\Contexts\GameWorld\GiftCodes\Contracts\GiftCodeSourceAdapter;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeSourceRegistry;
use App\Contexts\GameWorld\GiftCodes\ValueObjects\GiftCodeIngestionObservation;
use App\Contexts\GameWorld\GiftCodes\ValueObjects\GiftCodeIngestionPage;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use UnexpectedValueException;

final class JsonFeedGiftCodeSourceAdapter implements GiftCodeSourceAdapter
{
    public const KEY = 'json-feed-v1';

    public function key(): string
    {
        return self::KEY;
    }

    public function acquire(GiftCodeSourceRegistry $source, ?string $cursor, int $limit): GiftCodeIngestionPage
    {
        $limit = max(1, min(500, $limit));
        $url = $this->feedUrl($source);
        $response = Http::acceptJson()
            ->timeout(max(1, min(30, (int) config('game_world.gift_codes.ingestion_timeout_seconds', 10))))
            ->withOptions(['allow_redirects' => false])
            ->get($url, array_filter([
                'cursor' => $cursor,
                'limit' => $limit,
            ], static fn (mixed $value): bool => $value !== null && $value !== ''));

        if (! $response->successful()) {
            throw new \RuntimeException(sprintf(
                'The approved source returned HTTP %d.',
                $response->status(),
            ));
        }
        if (! str_contains(mb_strtolower((string) $response->header('Content-Type')), 'json')) {
            throw new UnexpectedValueException('The approved source did not return JSON content.');
        }

        $payload = $response->json();
        if (! is_array($payload) || ! isset($payload['items']) || ! is_array($payload['items'])) {
            throw new UnexpectedValueException('The approved source JSON must contain an items array.');
        }
        if (! array_is_list($payload['items']) || count($payload['items']) > $limit) {
            throw new UnexpectedValueException('The approved source returned an invalid or unbounded items collection.');
        }

        $feedVersion = $this->optionalString($payload['version'] ?? null, 120)
            ?? $this->retrievalVersion($response);
        $observations = [];
        foreach ($payload['items'] as $position => $item) {
            if (! is_array($item)) {
                throw new UnexpectedValueException(sprintf('Gift Code feed item %d must be an object.', $position + 1));
            }
            $observations[] = $this->observation($item, $url, $feedVersion, $response, $position + 1);
        }

        return new GiftCodeIngestionPage(
            $observations,
            $this->optionalString($payload['next_cursor'] ?? null, 2000),
        );
    }

    private function feedUrl(GiftCodeSourceRegistry $source): string
    {
        $domain = mb_strtolower(rtrim(trim((string) $source->canonical_domain), '.'));
        $policy = $source->provenance_policy ?? [];
        $path = is_string($policy['feed_path'] ?? null) ? trim($policy['feed_path']) : '';
        if ($domain === '' || filter_var($domain, FILTER_VALIDATE_IP) !== false || $domain === 'localhost') {
            throw new UnexpectedValueException('The JSON feed adapter requires a public canonical hostname.');
        }
        if ($path === '' || ! str_starts_with($path, '/') || str_starts_with($path, '//')) {
            throw new UnexpectedValueException('The JSON feed adapter requires an absolute feed path on the canonical domain.');
        }
        $parts = parse_url($path);
        if ($parts === false
            || ($parts['path'] ?? null) !== $path
            || str_contains('/'.$path.'/', '/../')
            || str_contains('/'.$path.'/', '/./')) {
            throw new UnexpectedValueException('The JSON feed path cannot contain a host, query, fragment, or traversal segment.');
        }

        return 'https://'.$domain.$path;
    }

    /**
     * @param  array<string, mixed>  $item
     */
    private function observation(
        array $item,
        string $feedUrl,
        string $feedVersion,
        Response $response,
        int $position,
    ): GiftCodeIngestionObservation {
        $code = $this->requiredString($item['code'] ?? null, 'code', $position, 64);
        $assertion = $this->requiredString($item['assertion'] ?? null, 'assertion', $position, 48);
        $sourceUrl = $this->optionalString($item['source_url'] ?? null, 2048) ?? $feedUrl;
        $payload = $item['payload'] ?? null;
        if ($payload !== null && ! is_array($payload)) {
            throw new UnexpectedValueException(sprintf('Gift Code feed item %d payload must be an object or array.', $position));
        }
        $canonicalItem = json_encode($item, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $fingerprint = hash('sha256', $canonicalItem);

        return new GiftCodeIngestionObservation(
            code: $code,
            assertion: $assertion,
            assertionPayload: $payload,
            sourceUrl: $sourceUrl,
            claimedExpiresAt: $this->optionalString($item['expires_at'] ?? null, 120),
            expiryPrecision: $this->optionalString($item['expiry_precision'] ?? null, 32),
            expiryTimezone: $this->optionalString($item['expiry_timezone'] ?? null, 80),
            publishedAt: $this->optionalString($item['published_at'] ?? null, 120),
            sourceVersion: $this->optionalString($item['version'] ?? null, 120) ?? $feedVersion,
            retrievalVersion: $this->retrievalVersion($response),
            parserVersion: self::KEY,
            contentFingerprint: $fingerprint,
            rawEvidenceRef: $sourceUrl.'#gift-code-observation='.$fingerprint,
            verificationPassed: true,
        );
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

    private function requiredString(mixed $value, string $field, int $position, int $maximum): string
    {
        $value = $this->optionalString($value, $maximum);
        if ($value === null) {
            throw new UnexpectedValueException(sprintf(
                'Gift Code feed item %d requires a non-empty %s string.',
                $position,
                $field,
            ));
        }

        return $value;
    }

    private function optionalString(mixed $value, int $maximum): ?string
    {
        if ($value === null) {
            return null;
        }
        if (! is_string($value)) {
            throw new UnexpectedValueException('Gift Code feed scalar fields must be strings.');
        }
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        if (mb_strlen($value) > $maximum) {
            throw new UnexpectedValueException('A Gift Code feed scalar field exceeded its maximum length.');
        }

        return $value;
    }
}
