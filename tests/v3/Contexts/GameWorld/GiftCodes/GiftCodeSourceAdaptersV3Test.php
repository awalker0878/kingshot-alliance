<?php

declare(strict_types=1);

namespace Tests\v3\Contexts\GameWorld\GiftCodes;

use App\Contexts\GameWorld\GiftCodes\Actions\RunApprovedGiftCodeSourceIngestion;
use App\Contexts\GameWorld\GiftCodes\Adapters\CenturyGamesKingshotNewsRssGiftCodeSourceAdapter;
use App\Contexts\GameWorld\GiftCodes\Adapters\DiscordChannelGiftCodeSourceAdapter;
use App\Contexts\GameWorld\GiftCodes\Adapters\FacebookPageGiftCodeSourceAdapter;
use App\Contexts\GameWorld\GiftCodes\Adapters\InstagramMediaGiftCodeSourceAdapter;
use App\Contexts\GameWorld\GiftCodes\Adapters\JsonFeedGiftCodeSourceAdapter;
use App\Contexts\GameWorld\GiftCodes\Adapters\OfficialXGiftCodeSourceAdapter;
use App\Contexts\GameWorld\GiftCodes\Adapters\RedditSubredditGiftCodeSourceAdapter;
use App\Contexts\GameWorld\GiftCodes\Adapters\RssAtomGiftCodeSourceAdapter;
use App\Contexts\GameWorld\GiftCodes\Adapters\StructuredHtmlGiftCodeSourceAdapter;
use App\Contexts\GameWorld\GiftCodes\Adapters\YouTubeChannelGiftCodeSourceAdapter;
use App\Contexts\GameWorld\GiftCodes\Enums\GiftCodeStatus;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCode;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeProvenance;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeSourceRegistry;
use App\Contexts\GameWorld\GiftCodes\Services\GiftCodeSourceAdapterRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\v3\TestCase;
use UnexpectedValueException;

final class GiftCodeSourceAdaptersV3Test extends TestCase
{
    use RefreshDatabase;

    public function test_registry_exposes_every_candidate_pull_adapter(): void
    {
        self::assertSame([
            JsonFeedGiftCodeSourceAdapter::KEY,
            RssAtomGiftCodeSourceAdapter::KEY,
            StructuredHtmlGiftCodeSourceAdapter::KEY,
            OfficialXGiftCodeSourceAdapter::KEY,
            CenturyGamesKingshotNewsRssGiftCodeSourceAdapter::KEY,
            DiscordChannelGiftCodeSourceAdapter::KEY,
            YouTubeChannelGiftCodeSourceAdapter::KEY,
            RedditSubredditGiftCodeSourceAdapter::KEY,
            FacebookPageGiftCodeSourceAdapter::KEY,
            InstagramMediaGiftCodeSourceAdapter::KEY,
        ], app(GiftCodeSourceAdapterRegistry::class)->keys());
    }

    public function test_rss_adapter_parses_explicit_bounded_gift_code_entries(): void
    {
        Http::fake([
            'https://publisher.example.test/gift-codes.xml' => Http::response(<<<'XML'
                <?xml version="1.0" encoding="UTF-8"?>
                <rss version="2.0" xmlns:ks="https://kingshot.app/gift-codes">
                    <channel>
                        <title>Gift Codes</title>
                        <lastBuildDate>Sat, 05 Sep 2026 09:00:00 GMT</lastBuildDate>
                        <item>
                            <title>New code</title>
                            <link>https://publisher.example.test/posts/code-one</link>
                            <pubDate>Sat, 05 Sep 2026 08:30:00 GMT</pubDate>
                            <ks:gift-code>RSS-CODE-ONE</ks:gift-code>
                            <ks:assertion>available</ks:assertion>
                            <ks:expires-at>2026-09-10T00:00:00Z</ks:expires-at>
                            <ks:expiry-precision>day</ks:expiry-precision>
                        </item>
                    </channel>
                </rss>
                XML, 200, [
                'Content-Type' => 'application/rss+xml; charset=UTF-8',
                'ETag' => '"rss-v1"',
            ]),
        ]);

        $page = app(RssAtomGiftCodeSourceAdapter::class)->acquire(
            $this->source('/gift-codes.xml'),
            null,
            10,
        );

        self::assertCount(1, $page->observations);
        $observation = $page->observations[0];
        self::assertSame('RSS-CODE-ONE', $observation->code);
        self::assertSame('available', $observation->assertion);
        self::assertSame('https://publisher.example.test/posts/code-one', $observation->sourceUrl);
        self::assertSame('2026-09-10T00:00:00Z', $observation->claimedExpiresAt);
        self::assertSame('day', $observation->expiryPrecision);
        self::assertSame(RssAtomGiftCodeSourceAdapter::KEY, $observation->parserVersion);
        self::assertTrue($observation->verificationPassed);
    }

