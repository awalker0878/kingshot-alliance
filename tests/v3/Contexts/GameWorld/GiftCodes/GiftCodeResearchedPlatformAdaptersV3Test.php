<?php

declare(strict_types=1);

namespace Tests\v3\Contexts\GameWorld\GiftCodes;

use App\Contexts\Accounts\Identity\Models\User;
use App\Contexts\Accounts\Identity\Queries\AccountIdentityQuery;
use App\Contexts\Accounts\Identity\ValueObjects\AccountIdentity;
use App\Contexts\GameWorld\GiftCodes\Actions\ManageGiftCodeSourceRegistry;
use App\Contexts\GameWorld\GiftCodes\Actions\RecordRegisteredGiftCodeEvidence;
use App\Contexts\GameWorld\GiftCodes\Adapters\DiscordChannelGiftCodeSourceAdapter;
use App\Contexts\GameWorld\GiftCodes\Adapters\FacebookPageGiftCodeSourceAdapter;
use App\Contexts\GameWorld\GiftCodes\Adapters\InstagramMediaGiftCodeSourceAdapter;
use App\Contexts\GameWorld\GiftCodes\Adapters\JsonFeedGiftCodeSourceAdapter;
use App\Contexts\GameWorld\GiftCodes\Adapters\RedditSubredditGiftCodeSourceAdapter;
use App\Contexts\GameWorld\GiftCodes\Adapters\RssAtomGiftCodeSourceAdapter;
use App\Contexts\GameWorld\GiftCodes\Adapters\StructuredHtmlGiftCodeSourceAdapter;
use App\Contexts\GameWorld\GiftCodes\Adapters\YouTubeChannelGiftCodeSourceAdapter;
use App\Contexts\GameWorld\GiftCodes\Enums\GiftCodeStatus;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCode;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeProvenance;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeSourceRegistry;
use App\Contexts\GameWorld\GiftCodes\Services\GiftCodeResearchedSourceCatalogue;
use App\Contexts\Platform\Administration\Actions\ManagePlatformAdministrator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Tests\v3\Support\ScenarioFactory;
use Tests\v3\TestCase;

final class GiftCodeResearchedPlatformAdaptersV3Test extends TestCase
{
    use RefreshDatabase;

    public function test_catalogue_maps_documented_automation_without_turning_candidates_into_approval(): void
    {
        $catalogue = app(GiftCodeResearchedSourceCatalogue::class);
        $wiki = $catalogue->find('kingshot-official-wiki');

        self::assertSame(DiscordChannelGiftCodeSourceAdapter::KEY, $catalogue->find('kingshot-official-discord')['candidate_adapter_keys'][0] ?? null);
        self::assertSame([
            JsonFeedGiftCodeSourceAdapter::KEY,
            RssAtomGiftCodeSourceAdapter::KEY,
            StructuredHtmlGiftCodeSourceAdapter::KEY,
        ], $wiki['candidate_adapter_keys'] ?? null);
        self::assertContains('registered_manual_evidence', $wiki['transports'] ?? []);
        self::assertStringContainsString('documented_structured_contract', (string) ($wiki['gate'] ?? ''));
        self::assertSame([], $catalogue->find('kingshot-net')['candidate_adapter_keys'] ?? null);
        self::assertSame(YouTubeChannelGiftCodeSourceAdapter::KEY, $catalogue->find('kingshot-youtube')['candidate_adapter_keys'][0] ?? null);
        self::assertSame(RedditSubredditGiftCodeSourceAdapter::KEY, $catalogue->find('kingshot-reddit')['candidate_adapter_keys'][0] ?? null);
        self::assertSame(FacebookPageGiftCodeSourceAdapter::KEY, $catalogue->find('kingshot-facebook')['candidate_adapter_keys'][0] ?? null);
        self::assertSame(InstagramMediaGiftCodeSourceAdapter::KEY, $catalogue->find('kingshot-instagram')['candidate_adapter_keys'][0] ?? null);

        foreach ($catalogue->all() as $source) {
            self::assertSame('research_only', $source['catalogue_state']);
            self::assertArrayNotHasKey('approved', $source);
            self::assertArrayNotHasKey('auto_verify', $source);
            self::assertArrayNotHasKey('ingestion_enabled', $source);
        }
    }

