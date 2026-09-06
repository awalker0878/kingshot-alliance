<?php

declare(strict_types=1);

namespace Tests\v3\Contexts\GameWorld\GiftCodes;

use App\Contexts\Accounts\Identity\Models\User;
use App\Contexts\Accounts\Identity\Queries\AccountIdentityQuery;
use App\Contexts\Accounts\Identity\ValueObjects\AccountIdentity;
use App\Contexts\GameWorld\GiftCodes\Actions\ManageGiftCodeSourceRegistry;
use App\Contexts\GameWorld\GiftCodes\Actions\RunApprovedGiftCodeSourceIngestion;
use App\Contexts\GameWorld\GiftCodes\Adapters\CenturyGamesKingshotNewsRssGiftCodeSourceAdapter;
use App\Contexts\GameWorld\GiftCodes\Adapters\OfficialXGiftCodeSourceAdapter;
use App\Contexts\GameWorld\GiftCodes\Enums\GiftCodeEvidenceVerificationState;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeProvenance;
use App\Contexts\GameWorld\GiftCodes\Services\GiftCodeResearchedSourceCatalogue;
use App\Contexts\Platform\Administration\Actions\ManagePlatformAdministrator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Tests\v3\Support\ScenarioFactory;
use Tests\v3\TestCase;

final class GiftCodeResearchedSourceAdaptersV3Test extends TestCase
{
    use RefreshDatabase;

    public function test_research_catalogue_does_not_grant_source_authority_or_enable_ingestion(): void
    {
        $catalogue = app(GiftCodeResearchedSourceCatalogue::class);
        $sources = $catalogue->all();

        self::assertSame([0, 1, 2, 3, 4], array_values(array_unique(array_column($sources, 'stage'))));
        self::assertSame(
            ['kingshot-official-x', 'century-games-kingshot-news'],
            array_column($catalogue->forStage(1), 'source_key'),
        );
        self::assertSame(
            OfficialXGiftCodeSourceAdapter::KEY,
            $catalogue->find('kingshot-official-x')['candidate_adapter_keys'][0] ?? null,
        );

        foreach ($sources as $source) {
            self::assertSame('research_only', $source['catalogue_state']);
            self::assertArrayNotHasKey('approved', $source);
            self::assertArrayNotHasKey('auto_verify', $source);
            self::assertArrayNotHasKey('ingestion_enabled', $source);
        }
    }

    public function test_official_x_adapter_uses_documented_api_and_only_explicit_gift_code_labels(): void
    {
        config()->set('game_world.gift_codes.approved_source_ingestion', true);
        config()->set('game_world.gift_codes.x_bearer_token', 'test-x-token');
        $actor = $this->administrator();
        app(ManageGiftCodeSourceRegistry::class)->register($actor, [
            'source_key' => 'official-kingshot-x-test',
            'name' => 'Official Kingshot X test',
            'classification' => 'official',
            'canonical_domain' => 'x.com',
            'verification_method' => 'official_x_api_v2',
            'adapter_key' => OfficialXGiftCodeSourceAdapter::KEY,
            'provenance_policy' => [
                'auto_verify' => true,
                'platform_api_access_confirmed' => true,
                'x_user_id' => '123456789',
                'x_username' => 'KingshotGame',
            ],
            'ingestion_enabled' => true,
        ]);

        Http::fake([
            'api.x.com/2/users/123456789/tweets*' => Http::response([
                'data' => [
                    [
                        'id' => '200000000000000001',
                        'author_id' => '123456789',
                        'created_at' => '2026-09-05T12:00:00.000Z',
                        'text' => "🎁 Gift Code: XCODE2026\nRedeem it in the official Gift Code Center.",
                    ],
                    [
                        'id' => '200000000000000002',
                        'author_id' => '123456789',
                        'created_at' => '2026-09-05T12:05:00.000Z',
                        'text' => 'Players are discussing code MAYBENOT, but this is not an explicit Gift Code label.',
                    ],
                ],
                'includes' => [
                    'users' => [[
                        'id' => '123456789',
                        'username' => 'KingshotGame',
                    ]],
                ],
                'meta' => ['result_count' => 2],
            ], 200, [
                'Content-Type' => 'application/json; charset=utf-8',
                'ETag' => '"x-page-1"',
            ]),
        ]);

        $result = app(RunApprovedGiftCodeSourceIngestion::class)->handle(sourceKey: 'official-kingshot-x-test');

        self::assertSame(1, $result->accepted);
        $evidence = GiftCodeProvenance::query()->firstOrFail();
        self::assertSame('https://x.com/KingshotGame/status/200000000000000001', $evidence->source_url);
        self::assertSame('x-post:200000000000000001', $evidence->source_version);
        self::assertSame('ETag:"x-page-1"', $evidence->retrieval_version);
        self::assertSame(OfficialXGiftCodeSourceAdapter::KEY, $evidence->parser_version);
        self::assertSame(GiftCodeEvidenceVerificationState::Verified, $evidence->verification_state);

        Http::assertSent(static fn ($request): bool => str_starts_with(
            $request->url(),
            'https://api.x.com/2/users/123456789/tweets?',
        ) && $request->hasHeader('Authorization', 'Bearer test-x-token'));
    }