    public function test_atom_adapter_supports_atom_entry_links_and_explicit_code_elements(): void
    {
        Http::fake([
            'https://publisher.example.test/atom.xml' => Http::response(<<<'XML'
                <?xml version="1.0" encoding="UTF-8"?>
                <feed xmlns="http://www.w3.org/2005/Atom" xmlns:ks="https://kingshot.app/gift-codes">
                    <title>Gift Codes</title>
                    <updated>2026-09-05T09:00:00Z</updated>
                    <entry>
                        <title>Atom code</title>
                        <link href="https://publisher.example.test/posts/atom-code" />
                        <published>2026-09-05T08:30:00Z</published>
                        <ks:code>ATOM-CODE-ONE</ks:code>
                    </entry>
                </feed>
                XML, 200, ['Content-Type' => 'application/atom+xml']),
        ]);

        $page = app(RssAtomGiftCodeSourceAdapter::class)->acquire(
            $this->source('/atom.xml'),
            null,
            10,
        );

        self::assertCount(1, $page->observations);
        self::assertSame('ATOM-CODE-ONE', $page->observations[0]->code);
        self::assertSame('available', $page->observations[0]->assertion);
        self::assertSame('https://publisher.example.test/posts/atom-code', $page->observations[0]->sourceUrl);
    }

    public function test_rss_adapter_ignores_code_elements_nested_inside_content_blocks(): void
    {
        Http::fake([
            'https://publisher.example.test/atom.xml' => Http::response(<<<'XML'
                <?xml version="1.0" encoding="UTF-8"?>
                <feed xmlns="http://www.w3.org/2005/Atom" xmlns:xhtml="http://www.w3.org/1999/xhtml">
                    <entry>
                        <title>Article with a prose code element</title>
                        <content type="xhtml">
                            <xhtml:div><xhtml:code>NOT-MACHINE-READABLE</xhtml:code></xhtml:div>
                        </content>
                    </entry>
                </feed>
                XML, 200, ['Content-Type' => 'application/atom+xml']),
        ]);

        $this->expectException(UnexpectedValueException::class);
        app(RssAtomGiftCodeSourceAdapter::class)->acquire($this->source('/atom.xml'), null, 10);
    }

    public function test_structured_html_adapter_requires_explicit_machine_readable_gift_code_markup(): void
    {
        Http::fake([
            'https://publisher.example.test/gift-codes' => Http::response(<<<'HTML'
                <!doctype html>
                <html lang="en">
                  <body>
                    <article
                      data-gift-code="HTML-CODE-ONE"
                      data-gift-code-assertion="reward"
                      data-gift-code-source-url="https://publisher.example.test/gift-codes#html-code-one"
                      data-gift-code-payload="{&quot;rewards&quot;:[{&quot;kind&quot;:&quot;gold&quot;,&quot;amount&quot;:500}]}"
                      data-gift-code-expires-at="2026-09-12T00:00:00Z"
                      data-gift-code-expiry-precision="day"
                      data-gift-code-published-at="2026-09-05T09:00:00Z"
                    >HTML-CODE-ONE</article>
                  </body>
                </html>
                HTML, 200, [
                'Content-Type' => 'text/html; charset=UTF-8',
                'Last-Modified' => 'Sat, 05 Sep 2026 09:00:00 GMT',
            ]),
        ]);

        $page = app(StructuredHtmlGiftCodeSourceAdapter::class)->acquire(
            $this->source('/gift-codes'),
            null,
            10,
        );

        self::assertCount(1, $page->observations);
        $observation = $page->observations[0];
        self::assertSame('HTML-CODE-ONE', $observation->code);
        self::assertSame('reward', $observation->assertion);
        self::assertSame(500, $observation->assertionPayload['rewards'][0]['amount'] ?? null);
        self::assertSame('https://publisher.example.test/gift-codes#html-code-one', $observation->sourceUrl);
        self::assertSame(StructuredHtmlGiftCodeSourceAdapter::KEY, $observation->parserVersion);
        self::assertTrue($observation->verificationPassed);
    }

    public function test_rss_adapter_rejects_document_types_and_entities(): void
    {
        Http::fake([
            'https://publisher.example.test/gift-codes.xml' => Http::response(
                '<!DOCTYPE rss [<!ENTITY xxe SYSTEM "file:///etc/passwd">]><rss><channel /></rss>',
                200,
                ['Content-Type' => 'application/rss+xml'],
            ),
        ]);

        $this->expectException(UnexpectedValueException::class);
        app(RssAtomGiftCodeSourceAdapter::class)->acquire($this->source('/gift-codes.xml'), null, 10);
    }

