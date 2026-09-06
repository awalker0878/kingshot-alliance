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
use App\Contexts\GameWorld\GiftCodes\Enums\GiftCodeSourceSyncMode;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeProvenance;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeSourceRegistry;
use App\Contexts\GameWorld\GiftCodes\Services\GiftCodeSourceAdapterRegistry;
use App\Contexts\GameWorld\GiftCodes\Services\GiftCodeSourceSyncStateRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\v3\TestCase;

final class GiftCodePullAdapterConformanceV3Test extends TestCase
{
    use RefreshDatabase;

    public function test_every_production_pull_adapter_is_uniquely_registered_and_resolvable(): void
    {
        $expected = [
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
        ];
        $registry = app(GiftCodeSourceAdapterRegistry::class);

        self::assertSame($expected, $registry->keys());
        self::assertSame($expected, array_values(array_unique($registry->keys())));
        foreach ($expected as $key) {
            self::assertSame($key, $registry->find($key)?->key());
        }
    }

    public function test_failed_pull_page_never_advances_committed_source_state(): void
    {
        config()->set('game_world.gift_codes.approved_source_ingestion', true);
        $source = $this->jsonSource('conformance-failure');
        $states = app(GiftCodeSourceSyncStateRepository::class);
        $state = $states->get($source, GiftCodeSourceSyncMode::Head);
        $states->advance($state, [
            'committed_high_water' => 'stable-cursor',
            'active_page_token' => null,
        ]);
        $version = $state->fresh()->version;
        Http::fake([
            'https://publisher.example.test/gift-codes.json*' => Http::response(
                ['unexpected' => []],
                200,
                ['Content-Type' => 'application/json'],
            ),
        ]);

        $result = app(RunApprovedGiftCodeSourceIngestion::class)->handle(sourceKey: $source->source_key);

        self::assertSame(1, $result->failedSources);
        $state->refresh();
        self::assertSame('stable-cursor', $state->committed_high_water);
        self::assertNull($state->active_page_token);
        self::assertSame($version, $state->version);
    }

    public function test_repeated_identical_pull_page_is_idempotent_at_canonical_provenance_boundary(): void
    {
        config()->set('game_world.gift_codes.approved_source_ingestion', true);
        $source = $this->jsonSource('conformance-idempotency');
        Http::fake([
            'https://publisher.example.test/gift-codes.json*' => Http::response([
                'version' => 'fixture-v1',
                'items' => [[
                    'code' => 'CONFORMANCE26',
                    'assertion' => 'available',
                    'source_url' => 'https://publisher.example.test/posts/conformance26',
                    'published_at' => '2026-09-06T12:00:00Z',
                ]],
                'next_cursor' => null,
            ], 200, ['Content-Type' => 'application/json']),
        ]);

        $first = app(RunApprovedGiftCodeSourceIngestion::class)->handle(sourceKey: $source->source_key);
        $second = app(RunApprovedGiftCodeSourceIngestion::class)->handle(sourceKey: $source->source_key);

        self::assertSame(1, $first->accepted);
        self::assertSame(0, $first->duplicates);
        self::assertSame(0, $second->accepted);
        self::assertSame(1, $second->duplicates);
        self::assertSame(1, GiftCodeProvenance::query()->count());
    }

    public function test_revoked_source_is_never_retrieved_by_pull_runner(): void
    {
        config()->set('game_world.gift_codes.approved_source_ingestion', true);
        $source = $this->jsonSource('conformance-revoked');
        $source->forceFill(['revoked_at' => now()])->save();
        Http::fake();

        $result = app(RunApprovedGiftCodeSourceIngestion::class)->handle(sourceKey: $source->source_key);

        self::assertSame(0, $result->sourceCount);
        Http::assertNothingSent();
    }

    private function jsonSource(string $key): GiftCodeSourceRegistry
    {
        return GiftCodeSourceRegistry::query()->create([
            'source_key' => $key,
            'name' => $key,
            'classification' => 'official',
            'canonical_domain' => 'publisher.example.test',
            'verification_method' => 'approved_json_feed',
            'adapter_key' => JsonFeedGiftCodeSourceAdapter::KEY,
            'provenance_policy' => [
                'feed_path' => '/gift-codes.json',
                'provider_contract_confirmed' => true,
                'auto_verify' => true,
            ],
            'is_active' => true,
            'ingestion_enabled' => true,
            'push_enabled' => false,
            'head_poll_enabled' => true,
            'reconciliation_enabled' => true,
            'backfill_enabled' => true,
            'authority_promotion_enabled' => true,
            'activation_status' => 'enabled',
            'health_status' => 'pending',
            'policy_revision' => 1,
        ]);
    }
}
