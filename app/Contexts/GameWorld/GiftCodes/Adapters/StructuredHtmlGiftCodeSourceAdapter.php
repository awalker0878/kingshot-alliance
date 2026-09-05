<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\Adapters;

use App\Contexts\GameWorld\GiftCodes\Contracts\GiftCodeSourceAdapter;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeSourceRegistry;
use App\Contexts\GameWorld\GiftCodes\ValueObjects\GiftCodeIngestionObservation;
use App\Contexts\GameWorld\GiftCodes\ValueObjects\GiftCodeIngestionPage;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use UnexpectedValueException;

final class StructuredHtmlGiftCodeSourceAdapter implements GiftCodeSourceAdapter
{
    public const KEY = 'structured-html-v1';

    public function key(): string
    {
        return self::KEY;
    }

    public function acquire(GiftCodeSourceRegistry $source, ?string $cursor, int $limit): GiftCodeIngestionPage
    {
        if ($cursor !== null && trim($cursor) !== '') {
            throw new UnexpectedValueException('The structured HTML adapter does not accept a source cursor.');
        }

        $limit = max(1, min(500, $limit));
        $url = $this->feedUrl($source);
        $response = Http::accept('text/html, application/xhtml+xml;q=0.9')
            ->timeout(max(1, min(30, (int) config('game_world.gift_codes.ingestion_timeout_seconds', 10))))
            ->withOptions(['allow_redirects' => false])
            ->get($url);

        if (! $response->successful()) {
            throw new \RuntimeException(sprintf('The approved HTML source returned HTTP %d.', $response->status()));
        }
        $contentType = mb_strtolower((string) $response->header('Content-Type'));
        if (! str_contains($contentType, 'html') && ! str_contains($contentType, 'xhtml')) {
            throw new UnexpectedValueException('The approved HTML source did not return HTML content.');
        }

        $body = $response->body();
        $this->assertBoundedDocument($body);
        $document = $this->parseHtml($body);
        $xpath = new DOMXPath($document);
        $items = $xpath->query('//*[@data-gift-code]');
        if ($items === false) {
            throw new UnexpectedValueException('The approved HTML Gift Code elements could not be evaluated.');
        }
        if ($items->length > $limit) {
            throw new UnexpectedValueException('The approved HTML source exceeded the bounded observation limit.');
        }

        $retrievalVersion = $this->retrievalVersion($response);
        $observations = [];
        foreach ($items as $position => $item) {
            if (! $item instanceof DOMElement) {
                throw new UnexpectedValueException(sprintf('HTML Gift Code item %d must be an element.', $position + 1));
            }
            $code = $this->requiredAttribute($item, 'data-gift-code', $position + 1, 64);
            $assertion = $this->optionalAttribute($item, 'data-gift-code-assertion', 48) ?? 'valid';
            $sourceUrl = $this->optionalAttribute($item, 'data-gift-code-source-url', 2048) ?? $url;
            $payload = $this->payload($item, $position + 1);
            $serialized = $document->saveHTML($item);
            if ($serialized === false) {
                throw new UnexpectedValueException(sprintf('HTML Gift Code item %d could not be serialized.', $position + 1));
            }
            $fingerprint = hash('sha256', $serialized);

            $observations[] = new GiftCodeIngestionObservation(
                code: $code,
                assertion: $assertion,
                assertionPayload: $payload,
                sourceUrl: $sourceUrl,
                claimedExpiresAt: $this->optionalAttribute($item, 'data-gift-code-expires-at', 120),
                expiryPrecision: $this->optionalAttribute($item, 'data-gift-code-expiry-precision', 32),
                expiryTimezone: $this->optionalAttribute($item, 'data-gift-code-expiry-timezone', 80),
                publishedAt: $this->optionalAttribute($item, 'data-gift-code-published-at', 120),
                sourceVersion: $this->optionalAttribute($item, 'data-gift-code-source-version', 120) ?? $retrievalVersion,
                retrievalVersion: $retrievalVersion,
                parserVersion: self::KEY,
                contentFingerprint: $fingerprint,
                rawEvidenceRef: $url.'#html-observation='.$fingerprint,
                verificationPassed: true,
            );
        }

        return new GiftCodeIngestionPage($observations, null);
    }

    private function feedUrl(GiftCodeSourceRegistry $source): string
    {
        $domain = mb_strtolower(rtrim(trim((string) $source->canonical_domain), '.'));
        $policy = $source->provenance_policy ?? [];
        $path = is_string($policy['feed_path'] ?? null) ? trim($policy['feed_path']) : '';
        if ($domain === '' || filter_var($domain, FILTER_VALIDATE_IP) !== false || $domain === 'localhost') {
            throw new UnexpectedValueException('The structured HTML adapter requires a public canonical hostname.');
        }
        if (! $this->validPath($path)) {
            throw new UnexpectedValueException('The structured HTML adapter requires an absolute page path on the canonical domain.');
        }

        return 'https://'.$domain.$path;
    }

    private function validPath(string $path): bool
    {
        if ($path === '' || ! str_starts_with($path, '/') || str_starts_with($path, '//')) {
            return false;
        }
        $parts = parse_url($path);

        return $parts !== false
            && ($parts['path'] ?? null) === $path
            && ! str_contains('/'.$path.'/', '/../')
            && ! str_contains('/'.$path.'/', '/./');
    }

    private function assertBoundedDocument(string $body): void
    {
        $maximum = max(65_536, min(5_000_000, (int) config('game_world.gift_codes.ingestion_document_max_bytes', 2_000_000)));
        if (strlen($body) > $maximum) {
            throw new UnexpectedValueException('The approved HTML source document exceeded the configured size bound.');
        }
    }

    private function parseHtml(string $body): DOMDocument
    {
        $previous = libxml_use_internal_errors(true);
        libxml_clear_errors();
        try {
            $document = new DOMDocument;
            $loaded = $document->loadHTML($body, LIBXML_NONET | LIBXML_COMPACT | LIBXML_NOERROR | LIBXML_NOWARNING);
            if (! $loaded) {
                throw new UnexpectedValueException('The approved HTML source returned malformed HTML.');
            }

            return $document;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    /** @return array<string,mixed>|null */
    private function payload(DOMElement $item, int $position): ?array
    {
        $value = $this->optionalAttribute($item, 'data-gift-code-payload', 4096);
        if ($value === null) {
            return null;
        }
        try {
            $payload = json_decode($value, true, 32, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new UnexpectedValueException(
                sprintf('HTML Gift Code item %d contains invalid JSON assertion payload.', $position),
                previous: $exception,
            );
        }
        if (! is_array($payload)) {
            throw new UnexpectedValueException(sprintf(
                'HTML Gift Code item %d assertion payload must decode to an object or array.',
                $position,
            ));
        }

        return $payload;
    }

    private function requiredAttribute(DOMElement $item, string $attribute, int $position, int $maximum): string
    {
        $value = $this->optionalAttribute($item, $attribute, $maximum);
        if ($value === null) {
            throw new UnexpectedValueException(sprintf(
                'HTML Gift Code item %d requires a non-empty %s attribute.',
                $position,
                $attribute,
            ));
        }

        return $value;
    }

    private function optionalAttribute(DOMElement $item, string $attribute, int $maximum): ?string
    {
        if (! $item->hasAttribute($attribute)) {
            return null;
        }
        $value = trim($item->getAttribute($attribute));
        if ($value === '') {
            return null;
        }
        if (mb_strlen($value) > $maximum) {
            throw new UnexpectedValueException(sprintf('The %s attribute exceeded its maximum length.', $attribute));
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