    public function test_discord_adapter_accepts_only_approved_channel_author_and_explicit_label(): void
    {
        config()->set('game_world.gift_codes.discord_bot_token', 'discord-test-token');
        Http::fake([
            'https://discord.com/api/v10/channels/222222222222222222' => Http::response([
                'id' => '222222222222222222',
                'guild_id' => '111111111111111111',
            ], 200, ['Content-Type' => 'application/json']),
            'https://discord.com/api/v10/channels/222222222222222222/messages*' => Http::response([
                [
                    'id' => '333333333333333333',
                    'author' => ['id' => '444444444444444444'],
                    'content' => 'Gift Code: DISCORD26',
                    'timestamp' => '2026-09-05T12:00:00+00:00',
                ],
                [
                    'id' => '333333333333333334',
                    'author' => ['id' => '999999999999999999'],
                    'content' => 'Gift Code: REJECTME26',
                    'timestamp' => '2026-09-05T12:01:00+00:00',
                ],
                [
                    'id' => '333333333333333335',
                    'author' => ['id' => '444444444444444444'],
                    'content' => 'People are discussing code MAYBENOT.',
                    'timestamp' => '2026-09-05T12:02:00+00:00',
                ],
            ], 200, ['Content-Type' => 'application/json']),
        ]);

        $page = app(DiscordChannelGiftCodeSourceAdapter::class)->acquire(new GiftCodeSourceRegistry([
            'canonical_domain' => 'discord.com',
            'provenance_policy' => [
                'platform_permission_confirmed' => true,
                'message_content_access_confirmed' => true,
                'discord_guild_id' => '111111111111111111',
                'discord_channel_id' => '222222222222222222',
                'discord_author_ids' => ['444444444444444444'],
            ],
        ]), null, 20);

        self::assertCount(1, $page->observations);
        self::assertSame('DISCORD26', $page->observations[0]->code);
        self::assertSame(
            'https://discord.com/channels/111111111111111111/222222222222222222/333333333333333333',
            $page->observations[0]->sourceUrl,
        );
        Http::assertSent(static fn ($request): bool => $request->hasHeader('Authorization', 'Bot discord-test-token'));
    }

    public function test_youtube_adapter_reads_confirmed_channel_uploads_playlist_not_search_results(): void
    {
        config()->set('game_world.gift_codes.youtube_api_key', 'youtube-test-key');
        $channelId = 'UC1234567890123456789012';
        Http::fake([
            'https://www.googleapis.com/youtube/v3/channels*' => Http::response([
                'items' => [[
                    'id' => $channelId,
                    'snippet' => ['title' => 'Kingshot Official'],
                    'contentDetails' => ['relatedPlaylists' => ['uploads' => 'UU1234567890123456789012']],
                ]],
            ], 200, ['Content-Type' => 'application/json']),
            'https://www.googleapis.com/youtube/v3/playlistItems*' => Http::response([
                'items' => [[
                    'snippet' => [
                        'publishedAt' => '2026-09-05T12:00:00Z',
                        'title' => 'Gift Code: YOUTUBE26',
                        'description' => 'Official upload.',
                        'resourceId' => ['videoId' => 'videoId12345'],
                    ],
                ]],
                'nextPageToken' => 'next-page-token',
            ], 200, ['Content-Type' => 'application/json']),
        ]);

        $page = app(YouTubeChannelGiftCodeSourceAdapter::class)->acquire(new GiftCodeSourceRegistry([
            'canonical_domain' => 'youtube.com',
            'provenance_policy' => [
                'platform_api_access_confirmed' => true,
                'youtube_channel_id' => $channelId,
                'youtube_channel_title' => 'Kingshot Official',
            ],
        ]), null, 20);

        self::assertCount(1, $page->observations);
        self::assertSame('YOUTUBE26', $page->observations[0]->code);
        self::assertSame('https://www.youtube.com/watch?v=videoId12345', $page->observations[0]->sourceUrl);
        self::assertSame('next-page-token', $page->nextCursor);
        Http::assertSent(static fn ($request): bool => str_contains($request->url(), '/playlistItems?')
            && str_contains($request->url(), 'playlistId=UU1234567890123456789012'));
    }

    public function test_reddit_data_api_source_is_forced_to_independent_discovery_without_auto_verify(): void
    {
        $actor = $this->administrator();

        foreach ([
            ['classification' => 'official', 'auto_verify' => false, 'error' => 'classification'],
            ['classification' => 'independent', 'auto_verify' => true, 'error' => 'auto_verify'],
        ] as $case) {
            try {
                app(ManageGiftCodeSourceRegistry::class)->register($actor, [
                    'source_key' => 'reddit-policy-'.($case['auto_verify'] ? 'auto' : 'official'),
                    'name' => 'Reddit policy test',
                    'classification' => $case['classification'],
                    'canonical_domain' => 'reddit.com',
                    'verification_method' => 'reddit_data_api',
                    'adapter_key' => RedditSubredditGiftCodeSourceAdapter::KEY,
                    'provenance_policy' => [
                        'auto_verify' => $case['auto_verify'],
                        'platform_api_access_confirmed' => true,
                        'reddit_subreddit' => 'Kingshot',
                    ],
                    'ingestion_enabled' => false,
                ]);
                self::fail('Reddit policy must fail closed outside independent, non-auto-verified discovery.');
            } catch (ValidationException $exception) {
                self::assertArrayHasKey($case['error'], $exception->errors());
            }
        }
    }

