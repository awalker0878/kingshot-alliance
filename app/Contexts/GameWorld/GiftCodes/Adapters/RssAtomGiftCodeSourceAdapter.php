<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\Adapters;

use App\Contexts\GameWorld\GiftCodes\Contracts\GiftCodeSourceAdapter;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeSourceRegistry;
use App\Contexts\GameWorld\GiftCodes\ValueObjects\GiftCodeIngestionObservation;
use App\Contexts\GameWorld\GiftCodes\ValueObjects\GiftCodeIngestionPage;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use UnexpectedValueException;

final class RssAtomGiftCodeSourceAdapter implements GiftCodeSourceAdapter
{
    public const KEY = 'rss-atom-v1';

    public function key(): string
    {
        return self::KEY;
    }

    public function acquire(GiftCodeSourceRegistry $source, ?string $cursor, int $limit): GiftCodeIngestionPage
    {
        if ($cursor !== null && trim($cursor) !== '') {
            throw new UnexpectedValueException('The RSS/Atom adapter does not accept a source cursor.');
        }

        $limit = max(1, min(500, $limit));
        $url = $this->feedUrl($source);
        $response = Http::withHeaders([
            'Accept' => 'application/atom+xml, application/rss+xml, application/xml, text/xml;q=0.9',
        ])
            ->timeout(max(1, min(30, (int) config('game_world.gift_codes.ingestion_timeout_seconds', 10))))
            ->withOptions(['allow_redirects' => false])
            ->get($url);

        if (! $response->successful()) {
            throw new \RuntimeException(sprintf('The approved RSS/Atom source returned HTTP %d.', $response->status()));
        }
        if (! str_contains(mb_strtolower((string) $response->header('Content-Type')), 'xml')) {
            throw new UnexpectedValueException('The approved RSS/Atom source did not return XML content.');
        }

        $body = $response->body();
        $this->assertBoundedDocument($body);
        $document = $this->parseXml($body);
        $xpath = new DOMXPath($document);
        $entries = $xpath->query('//*[local-name()="item" or local-name()="entry"]');
        if ($entries === false) {
            throw new UnexpectedValueException('The RSS/Atom source entries could not be evaluated.');
        }
        if ($entries->length > $limit) {
            throw new UnexpectedValueException('The RSS/Atom source exceeded the bounded observation limit.');
        }

        $retrievalVersion = $this->retrievalVersion($response);
        $sourceVersion = $this->feedVersion($xpath) ?? $retrievalVersion;
        $observations = [];
        foreach ($entries as $position => $entry) {
            if (! $entry instanceof DOMNode) {
                throw new UnexpectedValueException(sprintf(
                    'RSS/Atom Gift Code entry %d is not a supported XML node.',
                    $position + 1,
                ));
            }

            $code = $this->firstText(
                $xpath,
                $entry,
                './*[local-name()="gift-code" or local-name()="gift_code" or local-name()="giftCode" or local-name()="code"]',
                64,
            );
            if ($code === null) {
                throw new UnexpectedValueException(sprintf(
                    'RSS/Atom Gift Code entry %d requires an explicit code element.',
                    $position + 1,
                ));
            }

            $assertion = $this->firstText(
                $xpath,
                $entry,
                './*[local-name()="assertion"]',
                48,
            ) ?? 'available';
            $claimedExpiresAt = $this->firstText(
                $xpath,
                $entry,
                './*[local-name()="expires-at" or local-name()="expires_at" or local-name()="expiresAt" or local-name()="expiry"]',
                120,
            );
            $publishedAt = $this->firstText(
                $xpath,
                $entry,
                './*[local-name()="pubDate" or local-name()="published" or local-name()="updated"]',
                120,
            );
            $sourceUrl = $this->entryLink($xpath, $entry) ?? $url;
            $entryDocument = $document->saveXML($entry);
            if ($entryDocument === false) {
                throw new UnexpectedValueException(sprintf('RSS/Atom Gift Code entry %d could not be serialized.', $position + 1));
            }
            $fingerprint = hash('sha256', $entryDocument);

            $observations[] = new GiftCodeIngestionObservation(
                code: $code,
                assertion: $assertion,
                assertionPayload: null,
                sourceUrl: $sourceUrl,
                claimedExpiresAt: $claimedExpiresAt,
                expiryPrecision: $this->firstText(
                    $xpath,
                    $entry,
                    './*[local-name()="expiry-precision" or local-name()="expiry_precision"]',
                    32,
                ),
                expiryTimezone: $this->firstText(
                    $xpath,
                    $entry,
                    './*[local-name()="expiry-timezone" or local-name()="expiry_timezone"]',
                    80,
                ),
                publishedAt: $publishedAt,
                sourceVersion: $sourceVersion,
                retrievalVersion: $retrievalVersion,
                parserVersion: self::KEY,
                contentFingerprint: $fingerprint,
                rawEvidenceRef: $url.'#rss-atom-observation='.$fingerprint,
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
            throw new UnexpectedValueException('The RSS/Atom adapter requires a public canonical hostname.');
        }
        if (! $this->validPath($path)) {
            throw new UnexpectedValueException('The RSS/Atom adapter requires an absolute feed path on the canonical domain.');
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
            throw new UnexpectedValueException('The RSS/Atom source document exceeded the configured size bound.');
        }
        if (stripos($body, '<!DOCTYPE') !== false || stripos($body, '<!ENTITY') !== false) {
            throw new UnexpectedValueException('RSS/Atom source documents may not declare document types or entities.');
        }
    }

    private function parseXml(string $body): DOMDocument
    {
        $previous = libxml_use_internal_errors(true);
        libxml_clear_errors();
        try {
            $document = new DOMDocument;
            $loaded = $document->loadXML($body, LIBXML_NONET | LIBXML_NOBLANKS | LIBXML_COMPACT);
            if (! $loaded) {
                throw new UnexpectedValueException('The approved RSS/Atom source returned malformed XML.');
            }

            return $document;
        } finally {
            libxml_clear_errors();
            libxml_use_internal_errors($previous);
        }
    }

    private function feedVersion(DOMXPath $xpath): ?string
    {
        $nodes = $xpath->query(
            '/*[local-name()="rss"]/*[local-name()="channel"]/*[local-name()="lastBuildDate"] | /*[local-name()="feed"]/*[local-name()="updated"]',
        );
        if ($nodes === false || $nodes->length === 0) {
            return null;
        }
        $node = $nodes->item(0);
        if (! $node instanceof DOMNode) {
            return null;
        }

        return $this->optionalString($node->textContent, 120);
    }

    private function entryLink(DOMXPath $xpath, DOMNode $entry): ?string
    {
        $links = $xpath->query('./*[local-name()="link"]', $entry);
        if ($links === false) {
            return null;
        }
        foreach ($links as $link) {
            if (! $link instanceof DOMNode) {
                continue;
            }
            if ($link instanceof DOMElement) {
                $href = $this->optionalString($link->getAttribute('href'), 2048);
                if ($href !== null) {
                    return $href;
                }
            }
            $text = $this->optionalString($link->textContent, 2048);
            if ($text !== null) {
                return $text;
            }
        }

        return null;
    }

    private function firstText(
        DOMXPath $xpath,
        DOMNode $context,
        string $expression,
        int $maximum,
    ): ?string {
        $nodes = $xpath->query($expression, $context);
        if ($nodes === false || $nodes->length === 0) {
            return null;
        }
        $node = $nodes->item(0);
        if (! $node instanceof DOMNode) {
            return null;
        }

        return $this->optionalString($node->textContent, $maximum);
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

    private function optionalString(mixed $value, int $maximum): ?string
    {
        if ($value === null) {
            return null;
        }
        if (! is_string($value)) {
            throw new UnexpectedValueException('RSS/Atom Gift Code scalar fields must be strings.');
        }
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        if (mb_strlen($value) > $maximum) {
            throw new UnexpectedValueException('An RSS/Atom Gift Code scalar field exceeded its maximum length.');
        }

        return $value;
    }
}
