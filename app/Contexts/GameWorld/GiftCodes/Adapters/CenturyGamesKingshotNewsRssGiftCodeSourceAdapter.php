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

final class CenturyGamesKingshotNewsRssGiftCodeSourceAdapter implements GiftCodeSourceAdapter
{
    public const KEY = 'century-games-kingshot-news-rss-v1';

    public function key(): string
    {
        return self::KEY;
    }

    public function acquire(GiftCodeSourceRegistry $source, ?string $cursor, int $limit): GiftCodeIngestionPage
    {
        if ($cursor !== null && trim($cursor) !== '') {
            throw new UnexpectedValueException('The Century Games Kingshot news adapter does not accept a source cursor.');
        }

        $policy = $source->provenance_policy ?? [];
        if (($policy['provider_permission_confirmed'] ?? false) !== true) {
            throw new UnexpectedValueException('Century Games Kingshot news ingestion requires confirmed provider permission.');
        }
        $category = $this->requiredPolicyString($policy, 'gift_code_category', 120);
        $feedPath = $this->requiredPolicyString($policy, 'feed_path', 2048);
        if (! $this->validPath($feedPath)) {
            throw new UnexpectedValueException('The Century Games Kingshot news adapter requires an absolute feed path.');
        }
        if (mb_strtolower(rtrim(trim((string) $source->canonical_domain), '.')) !== 'centurygames.com') {
            throw new UnexpectedValueException('The Century Games Kingshot news adapter requires centurygames.com as the canonical source domain.');
        }

        $limit = max(1, min(500, $limit));
        $url = 'https://www.centurygames.com'.$feedPath;
        $response = Http::withHeaders([
            'Accept' => 'application/rss+xml, application/atom+xml, application/xml, text/xml;q=0.9',
        ])
            ->timeout(max(1, min(30, (int) config('game_world.gift_codes.ingestion_timeout_seconds', 10))))
            ->withOptions(['allow_redirects' => false])
            ->get($url);

        if (! $response->successful()) {
            throw new \RuntimeException(sprintf('The Century Games Kingshot news feed returned HTTP %d.', $response->status()));
        }
        if (! str_contains(mb_strtolower((string) $response->header('Content-Type')), 'xml')) {
            throw new UnexpectedValueException('The Century Games Kingshot news feed did not return XML content.');
        }

        $body = $response->body();
        $this->assertBoundedDocument($body);
        $document = $this->parseXml($body);
        $xpath = new DOMXPath($document);
        $entries = $xpath->query('//*[local-name()="item" or local-name()="entry"]');
        if ($entries === false) {
            throw new UnexpectedValueException('The Century Games Kingshot news entries could not be evaluated.');
        }
        if ($entries->length > $limit) {
            throw new UnexpectedValueException('The Century Games Kingshot news feed exceeded the bounded observation limit.');
        }

        $retrievalVersion = $this->retrievalVersion($response);
        $sourceVersion = $this->feedVersion($xpath) ?? $retrievalVersion;
        $observations = [];
        foreach ($entries as $position => $entry) {
            if (! $entry instanceof DOMNode) {
                throw new UnexpectedValueException(sprintf('Century Games news entry %d is not a supported XML node.', $position + 1));
            }
            if (! $this->hasCategory($xpath, $entry, $category)) {
                continue;
            }

            $code = $this->explicitCode($xpath, $entry);
            if ($code === null) {
                throw new UnexpectedValueException(sprintf(
                    'Century Games Kingshot Gift Code entry %d matched the configured category but not the explicit Gift Code label contract.',
                    $position + 1,
                ));
            }

            $sourceUrl = $this->entryLink($xpath, $entry) ?? $url;
            $this->assertCenturyGamesUrl($sourceUrl);
            $entryDocument = $document->saveXML($entry);
            if ($entryDocument === false) {
                throw new UnexpectedValueException(sprintf('Century Games news entry %d could not be serialized.', $position + 1));
            }
            $fingerprint = hash('sha256', $entryDocument);

            $observations[] = new GiftCodeIngestionObservation(
                code: $code,
                assertion: 'available',
                assertionPayload: null,
                sourceUrl: $sourceUrl,
                claimedExpiresAt: $this->firstText(
                    $xpath,
                    $entry,
                    './*[local-name()="expires-at" or local-name()="expires_at" or local-name()="expiresAt"]',
                    120,
                ),
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
                publishedAt: $this->firstText(
                    $xpath,
                    $entry,
                    './*[local-name()="pubDate" or local-name()="published" or local-name()="updated"]',
                    120,
                ),
                sourceVersion: $sourceVersion,
                retrievalVersion: $retrievalVersion,
                parserVersion: self::KEY,
                contentFingerprint: $fingerprint,
                rawEvidenceRef: $sourceUrl.'#century-games-gift-code='.rawurlencode($code),
                verificationPassed: true,
            );
        }

        return new GiftCodeIngestionPage($observations, null);
    }