    public function test_official_x_adapter_cannot_be_enabled_without_server_side_credentials(): void
    {
        config()->set('game_world.gift_codes.x_bearer_token', null);
        $actor = $this->administrator();

        try {
            app(ManageGiftCodeSourceRegistry::class)->register($actor, [
                'source_key' => 'x-without-credential',
                'name' => 'X without credential',
                'classification' => 'official',
                'canonical_domain' => 'x.com',
                'verification_method' => 'official_x_api_v2',
                'adapter_key' => OfficialXGiftCodeSourceAdapter::KEY,
                'provenance_policy' => [
                    'auto_verify' => false,
                    'platform_api_access_confirmed' => true,
                    'x_user_id' => '123456789',
                    'x_username' => 'KingshotGame',
                ],
                'ingestion_enabled' => true,
            ]);
            self::fail('The X adapter must fail closed when no server-side API credential is configured.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey('adapter_key', $exception->errors());
        }
    }

    public function test_century_games_news_adapter_requires_express_permission_and_explicit_kingshot_content(): void
    {
        config()->set('game_world.gift_codes.approved_source_ingestion', true);
        $actor = $this->administrator();

        try {
            app(ManageGiftCodeSourceRegistry::class)->register($actor, [
                'source_key' => 'century-news-without-permission',
                'name' => 'Century Games news without permission',
                'classification' => 'official',
                'canonical_domain' => 'centurygames.com',
                'verification_method' => 'permissioned_provider_rss',
                'adapter_key' => CenturyGamesKingshotNewsRssGiftCodeSourceAdapter::KEY,
                'provenance_policy' => [
                    'auto_verify' => false,
                    'feed_path' => '/feed/',
                    'provider_permission_confirmed' => false,
                ],
                'ingestion_enabled' => true,
            ]);
            self::fail('Century Games news ingestion must not enable before express provider permission is recorded.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey('provider_permission_confirmed', $exception->errors());
        }

        app(ManageGiftCodeSourceRegistry::class)->register($actor, [
            'source_key' => 'century-games-kingshot-news-test',
            'name' => 'Century Games Kingshot news test',
            'classification' => 'official',
            'canonical_domain' => 'centurygames.com',
            'verification_method' => 'permissioned_provider_rss',
            'adapter_key' => CenturyGamesKingshotNewsRssGiftCodeSourceAdapter::KEY,
            'provenance_policy' => [
                'auto_verify' => true,
                'feed_path' => '/feed/',
                'provider_permission_confirmed' => true,
            ],
            'ingestion_enabled' => true,
        ]);

        Http::fake([
            'www.centurygames.com/feed/*' => Http::response(<<<'XML'
<?xml version="1.0" encoding="UTF-8"?>
<rss version="2.0">
  <channel>
    <title>Century Games News</title>
    <lastBuildDate>Sat, 05 Sep 2026 12:30:00 +0000</lastBuildDate>
    <item>
      <title>Other game promotion</title>
      <link>https://www.centurygames.com/other-game-promotion/</link>
      <description>Gift Code: WRONGGAME2026</description>
    </item>
    <item>
      <title>Kingshot - September Calendar</title>
      <link>https://www.centurygames.com/kingshot-september-calendar/</link>
      <description>General event news without an explicit Gift Code label.</description>
    </item>
    <item>
      <title>Kingshot - Gift Code announcement</title>
      <link>https://www.centurygames.com/kingshot-gift-code-rsscode2026/</link>
      <description>Gift Code: RSSCODE2026</description>
      <pubDate>Sat, 05 Sep 2026 12:15:00 +0000</pubDate>
    </item>
  </channel>
</rss>
XML, 200, [
                'Content-Type' => 'application/rss+xml; charset=utf-8',
                'ETag' => '"century-news-1"',
            ]),
        ]);

        $result = app(RunApprovedGiftCodeSourceIngestion::class)->handle(sourceKey: 'century-games-kingshot-news-test');

        self::assertSame(1, $result->accepted);
        self::assertSame(1, GiftCodeProvenance::query()->count());
        $evidence = GiftCodeProvenance::query()->firstOrFail();
        self::assertSame('https://www.centurygames.com/kingshot-gift-code-rsscode2026/', $evidence->source_url);
        self::assertSame(CenturyGamesKingshotNewsRssGiftCodeSourceAdapter::KEY, $evidence->parser_version);
        self::assertSame(GiftCodeEvidenceVerificationState::Verified, $evidence->verification_state);
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
