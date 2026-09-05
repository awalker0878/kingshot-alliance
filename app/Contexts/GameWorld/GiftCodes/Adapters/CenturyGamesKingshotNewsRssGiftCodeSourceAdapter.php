<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\Adapters;

use App\Contexts\GameWorld\GiftCodes\Adapters\Concerns\HandlesGiftCodeProviderResponses;
use App\Contexts\GameWorld\GiftCodes\Contracts\GiftCodeSourceAdapter;
use App\Contexts\GameWorld\GiftCodes\Enums\GiftCodeSourceSyncMode;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeSourceRegistry;
use App\Contexts\GameWorld\GiftCodes\Services\GiftCodeConditionalHttpHeaders;
use App\Contexts\GameWorld\GiftCodes\Services\GiftCodeProviderPublication;
use App\Contexts\GameWorld\GiftCodes\Services\GiftCodeProviderPublicationExtractor;
use App\Contexts\GameWorld\GiftCodes\Services\GiftCodeSourceSyncStateRepository;
use App\Contexts\GameWorld\GiftCodes\ValueObjects\GiftCodeIngestionPage;
use App\Contexts\GameWorld\GiftCodes\ValueObjects\GiftCodeSourceCheckpoint;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use Illuminate\Support\Facades\Http;
use UnexpectedValueException;

final class CenturyGamesKingshotNewsRssGiftCodeSourceAdapter implements GiftCodeSourceAdapter
{
    use HandlesGiftCodeProviderResponses;

    public const KEY = 'century-games-kingshot-news-v2';

    public function __construct(
        private GiftCodeProviderPublicationExtractor $publications,
        private GiftCodeSourceSyncStateRepository $syncStates,
        private GiftCodeConditionalHttpHeaders $conditionalHeaders,
    ) {}

    public function key(): string
    {
        return self::KEY;
    }

    public function acquire(GiftCodeSourceRegistry $source, ?string $cursor, int $limit): GiftCodeIngestionPage
    {
        if ($cursor !== null && trim($cursor) !== '') {
            throw new UnexpectedValueException('The Century Games Kingshot news adapter does not accept a provider page cursor.');
        }

        $policy = $source->provenance_policy ?? [];
        if (($policy['provider_permission_confirmed'] ?? false) !== true) {
            throw new UnexpectedValueException('Century Games Kingshot news ingestion requires confirmed provider permission.');
        }
        $feedPath = $this->requiredPolicyString($policy, 'feed_path', 2048);
        if (! $this->validPath($feedPath)) {
            throw new UnexpectedValueException('The Century Games Kingshot news adapter requires an absolute feed path.');
        }
        if (mb_strtolower(rtrim(trim((string) $source->canonical_domain), '.')) !== 'centurygames.com') {
            throw new UnexpectedValueException('The Century Games Kingshot news adapter requires centurygames.com as the canonical source domain.');
        }

        $limit = max(1, min(500, $limit));
        $url = 'https://www.centurygames.com'.$feedPath;
        $state = $this->syncStates->get($source, GiftCodeSourceSyncMode::Head);
        $response = Http::withHeaders([
            'Accept' => 'application/rss+xml, application/atom+xml, application/xml, text/xml;q=0.9',
            ...$this->conditionalHeaders->forState($state),
        ])
            ->timeout(max(1, min(30, (int) config('game_world.gift_codes.ingestion_timeout_seconds', 10))))
            ->withOptions(['allow_redirects' => false])
            ->get($url);

        $providerRequestId = $this->giftCodeProviderRequestId($response);
        if ($response->status() === 304) {
            $retrievalVersion = $state->http_etag !== null
                ? 'ETag:'.$state->http_etag
                : ($state->http_last_modified !== null ? 'Last-Modified:'.$state->http_last_modified : null);

            return new GiftCodeIngestionPage(
                observations: [],
                nextCursor: null,
                retrievalVersion: $retrievalVersion,
                providerRequestId: $providerRequestId,
                rateLimit: $this->giftCodeRateLimit($response),
                checkpoint: new GiftCodeSourceCheckpoint(
                    cursor: null,
                    retrievalVersion: $retrievalVersion,
                    providerRequestId: $providerRequestId,
                    providerState: [
                        'feed_url' => $url,
                        'not_modified' => true,
                        'http_etag' => $state->http_etag,
                        'http_last_modified' => $state->http_last_modified,
                    ],
                ),
            );
        }

        $this->assertGiftCodeProviderSuccess($response, 'The Century Games Kingshot news feed');
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
        if ($entries->length > 500) {
            throw new UnexpectedValueException('The Century Games Kingshot news feed exceeded the bounded publication limit.');
        }

        $retrievalVersion = $this->giftCodeRetrievalVersion($response);
        $observations = [];
        $itemIds = [];
        $latestPublicationId = null;
        foreach ($entries as $position => $entry) {
            if (! $entry instanceof DOMNode) {
                throw new UnexpectedValueException(sprintf('Century Games news entry %d is not a supported XML node.', $position + 1));
            }

            $sourceUrl = $this->entryLink($xpath, $entry) ?? $url;
            $this->assertCenturyGamesUrl($sourceUrl);
            if (! $this->isKingshotEntry($xpath, $entry, $sourceUrl)) {
                continue;
            }

            $publicationId = $this->publicationId($xpath, $entry, $sourceUrl);
            $itemIds[] = $publicationId;
            $latestPublicationId ??= $publicationId;
            $publishedAt = $this->firstText(
                $xpath,
                $entry,
                './*[local-name()="pubDate" or local-name()="published" or local-name()="updated"]',
                120,
            );
            $content = $this->publicationContent($xpath, $entry);
            if ($content === null) {
                continue;
            }

            foreach ($this->publications->observations(
                new GiftCodeProviderPublication(
                    provider: 'century-games',
                    providerItemId: $publicationId,
                    sourceUrl: $sourceUrl,
                    content: $content,
                    publishedAt: $publishedAt,
                    retrievalVersion: $retrievalVersion,
                ),
                self::KEY,
                true,
            ) as $observation) {
                $observations[] = $observation;
                if (count($observations) >= $limit) {
                    break 2;
                }
            }
        }

        $etag = trim((string) $response->header('ETag'));
        $lastModified = trim((string) $response->header('Last-Modified'));

        return new GiftCodeIngestionPage(
            observations: $observations,
            nextCursor: null,
            retrievalVersion: $retrievalVersion,
            providerRequestId: $providerRequestId,
            rateLimit: $this->giftCodeRateLimit($response),
            checkpoint: new GiftCodeSourceCheckpoint(
                cursor: null,
                retrievalVersion: $retrievalVersion,
                providerRequestId: $providerRequestId,
                providerState: [
                    'feed_url' => $url,
                    'source_version' => $this->feedVersion($xpath) ?? $retrievalVersion,
                    'latest_publication_id' => $latestPublicationId,
                    'item_ids' => $itemIds,
                    'http_etag' => $etag !== '' ? $etag : null,
                    'http_last_modified' => $lastModified !== '' ? $lastModified : null,
                    'not_modified' => false,
                ],
            ),
        );
    }

