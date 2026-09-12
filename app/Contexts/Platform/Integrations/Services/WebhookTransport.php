<?php

declare(strict_types=1);

namespace App\Contexts\Platform\Integrations\Services;

use App\Contexts\Platform\Integrations\ValueObjects\ResolvedWebhookEndpoint;
use GuzzleHttp\Handler\CurlHandler;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Psr\Http\Message\ResponseInterface;
use RuntimeException;

final class WebhookTransport
{
    /** @param array<string, string> $headers */
    public function send(ResolvedWebhookEndpoint $endpoint, string $body, array $headers): Response
    {
        if (! extension_loaded('curl')) {
            throw new RuntimeException('The webhook HTTPS transport is unavailable.');
        }

        return Http::acceptJson()
            ->setHandler(new CurlHandler)
            ->withoutRedirecting()
            ->connectTimeout(3)
            ->timeout(10)
            ->withOptions([
                'verify' => true,
                'proxy' => '',
                'on_headers' => static function (ResponseInterface $response): void {
                    $length = $response->getHeaderLine('Content-Length');
                    if (is_numeric($length) && (float) $length > 65536) {
                        throw new RuntimeException('Webhook response exceeded the 64 KiB limit.');
                    }
                },
                'progress' => static function (float $total, float $downloaded): void {
                    if ($total > 65536 || $downloaded > 65536) {
                        throw new RuntimeException('Webhook response exceeded the 64 KiB limit.');
                    }
                },
                'decode_content' => false,
                'curl' => [
                    CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
                    CURLOPT_RESOLVE => [$endpoint->curlResolution()],
                    CURLOPT_PROXY => '',
                    CURLOPT_FRESH_CONNECT => true,
                    CURLOPT_FORBID_REUSE => true,
                ],
            ])
            ->withHeaders($headers)
            ->withBody($body, 'application/json')
            ->post($endpoint->url);
    }
}
