<?php

declare(strict_types=1);

namespace Tests\Contexts\Platform\Integrations\Feature;

use App\Contexts\Platform\Integrations\Services\WebhookTransport;
use App\Contexts\Platform\Integrations\ValueObjects\ResolvedWebhookEndpoint;
use Illuminate\Support\Facades\Http;
use Symfony\Component\Process\Process;
use Tests\TestCase;
use Throwable;

final class WebhookHttpsTransportTest extends TestCase
{
    public function test_real_tls_transport_pins_connects_signs_and_enforces_failure_boundaries(): void
    {
        $directory = sys_get_temp_dir().'/webhook-tls-'.bin2hex(random_bytes(8));
        mkdir($directory, 0700);
        $server = null;
        $previousProxy = getenv('HTTPS_PROXY');
        try {
            (new Process(['openssl', 'req', '-x509', '-newkey', 'rsa:2048', '-nodes', '-days', '1',
                '-subj', '/CN=hooks.example.test', '-addext', 'subjectAltName=DNS:hooks.example.test',
                '-keyout', $directory.'/key.pem', '-out', $directory.'/cert.pem']))->mustRun();
            $server = new Process(['python3', base_path('tests/Contexts/Platform/Integrations/Fixtures/webhook_https_server.py'),
                $directory.'/cert.pem', $directory.'/key.pem', $directory.'/requests.jsonl']);
            $server->setTimeout(15);
            $server->start();
            self::assertTrue($server->waitUntil(static fn (string $type, string $output): bool => $type === Process::OUT && preg_match('/^\d+\s*$/D', $output) === 1));
            $port = (int) trim($server->getOutput());
            self::assertGreaterThan(0, $port);
            // The isolated adapter fixture deliberately supplies a loopback pin.
            // Production destinations must first pass WebhookEndpointPolicy.
            $endpoint = static fn (string $path, string $host = 'hooks.example.test'): ResolvedWebhookEndpoint => new ResolvedWebhookEndpoint('https://'.$host.':'.$port.$path, $host, $port, '127.0.0.1');
            Http::globalOptions(['curl' => [CURLOPT_CAINFO => $directory.'/cert.pem']]);
            putenv('HTTPS_PROXY=http://127.0.0.1:1');
            $body = '{"url":"https://example.test/a","name":"caf\u00e9"}';
            $signature = 'sha256='.hash_hmac('sha256', '123.'.$body, 'fixture-secret');
            $transport = app(WebhookTransport::class);
            self::assertSame(204, $transport->send($endpoint('/ok'), $body, ['X-Kingshot-Timestamp' => '123', 'X-Kingshot-Signature' => $signature])->status());
            self::assertSame(302, $transport->send($endpoint('/redirect'), $body, [])->status());
            foreach (['/large', '/chunked'] as $path) {
                $error = null;
                try {
                    $transport->send($endpoint($path), $body, []);
                } catch (Throwable $exception) {
                    $error = $exception;
                }
                self::assertNotNull($error, 'An oversized provider response must abort.');
            }
            $error = null;
            try {
                $transport->send($endpoint('/wrong-host', 'wrong.example.test'), $body, []);
            } catch (Throwable $exception) {
                $error = $exception;
            }
            self::assertNotNull($error, 'The certificate must match the original hostname.');
            $lines = file($directory.'/requests.jsonl', FILE_IGNORE_NEW_LINES);
            self::assertIsArray($lines);
            $requests = array_map(static fn (string $line): array => json_decode($line, true, flags: JSON_THROW_ON_ERROR), $lines);
            self::assertSame(['/ok', '/redirect', '/large', '/chunked'], array_column($requests, 'path'));
            self::assertSame($body, $requests[0]['body']);
            self::assertSame($signature, $requests[0]['headers']['X-Kingshot-Signature']);
            self::assertSame('hooks.example.test:'.$port, $requests[0]['headers']['Host']);
        } finally {
            $server?->stop(0);
            $previousProxy === false ? putenv('HTTPS_PROXY') : putenv('HTTPS_PROXY='.$previousProxy);
            Http::globalOptions([]);
            foreach (glob($directory.'/*') ?: [] as $file) {
                unlink($file);
            }
            rmdir($directory);
        }
    }
}
