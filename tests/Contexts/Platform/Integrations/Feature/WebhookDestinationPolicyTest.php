<?php

declare(strict_types=1);

namespace Tests\Contexts\Platform\Integrations\Feature;

use App\Contexts\Platform\Integrations\Services\WebhookEndpointPolicy;
use App\Contexts\Platform\Integrations\Services\WebhookHostResolver;
use App\Contexts\Platform\Integrations\Services\WebhookTransport;
use GuzzleHttp\Psr7\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

final class WebhookDestinationPolicyTest extends TestCase
{
    public static function unsafeAddresses(): iterable
    {
        foreach (['::ffff:127.0.0.1', '::ffff:10.1.2.3', '64:ff9b::a00:1', '100::1',
            '2002:7f00:1::', 'fec0::1', 'fe80::1', 'fc00::1', '2001:db8::1', '3fff::1',
            '0.0.0.0', '127.1.2.3', '169.254.169.254', '100.64.0.1', '192.88.99.1', '240.0.0.1'] as $address) {
            yield $address => [[$address]];
            yield 'mixed '.$address => [['8.8.8.8', $address]];
        }
        yield 'empty' => [[]];
    }

    #[DataProvider('unsafeAddresses')]
    public function test_non_public_answers_fail_closed(array $addresses): void
    {
        $this->expectException(ValidationException::class);
        $this->policy($addresses)->resolveAllowed('https://hooks.example.com/events');
    }

    public static function unsafeUrls(): iterable
    {
        foreach (['http://example.com', 'https://user:secret@example.com/', 'https://example.com/#fragment',
            'https://127.1/', 'https://2130706433/', 'https://0x7f000001/', 'https://0177.0.0.1/',
            'https://[::ffff:127.0.0.1]/', 'https://localhost/', 'https://box.local/',
            "https://example.com/\n", 'https://example.com\\@127.0.0.1/', 'https://example.com:0/'] as $url) {
            yield $url => [$url];
        }
    }

    #[DataProvider('unsafeUrls')]
    public function test_ambiguous_and_unsafe_urls_are_rejected_before_dns(string $url): void
    {
        $this->expectException(ValidationException::class);
        $this->policy(['8.8.8.8'])->resolveAllowed($url);
    }

    public function test_canonical_request_and_pin_share_the_same_host_and_port(): void
    {
        $endpoint = $this->policy(['8.8.8.8'])->resolveAllowed('https://HOOKS.Example.Com.:8443/a?b=1');
        self::assertSame('https://hooks.example.com:8443/a?b=1', $endpoint->url);
        self::assertSame('hooks.example.com:8443:8.8.8.8', $endpoint->curlResolution());
        $v6 = $this->policy(['2606:4700:4700::1111'])->resolveAllowed('https://[2606:4700:4700::1111]/');
        self::assertSame('https://[2606:4700:4700::1111]/', $v6->url);
    }

    public function test_transport_pins_egress_and_sends_the_exact_signed_bytes(): void
    {
        $endpoint = $this->policy(['8.8.8.8'])->resolveAllowed('https://hooks.example.com:8443/a');
        $body = '{"url":"https://example.com/a","name":"caf\u00e9"}';
        $signature = hash_hmac('sha256', '123.'.$body, 'test-secret');
        Http::fake(function ($request, array $options) use ($body, $signature) {
            self::assertSame($body, $request->body());
            self::assertSame(['sha256='.$signature], $request->header('X-Kingshot-Signature'));
            self::assertFalse($options['allow_redirects']);
            self::assertTrue($options['verify']);
            self::assertSame('', $options['proxy']);
            self::assertSame('', $options['curl'][CURLOPT_PROXY]);
            self::assertSame(['hooks.example.com:8443:8.8.8.8'], $options['curl'][CURLOPT_RESOLVE]);
            self::assertTrue($options['curl'][CURLOPT_FRESH_CONNECT]);
            foreach (['on_headers', 'progress'] as $callback) {
                try {
                    $callback === 'on_headers'
                        ? $options[$callback](new Response(200, ['Content-Length' => 65537]))
                        : $options[$callback](0, 65537);
                    self::fail('Oversized responses must abort.');
                } catch (RuntimeException $exception) {
                    self::assertStringContainsString('64 KiB', $exception->getMessage());
                }
            }

            return Http::response('', 302, ['Location' => 'https://127.0.0.1/']);
        });
        $response = app(WebhookTransport::class)->send($endpoint, $body, ['X-Kingshot-Signature' => 'sha256='.$signature]);
        self::assertSame(302, $response->status());
        Http::assertSentCount(1);
    }

    private function policy(array $addresses): WebhookEndpointPolicy
    {
        return new WebhookEndpointPolicy(new class($addresses) extends WebhookHostResolver
        {
            public function __construct(private array $addresses) {}

            public function resolve(string $host): array
            {
                return $this->addresses;
            }
        });
    }
}