    public function test_meta_adapters_verify_expected_account_identity_and_platform_evidence_domains(): void
    {
        config()->set('game_world.gift_codes.facebook_access_token', 'facebook-token');
        config()->set('game_world.gift_codes.instagram_access_token', 'instagram-token');
        config()->set('game_world.gift_codes.meta_graph_api_version', 'v26.0');
        Http::fake([
            'https://graph.facebook.com/v26.0/12345*' => static function ($request) {
                if (str_contains($request->url(), '/posts?')) {
                    return Http::response(['data' => [[
                        'id' => '12345_67890',
                        'message' => 'Gift Code: FACEBOOK26',
                        'created_time' => '2026-09-05T12:00:00+0000',
                        'permalink_url' => 'https://www.facebook.com/kingshot/posts/67890',
                    ]]], 200, ['Content-Type' => 'application/json']);
                }

                return Http::response(['id' => '12345', 'name' => 'Kingshot'], 200, ['Content-Type' => 'application/json']);
            },
            'https://graph.instagram.com/v26.0/54321*' => static function ($request) {
                if (str_contains($request->url(), '/media?')) {
                    return Http::response(['data' => [[
                        'id' => 'ig-media-1',
                        'caption' => 'Gift Code: INSTAGRAM26',
                        'permalink' => 'https://www.instagram.com/p/IGMEDIA1/',
                        'timestamp' => '2026-09-05T12:00:00+0000',
                        'username' => 'kingshotofficial',
                    ]]], 200, ['Content-Type' => 'application/json']);
                }

                return Http::response(['id' => '54321', 'username' => 'kingshotofficial'], 200, ['Content-Type' => 'application/json']);
            },
        ]);

        $facebook = app(FacebookPageGiftCodeSourceAdapter::class)->acquire(new GiftCodeSourceRegistry([
            'canonical_domain' => 'facebook.com',
            'provenance_policy' => [
                'platform_permission_confirmed' => true,
                'facebook_page_id' => '12345',
                'facebook_page_name' => 'Kingshot',
            ],
        ]), null, 20);
        self::assertSame('FACEBOOK26', $facebook->observations[0]->code);
        self::assertStringContainsString('facebook.com/', (string) $facebook->observations[0]->sourceUrl);

        $instagram = app(InstagramMediaGiftCodeSourceAdapter::class)->acquire(new GiftCodeSourceRegistry([
            'canonical_domain' => 'instagram.com',
            'provenance_policy' => [
                'platform_permission_confirmed' => true,
                'instagram_user_id' => '54321',
                'instagram_username' => 'kingshotofficial',
            ],
        ]), null, 20);
        self::assertSame('INSTAGRAM26', $instagram->observations[0]->code);
        self::assertStringContainsString('instagram.com/', (string) $instagram->observations[0]->sourceUrl);
    }

    public function test_two_distinct_registered_manual_publishers_satisfy_independent_corroboration_threshold(): void
    {
        config()->set('game_world.gift_codes.moderation', true);
        config()->set('game_world.gift_codes.independent_evidence_threshold', 2);
        $actor = $this->administrator();
        $registry = app(ManageGiftCodeSourceRegistry::class);
        $first = $registry->register($actor, [
            'source_key' => 'kingshot-net-manual-test',
            'name' => 'Kingshot.net manual test',
            'classification' => 'independent',
            'canonical_domain' => 'kingshot.net',
            'verification_method' => 'curator_confirmed_publication',
            'provenance_policy' => ['manual_evidence_allowed' => true, 'auto_verify' => false],
            'ingestion_enabled' => false,
        ]);
        $second = $registry->register($actor, [
            'source_key' => 'kingshot-mastery-manual-test',
            'name' => 'Kingshot Mastery manual test',
            'classification' => 'independent',
            'canonical_domain' => 'kingshotmastery.com',
            'verification_method' => 'curator_confirmed_publication',
            'provenance_policy' => ['manual_evidence_allowed' => true, 'auto_verify' => false],
            'ingestion_enabled' => false,
        ]);

        $record = app(RecordRegisteredGiftCodeEvidence::class);
        $record->handle($actor, [
            'source_id' => $first,
            'code' => 'MANUAL26',
            'assertion' => 'available',
            'source_url' => 'https://kingshot.net/gift-codes/manual26',
        ]);
        $giftCode = GiftCode::query()->where('normalized_code', 'MANUAL26')->firstOrFail();
        self::assertSame(GiftCodeStatus::Pending, $giftCode->status);

        $record->handle($actor, [
            'source_id' => $second,
            'code' => 'MANUAL26',
            'assertion' => 'available',
            'source_url' => 'https://kingshotmastery.com/gift-codes/manual26',
        ]);
        $giftCode->refresh();
        self::assertSame(GiftCodeStatus::Valid, $giftCode->status);
        self::assertSame(2, GiftCodeProvenance::query()->where('gift_code_id', (string) $giftCode->id)->distinct('registered_source_id')->count('registered_source_id'));
    }

    private function administrator(): AccountIdentity
    {
        $account = app(ScenarioFactory::class)->account();
        User::query()->whereKey($account->userId)->update([
            'email_verified_at' => now(),
            'two_factor_confirmed_at' => now(),
        ]);
        app(ManagePlatformAdministrator::class)->grant($account->userId);

        return app(AccountIdentityQuery::class)->require($account->userId);
    }
}
