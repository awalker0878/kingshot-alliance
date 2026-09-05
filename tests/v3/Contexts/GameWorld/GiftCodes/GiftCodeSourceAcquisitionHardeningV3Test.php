<?php

declare(strict_types=1);

namespace Tests\v3\Contexts\GameWorld\GiftCodes;

use App\Contexts\Accounts\Identity\Models\User;
use App\Contexts\Accounts\Identity\Queries\AccountIdentityQuery;
use App\Contexts\Accounts\Identity\ValueObjects\AccountIdentity;
use App\Contexts\GameWorld\GiftCodes\Actions\ManageGiftCodeSourceRegistry;
use App\Contexts\GameWorld\GiftCodes\Actions\RunApprovedGiftCodeSourceIngestion;
use App\Contexts\GameWorld\GiftCodes\Adapters\DiscordChannelGiftCodeSourceAdapter;
use App\Contexts\GameWorld\GiftCodes\Adapters\JsonFeedGiftCodeSourceAdapter;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeIngestionRun;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeSourceRegistry;
use App\Contexts\GameWorld\GiftCodes\Queries\GiftCodeIngestionHealthQuery;
use App\Contexts\GameWorld\GiftCodes\Services\EvaluateGiftCodeSourceActivationReadiness;
use App\Contexts\Platform\Administration\Actions\ManagePlatformAdministrator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Tests\v3\Support\ScenarioFactory;
use Tests\v3\TestCase;

final class GiftCodeSourceAcquisitionHardeningV3Test extends TestCase
{
    use RefreshDatabase;

