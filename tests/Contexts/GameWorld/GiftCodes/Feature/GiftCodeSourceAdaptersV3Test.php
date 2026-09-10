<?php

declare(strict_types=1);

namespace Tests\Contexts\GameWorld\GiftCodes\Feature;

use App\Contexts\GameWorld\GiftCodes\Actions\RunApprovedGiftCodeSourceIngestion;
use App\Contexts\GameWorld\GiftCodes\Adapters\RssAtomGiftCodeSourceAdapter;
use App\Contexts\GameWorld\GiftCodes\Adapters\StructuredHtmlGiftCodeSourceAdapter;
use App\Contexts\GameWorld\GiftCodes\Enums\GiftCodeStatus;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCode;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeProvenance;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeSourceRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

final class GiftCodeSourceAdaptersV3Test extends TestCase
{
    use RefreshDatabase;

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