    private function isKingshotEntry(DOMXPath $xpath, DOMNode $entry, string $sourceUrl): bool
    {
        $path = mb_strtolower((string) parse_url($sourceUrl, PHP_URL_PATH));
        if (str_contains($path, '/kingshot-') || str_contains($path, '/games/kingshot/')) {
            return true;
        }

        $title = $this->firstText($xpath, $entry, './*[local-name()="title"]', 1000);
        if ($title !== null && preg_match('/\bkingshot\b/iu', $title) === 1) {
            return true;
        }

        $categories = $xpath->query('./*[local-name()="category"]', $entry);
        if ($categories === false) {
            return false;
        }
        foreach ($categories as $category) {
            if (! $category instanceof DOMNode) {
                continue;
            }
            $value = $category instanceof DOMElement
                ? ($this->optionalString($category->getAttribute('term'), 120) ?? $this->optionalString($category->textContent, 120))
                : $this->optionalString($category->textContent, 120);
            if ($value !== null && preg_match('/\bkingshot\b/iu', $value) === 1) {
                return true;
            }
        }

        return false;
    }

    private function publicationContent(DOMXPath $xpath, DOMNode $entry): ?string
    {
        $parts = [];
        foreach ([
            './*[local-name()="title"]',
            './*[local-name()="description" or local-name()="summary"]',
            './*[local-name()="encoded" or local-name()="content"]',
        ] as $expression) {
            $nodes = $xpath->query($expression, $entry);
            if ($nodes === false) {
                continue;
            }
            foreach ($nodes as $node) {
                if (! $node instanceof DOMNode) {
                    continue;
                }
                $value = $this->optionalString($node->textContent, 100_000);
                if ($value !== null) {
                    $parts[] = $value;
                }
            }
        }

        if ($parts === []) {
            return null;
        }

        return implode("\n", $parts);
    }

    private function publicationId(DOMXPath $xpath, DOMNode $entry, string $sourceUrl): string
    {
        $id = $this->firstText($xpath, $entry, './*[local-name()="guid" or local-name()="id"]', 2048);

        return $id ?? $sourceUrl;
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

    /** @param array<string,mixed> $policy */
    private function requiredPolicyString(array $policy, string $key, int $maximum): string
    {
        $value = $this->optionalString($policy[$key] ?? null, $maximum);
        if ($value === null) {
            throw new UnexpectedValueException(sprintf('The Century Games Kingshot news adapter requires source policy %s.', $key));
        }

        return $value;
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
