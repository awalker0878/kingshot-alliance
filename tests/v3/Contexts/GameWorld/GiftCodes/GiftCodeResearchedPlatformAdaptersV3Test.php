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
use App\Contexts\GameWorld\GiftCodes\Adapters\RedditSubredditGiftCodeSourceAdapter;
use App\Contexts\GameWorld\GiftCodes\Adapters\YouTubeChannelGiftCodeSourceAdapter;
use App\Contexts\GameWorld\GiftCodes\Enums\GiftCodeEvidenceVerificationState;
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

    public function test_catalogue_maps_only_legitimate_stage_two_and_four_automation(): void
    {
        $catalogue = app(GiftCodeResearchedSourceCatalogue::class);

        self::assertSame(
            DiscordChannelGiftCodeSourceAdapter::KEY,
            $catalogue->find('kingshot-official-discord')['candidate_adapter_keys'][0] ?? null,
        );
        self::assertSame(
            [],
            $catalogue->find('kingshot-official-wiki')['candidate_adapter_keys'] ?? null,
            'The Wiki must not be assigned an automatic prose scraper.',
        );
        self::assertSame(
            [],
            $catalogue->find('kingshot-net')['candidate_adapter_keys'] ?? null,
            'Stage 3 publishers remain manual unless they publish a documented structured contract.',
        );
        self::assertSame(
            YouTubeChannelGiftCodeSourceAdapter::KEY,
            $catalogue->find('kingshot-youtube')['candidate_adapter_keys'][0] ?? null,
        );
        self::assertSame(
            RedditSubredditGiftCodeSourceAdapter::KEY,
            $catalogue->find('kingshot-reddit')['candidate_adapter_keys'][0] ?? null,
        );
    }

    public function test_discord_adapter_requires_installed_bot_scope_author_allowlist_and_explicit_label(): void
    {
        config()->set('game_world.gift_codes.discord_bot_token', 'discord-test-token');
        Http::fake([
            'https://discord.com/api/v10/channels/222222222222222222' => Http::response([
                'id' => '222222222222222222',
                'guild_id' => '111111111111111111',
                'name' => 'gift-codes',
            ], 200, ['Content-Type' => 'application/json']),
            'https://discord.com/api/v10/channels/222222222222222222/messages*' => Http::response([
                [
                    'id' => '333333333333333333',
                    'author' => ['id' => '444444444444444444'],
                    'content' => "Gift Code: DISCORD26\nRedeem through the normal flow.",
                    'timestamp' => '2026-09-05T12:00:00+00:00',
                ],
                [
                    'id' => '333333333333333334',
                    'author' => ['id' => '999999999999999999'],
                    'content' => 'Gift Code: UNTRUSTED26',
                    'timestamp' => '2026-09-05T12:01:00+00:00',
                ],
                [
                    'id' => '333333333333333335',
                    'author' => ['id' => '444444444444444444'],
                    'content' => 'People are talking about code MAYBENOT today.',
                    'timestamp' => '2026-09-05T12:02:00+00:00',
                ],
            ], 200, ['Content-Type' => 'application/json']),
        ]);

        $page = app(DiscordChannelGiftCodeSourceAdapter::class)->acquire(new GiftCodeSourceRegistry([
            'source_key' => 'official-discord-test',
            'name' => 'Official Discord test',
            'classification' => 'official',
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
        self::assertSame(DiscordChannelGiftCodeSourceAdapter::KEY, $page->observations[0]->parserVersion);
        Http::assertSent(static fn ($request): bool => $request->hasHeader('Authorization', 'Bot discord-test-token'));
    }

    public function test_youtube_adapter_uses_channel_uploads_playlist_and_explicit_labels(): void
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
                'items' => [
                    [
                        'snippet' => [
                            'publishedAt' => '2026-09-05T12:00:00Z',
                            'title' => 'Gift Code: YOUTUBE26',
                            'description' => 'Official upload.',
                            'resourceId' => ['videoId' => 'videoId12345'],
                        ],
                    ],
                    [
                        'snippet' => [
                            'publishedAt' => '2026-09-05T12:01:00Z',
                            'title' => 'A normal update',
                            'description' => 'The word code appears here, but no explicit label.',
                            'resourceId' => ['videoId' => 'videoId12346'],
                        ],
                    ],
                ],
                'nextPageToken' => 'next-page-token',
            ], 200, ['Content-Type' => 'application/json']),
        ]);

        $page = app(YouTubeChannelGiftCodeSourceAdapter::class)->acquire(new GiftCodeSourceRegistry([
            'source_key' => 'youtube-test',
            'name' => 'YouTube test',
            'classification' => 'official',
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

    public function test_reddit_adapter_is_registered_api_discovery_only(): void
    {
        config()->set('game_world.gift_codes.reddit_client_id', 'reddit-client');
        config()->set('game_world.gift_codes.reddit_client_secret', 'reddit-secret');
        config()->set('game_world.gift_codes.reddit_user_agent', 'kingshot-alliance:test-suite:v1');
        Http::fake([
            'https://www.reddit.com/api/v1/access_token' => Http::response([
                'access_token' => 'reddit-access-token',
                'token_type' => 'bearer',
            ], 200, ['Content-Type' => 'application/json']),
            'https://oauth.reddit.com/r/Kingshot/new*' => Http::response([
                'data' => [
                    'children' => [[
                        'data' => [
                            'id' => 'abc123',
                            'title' => 'Gift Code: REDDIT26',
                            'selftext' => 'Community discovery only.',
                            'permalink' => '/r/Kingshot/comments/abc123/gift_code_reddit26/',
                            'created_utc' => 1788610000,
                        ],
                    ]],
                    'after' => 't3_next',
                ],
            ], 200, ['Content-Type' => 'application/json']),
        ]);

        $page = app(RedditSubredditGiftCodeSourceAdapter::class)->acquire(new GiftCodeSourceRegistry([
            'source_key' => 'reddit-test',
            'name' => 'Reddit test',
            'classification' => 'independent',
            'canonical_domain' => 'reddit.com',
            'provenance_policy' => [
                'auto_verify' => false,
                'platform_api_access_confirmed' => true,
                'reddit_subreddit' => 'Kingshot',
            ],
        ]), null, 20);

        self::assertCount(1, $page->observations);
        self::assertSame('REDDIT26', $page->observations[0]->code);
        self::assertSame('https://www.reddit.com/r/Kingshot/comments/abc123/gift_code_reddit26/', $page->observations[0]->sourceUrl);
        self::assertSame('t3_next', $page->nextCursor);

        $actor = $this->administrator();
        try {
            app(ManageGiftCodeSourceRegistry::class)->register($actor, [
                'source_key' => 'reddit-cannot-be-official',
                'name' => 'Reddit cannot be official',
                'classification' => 'official',
                'canonical_domain' => 'reddit.com',
                'verification_method' => 'reddit_data_api',
                'adapter_key' => RedditSubredditGiftCodeSourceAdapter::KEY,
                'provenance_policy' => [
                    'auto_verify' => false,
                    'platform_api_access_confirmed' => true,
                    'reddit_subreddit' => 'Kingshot',
                ],
                'ingestion_enabled' => false,
            ]);
            self::fail('Reddit discovery must remain independent.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey('classification', $exception->errors());
        }
    }

    public function test_meta_adapters_verify_account_identity_and_keep_evidence_on_platform_domains(): void
    {
        config()->set('game_world.gift_codes.facebook_access_token', 'facebook-token');
        config()->set('game_world.gift_codes.instagram_access_token', 'instagram-token');
        config()->set('game_world.gift_codes.meta_graph_api_version', 'v26.0');
        Http::fake([
            'https://graph.facebook.com/v26.0/12345*' => static function ($request) {
                if (str_contains($request->url(), '/posts?')) {
                    return Http::response([
                        'data' => [[
                            'id' => '12345_67890',
                            'message' => 'Gift Code: FACEBOOK26',
                            'created_time' => '2026-09-05T12:00:00+0000',
                            'permalink_url' => 'https://www.facebook.com/kingshot/posts/67890',
                        ]],
                    ], 200, ['Content-Type' => 'application/json']);
                }

                return Http::response(['id' => '12345', 'name' => 'Kingshot'], 200, ['Content-Type' => 'application/json']);
            },
            'https://graph.instagram.com/v26.0/54321*' => static function ($request) {
                if (str_contains($request->url(), '/media?')) {
                    return Http::response([
                        'data' => [[
                            'id' => 'ig-media-1',
                            'caption' => 'Gift Code: INSTAGRAM26',
                            'permalink' => 'https://www.instagram.com/p/IGMEDIA1/',
                            'timestamp' => '2026-09-05T12:00:00+0000',
                            'username' => 'kingshotofficial',
                        ]],
                    ], 200, ['Content-Type' => 'application/json']);
                }

                return Http::response([
                    'id' => '54321',
                    'username' => 'kingshotofficial',
                ], 200, ['Content-Type' => 'application/json']);
            },
        ]);

        $facebook = app(FacebookPageGiftCodeSourceAdapter::class)->acquire(new GiftCodeSourceRegistry([
            'source_key' => 'facebook-test',
            'name' => 'Facebook test',
            'classification' => 'official',
            'canonical_domain' => 'facebook.com',
            'provenance_policy' => [
                'platform_permission_confirmed' => true,
                'facebook_page_id' => '12345',
                'facebook_page_name' => 'Kingshot',
            ],
        ]), null, 20);
        self::assertCount(1, $facebook->observations);
        self::assertSame('FACEBOOK26', $facebook->observations[0]->code);
        self::assertStringContainsString('facebook.com/', $facebook->observations[0]->sourceUrl);

        $instagram = app(InstagramMediaGiftCodeSourceAdapter::class)->acquire(new GiftCodeSourceRegistry([
            'source_key' => 'instagram-test',
            'name' => 'Instagram test',
            'classification' => 'official',
            'canonical_domain' => 'instagram.com',
            'provenance_policy' => [
                'platform_permission_confirmed' => true,
                'instagram_user_id' => '54321',
                'instagram_username' => 'kingshotofficial',
            ],
        ]), null, 20);
        self::assertCount(1, $instagram->observations);
        self::assertSame('INSTAGRAM26', $instagram->observations[0]->code);
        self::assertStringContainsString('instagram.com/', $instagram->observations[0]->sourceUrl);
    }

    public function test_distinct_manual_independent_sources_satisfy_existing_corroboration_threshold(): void
    {
        config()->set('game_world.gift_codes.moderation', true);
        config()->set('game_world.gift_codes.independent_evidence_threshold', 2);
        $actor = $this->administrator();
        $sources = app(ManageGiftCodeSourceRegistry::class);
        $firstSourceId = $sources->register($actor, [
            'source_key' => 'kingshot-net-manual-test',
            'name' => 'Kingshot.net manual test',
            'classification' => 'independent',
            'canonical_domain' => 'kingshot.net',
            'verification_method' => 'curator_confirmed_publication',
            'adapter_key' => null,
            'provenance_policy' => [
                'auto_verify' => false,
                'manual_evidence_allowed' => true,
            ],
            'ingestion_enabled' => false,
        ]);
        $secondSourceId = $sources->register($actor, [
            'source_key' => 'kingshot-mastery-manual-test',
            'name' => 'Kingshot Mastery manual test',
            'classification' => 'independent',
            'canonical_domain' => 'kingshotmastery.com',
            'verification_method' => 'curator_confirmed_publication',
            'adapter_key' => null,
            'provenance_policy' => [
                'auto_verify' => false,
                'manual_evidence_allowed' => true,
            ],
            'ingestion_enabled' => false,
        ]);

        $manual = app(RecordRegisteredGiftCodeEvidence::class);
        $manual->handle($actor, [
            'source_id' => $firstSourceId,
            'code' => 'MANUAL26',
            'assertion' => 'available',
            'source_url' => 'https://kingshot.net/gift-codes/manual26',
        ]);
        self::assertSame(
            GiftCodeStatus::Pending,
            GiftCode::query()->where('normalized_code', 'MANUAL26')->firstOrFail()->status,
        );

        $manual->handle($actor, [
            'source_id' => $secondSourceId,
            'code' => 'MANUAL26',
            'assertion' => 'available',
            'source_url' => 'https://kingshotmastery.com/gift-codes/manual26',
        ]);

        $giftCode = GiftCode::query()->where('normalized_code', 'MANUAL26')->firstOrFail();
        self::assertSame(GiftCodeStatus::Valid, $giftCode->status);
        $evidence = GiftCodeProvenance::query()
            ->where('gift_code_id', (string) $giftCode->id)
            ->orderBy('registered_source_id')
            ->get();
        self::assertCount(2, $evidence);
        self::assertCount(2, $evidence->pluck('registered_source_id')->unique());
        self::assertTrue($evidence->every(
            static fn (GiftCodeProvenance $item): bool => $item->verification_state === GiftCodeEvidenceVerificationState::Verified,
        ));

        try {
            $sources->register($actor, [
                'source_key' => 'manual-auto-verify-forbidden',
                'name' => 'Manual auto verify forbidden',
                'classification' => 'independent',
                'canonical_domain' => 'example.test',
                'verification_method' => 'manual_review',
                'adapter_key' => null,
                'provenance_policy' => [
                    'auto_verify' => true,
                    'manual_evidence_allowed' => true,
                ],
                'ingestion_enabled' => false,
            ]);
            self::fail('Manual registered evidence must never auto-verify by source policy.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey('auto_verify', $exception->errors());
        }
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