    public function test_document_adapters_reject_sources_that_exceed_the_observation_bound(): void
    {
        Http::fake([
            'https://publisher.example.test/gift-codes.xml' => Http::response(
                '<rss><channel><item><code>ONE</code></item><item><code>TWO</code></item></channel></rss>',
                200,
                ['Content-Type' => 'application/rss+xml'],
            ),
        ]);

        $this->expectException(UnexpectedValueException::class);
        app(RssAtomGiftCodeSourceAdapter::class)->acquire($this->source('/gift-codes.xml'), null, 1);
    }

    public function test_rss_candidate_adapter_enters_the_canonical_approved_source_pipeline(): void
    {
        config()->set('game_world.gift_codes.approved_source_ingestion', true);
        $source = $this->registeredSource('rss-candidate', RssAtomGiftCodeSourceAdapter::KEY, '/gift-codes.xml');
        Http::fake([
            'https://publisher.example.test/gift-codes.xml' => Http::response(<<<'XML'
                <?xml version="1.0" encoding="UTF-8"?>
                <rss version="2.0" xmlns:ks="https://kingshot.app/gift-codes">
                    <channel>
                        <item>
                            <link>https://publisher.example.test/posts/rss-e2e</link>
                            <ks:gift-code>RSS-E2E-CODE</ks:gift-code>
                        </item>
                    </channel>
                </rss>
                XML, 200, ['Content-Type' => 'application/rss+xml']),
        ]);

        $sweep = app(RunApprovedGiftCodeSourceIngestion::class)->handle(sourceKey: $source->source_key);

        self::assertSame(1, $sweep->sourceCount);
        self::assertSame(1, $sweep->examined);
        self::assertSame(1, $sweep->accepted);
        self::assertSame(0, $sweep->quarantined);
        self::assertSame(0, $sweep->failedSources);
        $giftCode = GiftCode::query()->where('normalized_code', 'RSS-E2E-CODE')->firstOrFail();
        self::assertSame(GiftCodeStatus::Valid, $giftCode->status);
        $provenance = GiftCodeProvenance::query()->where('gift_code_id', (string) $giftCode->id)->firstOrFail();
        self::assertSame(RssAtomGiftCodeSourceAdapter::KEY, $provenance->parser_version);
    }

    public function test_structured_html_candidate_adapter_enters_the_canonical_approved_source_pipeline(): void
    {
        config()->set('game_world.gift_codes.approved_source_ingestion', true);
        $source = $this->registeredSource('html-candidate', StructuredHtmlGiftCodeSourceAdapter::KEY, '/gift-codes');
        Http::fake([
            'https://publisher.example.test/gift-codes' => Http::response(<<<'HTML'
                <!doctype html>
                <html lang="en">
                  <body>
                    <article data-gift-code="HTML-E2E-CODE">HTML-E2E-CODE</article>
                  </body>
                </html>
                HTML, 200, ['Content-Type' => 'text/html; charset=UTF-8']),
        ]);

        $sweep = app(RunApprovedGiftCodeSourceIngestion::class)->handle(sourceKey: $source->source_key);

        self::assertSame(1, $sweep->sourceCount);
        self::assertSame(1, $sweep->examined);
        self::assertSame(1, $sweep->accepted);
        self::assertSame(0, $sweep->quarantined);
        self::assertSame(0, $sweep->failedSources);
        $giftCode = GiftCode::query()->where('normalized_code', 'HTML-E2E-CODE')->firstOrFail();
        self::assertSame(GiftCodeStatus::Valid, $giftCode->status);
        $provenance = GiftCodeProvenance::query()->where('gift_code_id', (string) $giftCode->id)->firstOrFail();
        self::assertSame(StructuredHtmlGiftCodeSourceAdapter::KEY, $provenance->parser_version);
    }

    private function source(string $feedPath): GiftCodeSourceRegistry
    {
        return new GiftCodeSourceRegistry([
            'source_key' => 'candidate-source',
            'name' => 'Candidate source',
            'classification' => 'official',
            'canonical_domain' => 'publisher.example.test',
            'verification_method' => 'approved_source',
            'adapter_key' => 'test',
            'provenance_policy' => ['feed_path' => $feedPath, 'auto_verify' => true],
            'ingestion_enabled' => true,
            'is_active' => true,
            'policy_revision' => 1,
        ]);
    }

    private function registeredSource(string $key, string $adapterKey, string $feedPath): GiftCodeSourceRegistry
    {
        return GiftCodeSourceRegistry::query()->create([
            'source_key' => $key,
            'name' => ucfirst(str_replace('-', ' ', $key)),
            'classification' => 'official',
            'canonical_domain' => 'publisher.example.test',
            'verification_method' => 'approved_source',
            'adapter_key' => $adapterKey,
            'provenance_policy' => ['feed_path' => $feedPath, 'auto_verify' => true],
            'ingestion_enabled' => true,
            'is_active' => true,
            'policy_revision' => 1,
        ]);
    }
}
