<?php

declare(strict_types=1);

namespace Tests\v3\Contexts\GameWorld\GiftCodes;

use App\Contexts\GameWorld\GiftCodes\Adapters\FacebookPageGiftCodeSourceAdapter;
use App\Contexts\GameWorld\GiftCodes\Adapters\JsonFeedGiftCodeSourceAdapter;
use App\Contexts\GameWorld\GiftCodes\Adapters\RssAtomGiftCodeSourceAdapter;
use App\Contexts\GameWorld\GiftCodes\Adapters\StructuredHtmlGiftCodeSourceAdapter;
use App\Contexts\GameWorld\GiftCodes\Exceptions\GiftCodeSourceAcquisitionException;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeSourceRegistry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\v3\TestCase;
use UnexpectedValueException;

final class GiftCodeProviderFailureMatrixV3Test extends TestCase
{
    use RefreshDatabase;

    public function test_common_http_provider_failures_have_stable_operational_codes(): void
    {
        $matrix = [
            401 => 'authentication_failed',
            403 => 'permission_revoked',
            404 => 'source_identity_unavailable',
            408 => 'provider_timeout',
            409 => 'provider_conflict',
            429 => 'rate_limited',
            500 => 'provider_unavailable',
            503 => 'provider_unavailable',
            418 => 'source_retrieval_failed',
        ];

        foreach ($matrix as $status => $expected) {
            Http::fake([
                'https://publisher.example.test/gift-codes.json*' => Http::response(
                    ['error' => 'fixture'],
                    $status,
                    [
                        'Content-Type' => 'application/json',
                        'Retry-After' => '120',
                        'X-Request-Id' => 'fixture-'.$status,
                    ],
                ),
            ]);

            try {
                app(JsonFeedGiftCodeSourceAdapter::class)->acquire($this->source('/gift-codes.json'), null, 10);
                self::fail(sprintf('HTTP %d must fail the provider contract.', $status));
            } catch (GiftCodeSourceAcquisitionException $exception) {
                self::assertSame($expected, $exception->failureCode, 'Unexpected failure code for HTTP '.$status);
                self::assertSame($status, $exception->httpStatus);
                self::assertSame(120, $exception->retryAfterSeconds);
                self::assertSame('fixture-'.$status, $exception->providerRequestId);
            }
        }
    }

    public function test_parser_drift_and_malformed_documents_fail_closed(): void
    {
        Http::fake([
            'https://publisher.example.test/gift-codes.json*' => Http::response(
                ['version' => 'drifted-without-items'],
                200,
                ['Content-Type' => 'application/json'],
            ),
        ]);
        $this->assertUnexpectedValue(static fn () => app(JsonFeedGiftCodeSourceAdapter::class)
            ->acquire($this->source('/gift-codes.json'), null, 10));

        Http::fake([
            'https://publisher.example.test/gift-codes.xml*' => Http::response(
                '<rss><channel><item><ks:gift-code>UNCLOSED',
                200,
                ['Content-Type' => 'application/rss+xml'],
            ),
        ]);
        $this->assertUnexpectedValue(static fn () => app(RssAtomGiftCodeSourceAdapter::class)
            ->acquire($this->source('/gift-codes.xml'), null, 10));

        Http::fake([
            'https://publisher.example.test/gift-codes*' => Http::response(
                '<html><body><p>Gift Code: PROSE-ONLY-26</p></body></html>',
                200,
                ['Content-Type' => 'text/html'],
            ),
        ]);
        $this->assertUnexpectedValue(static fn () => app(StructuredHtmlGiftCodeSourceAdapter::class)
            ->acquire($this->source('/gift-codes'), null, 10));
    }

    public function test_facebook_identity_mismatch_fails_before_publication_observations_are_accepted(): void
    {
        config()->set('game_world.gift_codes.facebook_access_token', 'facebook-token');
        config()->set('game_world.gift_codes.meta_graph_api_version', 'v26.0');
        Http::fake([
            'https://graph.facebook.com/v26.0/12345*' => Http::response([
                'id' => '99999',
                'name' => 'Not Kingshot',
            ], 200, ['Content-Type' => 'application/json']),
        ]);
        $source = new GiftCodeSourceRegistry([
            'canonical_domain' => 'facebook.com',
            'provenance_policy' => [
                'platform_permission_confirmed' => true,
                'facebook_page_id' => '12345',
                'facebook_page_name' => 'Kingshot',
            ],
        ]);

        try {
            app(FacebookPageGiftCodeSourceAdapter::class)->acquire($source, null, 10);
            self::fail('A mismatched Facebook Page identity must fail closed.');
        } catch (UnexpectedValueException $exception) {
            self::assertStringContainsString('identity did not match', $exception->getMessage());
        }
        Http::assertSentCount(1);
    }

    private function source(string $path): GiftCodeSourceRegistry
    {
        return new GiftCodeSourceRegistry([
            'source_key' => 'failure-matrix',
            'name' => 'Failure matrix',
            'classification' => 'official',
            'canonical_domain' => 'publisher.example.test',
            'verification_method' => 'fixture',
            'adapter_key' => JsonFeedGiftCodeSourceAdapter::KEY,
            'provenance_policy' => [
                'feed_path' => $path,
                'provider_contract_confirmed' => true,
                'structured_contract_confirmed' => true,
                'auto_verify' => false,
            ],
            'is_active' => true,
            'ingestion_enabled' => true,
        ]);
    }

    private function assertUnexpectedValue(callable $callback): void
    {
        try {
            $callback();
            self::fail('The drifted provider fixture must fail closed.');
        } catch (UnexpectedValueException) {
            self::assertTrue(true);
        }
    }
}