    private function explicitCode(DOMXPath $xpath, DOMNode $entry): ?string
    {
        foreach ([
            './*[local-name()="title"]',
            './*[local-name()="description" or local-name()="summary"]',
        ] as $expression) {
            $text = $this->firstText($xpath, $entry, $expression, 10_000);
            if ($text === null) {
                continue;
            }
            if (preg_match(
                '/^\s*(?:Kingshot\s*[-–—:]\s*)?(?:gift\s*code|redeem\s*code)\s*[:：-]\s*([A-Za-z0-9_-]{3,64})\s*[.!]?\s*$/iu',
                $text,
                $matches,
            ) === 1) {
                return $matches[1];
            }
        }

        return null;
    }

    private function hasCategory(DOMXPath $xpath, DOMNode $entry, string $expected): bool
    {
        $nodes = $xpath->query('./*[local-name()="category"]', $entry);
        if ($nodes === false) {
            return false;
        }
        $expected = mb_strtolower(trim($expected));
        foreach ($nodes as $node) {
            if (! $node instanceof DOMNode) {
                continue;
            }
            $value = $node instanceof DOMElement
                ? ($this->optionalString($node->getAttribute('term'), 120) ?? $this->optionalString($node->textContent, 120))
                : $this->optionalString($node->textContent, 120);
            if ($value !== null && mb_strtolower($value) === $expected) {
                return true;
            }
        }

        return false;
    }

    private function assertCenturyGamesUrl(string $url): void
    {
        $scheme = parse_url($url, PHP_URL_SCHEME);
        $host = parse_url($url, PHP_URL_HOST);
        $host = is_string($host) ? mb_strtolower(rtrim($host, '.')) : null;
        if ($scheme !== 'https'
            || $host === null
            || ($host !== 'centurygames.com' && ! str_ends_with($host, '.centurygames.com'))) {
            throw new UnexpectedValueException('Century Games Gift Code evidence links must remain on centurygames.com over HTTPS.');
        }
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
            throw new UnexpectedValueException('The Century Games Kingshot news document exceeded the configured size bound.');
        }
        if (stripos($body, '<!DOCTYPE') !== false || stripos($body, '<!ENTITY') !== false) {
            throw new UnexpectedValueException('Century Games Kingshot news documents may not declare document types or entities.');
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
                throw new UnexpectedValueException('The Century Games Kingshot news feed returned malformed XML.');
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

        return $node instanceof DOMNode ? $this->optionalString($node->textContent, 120) : null;
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

    private function firstText(DOMXPath $xpath, DOMNode $context, string $expression, int $maximum): ?string
    {
        $nodes = $xpath->query($expression, $context);
        if ($nodes === false || $nodes->length === 0) {
            return null;
        }
        $node = $nodes->item(0);

        return $node instanceof DOMNode ? $this->optionalString($node->textContent, $maximum) : null;
    }

    /** @param array<string, mixed> $policy */
    private function requiredPolicyString(array $policy, string $key, int $maximum): string
    {
        $value = $this->optionalString($policy[$key] ?? null, $maximum);
        if ($value === null) {
            throw new UnexpectedValueException(sprintf('The Century Games Kingshot news adapter requires source policy %s.', $key));
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

    private function optionalString(mixed $value, int $maximum): ?string
    {
        if ($value === null) {
            return null;
        }
        if (! is_string($value)) {
            throw new UnexpectedValueException('Century Games Kingshot news scalar fields must be strings.');
        }
        $value = trim($value);
        if ($value === '') {
            return null;
        }
        if (mb_strlen($value) > $maximum) {
            throw new UnexpectedValueException('A Century Games Kingshot news scalar field exceeded its maximum length.');
        }

        return $value;
    }
}
