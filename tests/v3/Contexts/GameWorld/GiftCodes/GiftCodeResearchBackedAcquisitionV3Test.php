<?php

declare(strict_types=1);

namespace Tests\v3\Contexts\GameWorld\GiftCodes;

use App\Contexts\GameWorld\GiftCodes\Actions\RunApprovedGiftCodeSourceIngestion;
use App\Contexts\GameWorld\GiftCodes\Adapters\CenturyGamesKingshotNewsRssGiftCodeSourceAdapter;
use App\Contexts\GameWorld\GiftCodes\Enums\GiftCodeSourceSyncMode;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeProvenance;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeSourceRegistry;
use App\Contexts\GameWorld\GiftCodes\Services\GiftCodeEvidenceExtractor;
use App\Contexts\GameWorld\GiftCodes\Services\GiftCodeSourceHealthProjector;
use App\Contexts\GameWorld\GiftCodes\Services\GiftCodeSourceSyncStateRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Tests\v3\TestCase;

final class GiftCodeResearchBackedAcquisitionV3Test extends TestCase
{
    use RefreshDatabase;

    public function test_fresh_schema_uses_explicit_sync_push_and_health_state_without_legacy_source_cursor(): void
    {
        self::assertTrue(Schema::hasTable('gift_code_source_sync_states'));
        self::assertTrue(Schema::hasTable('gift_code_source_subscriptions'));
        self::assertTrue(Schema::hasTable('gift_code_source_deliveries'));
        self::assertFalse(Schema::hasColumn('gift_code_sources', 'ingestion_cursor'));
        self::assertFalse(Schema::hasColumn('gift_code_sources', 'ingestion_checkpoint'));
        self::assertTrue(Schema::hasColumn('gift_code_sources', 'accepted_observation_count'));
        self::assertTrue(Schema::hasColumn('gift_code_sources', 'quarantined_observation_count'));
        self::assertTrue(Schema::hasColumn('gift_code_sources', 'reconciliation_gap_count'));
    }

    public function test_shared_extractor_accepts_explicit_century_pattern_and_rejects_url_only_code_parameter(): void
    {
        $extractor = app(GiftCodeEvidenceExtractor::class);
        $evidence = $extractor->extract(
            "Kingshot celebration\nGift Code: THURMADNESS\nValid Until: Jan 11, 23:59 (UTC+0)",
            '2026-01-08T12:00:00Z',
        );

        self::assertCount(1, $evidence);
        self::assertSame('THURMADNESS', $evidence[0]['code']);
        self::assertSame('minute', $evidence[0]['expiry_precision']);
        self::assertSame('UTC+0', $evidence[0]['expiry_timezone']);
        self::assertStringStartsWith('2026-01-11T23:59:00', (string) $evidence[0]['claimed_expires_at']);

        self::assertSame([], $extractor->extract(
            'Visit https://store.centurygames.com/?code=NOT-A-GIFT-CODE for details.',
            '2026-01-08T12:00:00Z',
        ));
    }

    public function test_partial_quarantine_is_degraded_and_retains_usefulness_signals(): void
    {
        $source = $this->source('health-source');
        app(GiftCodeSourceHealthProjector::class)->recordCompletedRun($source, 3, 1, 1, 1);
        $source->refresh();

        self::assertSame('degraded', $source->health_status);
        self::assertSame(1, $source->accepted_observation_count);
        self::assertSame(1, $source->quarantined_observation_count);
        self::assertSame(1, $source->duplicate_observation_count);
        self::assertSame(1, $source->consecutive_quarantined_runs);
        self::assertNotNull($source->last_accepted_observation_at);
        self::assertNotNull($source->last_quarantined_observation_at);
        self::assertSame('observation_quarantined', $source->last_ingestion_failure_code);
    }

    public function test_permissioned_century_games_feed_extracts_code_and_expiry_and_uses_conditional_retrieval(): void
    {
        config()->set('game_world.gift_codes.approved_source_ingestion', true);
        $source = $this->source('century-games-news', [
            'canonical_domain' => 'centurygames.com',
            'verification_method' => 'permissioned_provider_rss',
            'adapter_key' => CenturyGamesKingshotNewsRssGiftCodeSourceAdapter::KEY,
            'provenance_policy' => [
                'auto_verify' => true,
                'provider_permission_confirmed' => true,
                'feed_path' => '/feed/',
            ],
        ]);

        $rss = <<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0" xmlns:content="http://purl.org/rss/1.0/modules/content/">
  <channel>
    <title>Century Games</title>
    <lastBuildDate>Sat, 05 Sep 2026 20:00:00 +0000</lastBuildDate>
    <item>
      <guid>century-kingshot-1</guid>
      <title>Kingshot - Thursday Madness</title>
      <link>https://www.centurygames.com/kingshot-thursday-madness/</link>
      <category>Kingshot</category>
      <pubDate>Thu, 08 Jan 2026 12:00:00 +0000</pubDate>
      <content:encoded><![CDATA[
        <p>Thursday Madness is here.</p>
        <p>Gift Code: THURMADNESS</p>
        <p>Valid Until: Jan 11, 23:59 (UTC+0)</p>
      ]]></content:encoded>
    </item>
  </channel>
</rss>
XML;

        Http::fake([
            'www.centurygames.com/feed/' => Http::response($rss, 200, [
                'Content-Type' => 'application/rss+xml; charset=UTF-8',
                'ETag' => '"century-feed-v2"',
            ]),
        ]);

        $sweep = app(RunApprovedGiftCodeSourceIngestion::class)->handle(sourceKey: $source->source_key);

        self::assertSame(1, $sweep->accepted);
        $evidence = GiftCodeProvenance::query()->where('assertion', 'available')->firstOrFail();
        self::assertSame('THURMADNESS', $evidence->giftCode->code);
        self::assertSame('minute', $evidence->expiry_precision);
        self::assertSame('UTC+0', $evidence->expiry_timezone);
        self::assertSame(CenturyGamesKingshotNewsRssGiftCodeSourceAdapter::KEY, $evidence->parser_version);

        $state = app(GiftCodeSourceSyncStateRepository::class)->get($source->refresh(), GiftCodeSourceSyncMode::Head);
        self::assertSame('"century-feed-v2"', $state->http_etag);

        Http::fake([
            'www.centurygames.com/feed/' => static function ($request) {
                self::assertSame('"century-feed-v2"', $request->header('If-None-Match')[0] ?? null);

                return Http::response('', 304, ['ETag' => '"century-feed-v2"']);
            },
        ]);
        $idle = app(RunApprovedGiftCodeSourceIngestion::class)->handle(sourceKey: $source->source_key);
        self::assertSame(0, $idle->accepted);
        self::assertSame(1, GiftCodeProvenance::query()->count());
    }

    /** @param array<string,mixed> $overrides */
    private function source(string $key, array $overrides = []): GiftCodeSourceRegistry
    {
        return GiftCodeSourceRegistry::query()->create([
            'source_key' => $key,
            'name' => $key,
            'classification' => 'official',
            'canonical_domain' => 'official.example.test',
            'is_active' => true,
            'verification_method' => 'test',
            'adapter_key' => null,
            'policy_revision' => 1,
            'provenance_policy' => [],
            'ingestion_enabled' => true,
            'push_enabled' => false,
            'head_poll_enabled' => true,
            'reconciliation_enabled' => true,
            'backfill_enabled' => true,
            'authority_promotion_enabled' => true,
            ...$overrides,
        ]);
    }
}