    public function test_generic_machine_feed_cannot_activate_without_documented_provider_contract(): void
    {
        $actor = $this->administrator();

        try {
            app(ManageGiftCodeSourceRegistry::class)->register($actor, [
                'source_key' => 'feed-without-contract',
                'name' => 'Feed without contract',
                'classification' => 'official',
                'canonical_domain' => 'publisher.example.test',
                'verification_method' => 'approved_json_feed',
                'adapter_key' => JsonFeedGiftCodeSourceAdapter::KEY,
                'provenance_policy' => [
                    'feed_path' => '/gift-codes.json',
                    'auto_verify' => false,
                ],
                'ingestion_enabled' => true,
            ]);
            self::fail('A generic feed must not activate before its machine-readable provider contract is confirmed.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey('provider_contract_confirmed', $exception->errors());
        }

        $sourceId = app(ManageGiftCodeSourceRegistry::class)->register($actor, [
            'source_key' => 'feed-with-contract',
            'name' => 'Feed with contract',
            'classification' => 'official',
            'canonical_domain' => 'publisher.example.test',
            'verification_method' => 'approved_json_feed',
            'adapter_key' => JsonFeedGiftCodeSourceAdapter::KEY,
            'provenance_policy' => [
                'feed_path' => '/gift-codes.json',
                'provider_contract_confirmed' => true,
                'auto_verify' => false,
            ],
            'ingestion_enabled' => true,
        ]);

        $source = GiftCodeSourceRegistry::query()->findOrFail($sourceId);
        self::assertSame('enabled', $source->activation_status);
        self::assertTrue(app(EvaluateGiftCodeSourceActivationReadiness::class)->forSource($source)->ready());
    }

    public function test_rate_limited_source_is_deferred_and_exposes_operational_health(): void
    {
        config()->set('game_world.gift_codes.approved_source_ingestion', true);
        $source = GiftCodeSourceRegistry::query()->create([
            'source_key' => 'rate-limited-feed',
            'name' => 'Rate limited feed',
            'classification' => 'official',
            'canonical_domain' => 'publisher.example.test',
            'verification_method' => 'approved_json_feed',
            'adapter_key' => JsonFeedGiftCodeSourceAdapter::KEY,
            'provenance_policy' => [
                'feed_path' => '/gift-codes.json',
                'provider_contract_confirmed' => true,
                'auto_verify' => false,
            ],
            'ingestion_enabled' => true,
            'activation_status' => 'enabled',
            'health_status' => 'pending',
            'is_active' => true,
            'policy_revision' => 1,
        ]);
        Http::fake([
            'https://publisher.example.test/gift-codes.json*' => Http::response(
                ['message' => 'slow down'],
                429,
                [
                    'Content-Type' => 'application/json',
                    'Retry-After' => '120',
                    'X-Request-Id' => 'provider-request-429',
                    'X-RateLimit-Remaining' => '0',
                ],
            ),
        ]);

        $sweep = app(RunApprovedGiftCodeSourceIngestion::class)->handle(sourceKey: $source->source_key);

        self::assertSame(1, $sweep->failedSources);
        $source->refresh();
        self::assertSame('rate_limited', $source->health_status);
        self::assertSame(1, $source->rate_limit_event_count);
        self::assertSame(1, $source->consecutive_failures);
        self::assertSame(120, $source->last_retry_after_seconds);
        self::assertSame('provider-request-429', $source->last_provider_request_id);
        self::assertNotNull($source->next_eligible_ingestion_at);
        self::assertTrue($source->next_eligible_ingestion_at->isFuture());

        $run = GiftCodeIngestionRun::query()->firstOrFail();
        self::assertSame('failed', $run->status);
        self::assertSame('rate_limited', $run->failure_code);
        self::assertSame(120, $run->retry_after_seconds);

        $health = app(GiftCodeIngestionHealthQuery::class)->get();
        self::assertSame('rate_limited', $health[0]['healthStatus']);
        self::assertSame(1, $health[0]['rateLimitEventCount']);
        self::assertFalse($health[0]['stale']);
        self::assertArrayHasKey('activationReadiness', $health[0]);
    }

    public function test_discord_uses_message_high_water_cursor_and_after_parameter(): void
    {
        config()->set('game_world.gift_codes.discord_bot_token', 'discord-token');
        $source = new GiftCodeSourceRegistry([
            'source_key' => 'discord-high-water',
            'name' => 'Discord high water',
            'classification' => 'official',
            'canonical_domain' => 'discord.com',
            'verification_method' => 'discord_bot_api',
            'adapter_key' => DiscordChannelGiftCodeSourceAdapter::KEY,
            'provenance_policy' => [
                'platform_permission_confirmed' => true,
                'message_content_access_confirmed' => true,
                'discord_guild_id' => '100',
                'discord_channel_id' => '200',
                'discord_author_ids' => ['300'],
            ],
            'ingestion_enabled' => true,
            'is_active' => true,
        ]);
        Http::fake([
            'https://discord.com/api/v10/channels/200' => Http::response([
                'id' => '200',
                'guild_id' => '100',
            ], 200, ['Content-Type' => 'application/json']),
            'https://discord.com/api/v10/channels/200/messages*' => Http::response([
                [
                    'id' => '500',
                    'author' => ['id' => '300'],
                    'content' => 'Gift Code: DISCORD500',
                    'timestamp' => '2026-09-05T14:00:00Z',
                ],
                [
                    'id' => '450',
                    'author' => ['id' => '300'],
                    'content' => 'No explicit code here',
                    'timestamp' => '2026-09-05T13:00:00Z',
                ],
            ], 200, [
                'Content-Type' => 'application/json',
                'X-RateLimit-Remaining' => '49',
            ]),
        ]);

        $page = app(DiscordChannelGiftCodeSourceAdapter::class)->acquire($source, '400', 20);

        self::assertSame('500', $page->nextCursor);
        self::assertSame('500', $page->checkpoint?->providerState['message_high_water'] ?? null);
        self::assertSame(49, $page->rateLimit?->remaining);
        self::assertSame(2, $page->requestCount);
        self::assertCount(1, $page->observations);
        Http::assertSent(static fn ($request): bool => str_contains(
            $request->url(),
            '/channels/200/messages?limit=20&after=400',
        ));
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
