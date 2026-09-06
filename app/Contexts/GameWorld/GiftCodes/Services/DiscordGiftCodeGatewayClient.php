<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\GiftCodes\Services;

use App\Contexts\GameWorld\GiftCodes\Actions\ProcessDiscordGiftCodeGatewayEvent;
use App\Contexts\GameWorld\GiftCodes\Adapters\DiscordChannelGiftCodeSourceAdapter;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeSourceRegistry;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCodeSourceSubscription;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;
use UnexpectedValueException;

final class DiscordGiftCodeGatewayClient
{
    private const WEBSOCKET_MAGIC = '258EAFA5-E914-47DA-95CA-C5AB0DC85B11';

    public function __construct(private readonly ProcessDiscordGiftCodeGatewayEvent $processor) {}

    /**
     * Run the Discord Gateway until the optional wall-clock budget expires.
     * A maxSeconds value of zero runs until the process is terminated.
     */
    public function run(int $maxSeconds = 0): int
    {
        if (! (bool) config('game_world.gift_codes.discord_gateway_enabled', false)) {
            throw new UnexpectedValueException('Discord Gateway transport is disabled.');
        }
        $token = trim((string) config('game_world.gift_codes.discord_bot_token', ''));
        if ($token === '') {
            throw new UnexpectedValueException('Discord Gateway transport requires a configured bot token.');
        }

        $startedAt = microtime(true);
        $handled = 0;
        $sequence = null;
        $sessionId = null;
        $resumeUrl = null;
        $backoffSeconds = 1;

        while ($maxSeconds === 0 || microtime(true) - $startedAt < $maxSeconds) {
            $socket = null;
            try {
                $gatewayUrl = $resumeUrl ?? $this->gatewayUrl($token);
                $socket = $this->connect($this->gatewayEndpoint($gatewayUrl));
                $hello = $this->receiveJson($socket, 15);
                if (! is_array($hello) || (int) ($hello['op'] ?? -1) !== 10) {
                    throw new RuntimeException('Discord Gateway did not begin with a HELLO payload.');
                }
                $helloData = $hello['d'] ?? null;
                $heartbeatMs = is_array($helloData) ? (int) ($helloData['heartbeat_interval'] ?? 0) : 0;
                if ($heartbeatMs < 1000 || $heartbeatMs > 300_000) {
                    throw new RuntimeException('Discord Gateway returned an invalid heartbeat interval.');
                }

                if ($sessionId !== null && $sequence !== null) {
                    $this->sendJson($socket, [
                        'op' => 6,
                        'd' => [
                            'token' => $token,
                            'session_id' => $sessionId,
                            'seq' => $sequence,
                        ],
                    ]);
                } else {
                    $this->sendJson($socket, [
                        'op' => 2,
                        'd' => [
                            'token' => $token,
                            'intents' => max(0, (int) config('game_world.gift_codes.discord_gateway_intents', 33_281)),
                            'properties' => [
                                'os' => PHP_OS_FAMILY,
                                'browser' => 'kingshot-gift-codes',
                                'device' => 'kingshot-gift-codes',
                            ],
                        ],
                    ]);
                }

                $nextHeartbeatAt = microtime(true) + ($heartbeatMs / 1000);
                $heartbeatAcked = true;
                $backoffSeconds = 1;

                while ($maxSeconds === 0 || microtime(true) - $startedAt < $maxSeconds) {
                    $now = microtime(true);
                    if ($now >= $nextHeartbeatAt) {
                        if (! $heartbeatAcked) {
                            throw new RuntimeException('Discord Gateway heartbeat acknowledgement was not received.');
                        }
                        $this->sendJson($socket, ['op' => 1, 'd' => $sequence]);
                        $heartbeatAcked = false;
                        $nextHeartbeatAt = $now + ($heartbeatMs / 1000);
                    }

                    $waitSeconds = max(1, min(5, (int) ceil($nextHeartbeatAt - microtime(true))));
                    $payload = $this->receiveJson($socket, $waitSeconds);
                    if ($payload === null) {
                        continue;
                    }

                    $op = (int) ($payload['op'] ?? -1);
                    if (isset($payload['s']) && is_int($payload['s'])) {
                        $sequence = $payload['s'];
                    }

                    if ($op === 0) {
                        $eventType = is_string($payload['t'] ?? null) ? $payload['t'] : '';
                        if ($eventType === 'READY') {
                            $data = $payload['d'] ?? null;
                            if (is_array($data)) {
                                $session = $data['session_id'] ?? null;
                                $resume = $data['resume_gateway_url'] ?? null;
                                $sessionId = is_string($session) && $session !== '' ? $session : $sessionId;
                                $resumeUrl = is_string($resume) && str_starts_with($resume, 'wss://') ? $resume : $resumeUrl;
                                $this->markSubscriptionsActive();
                            }
                        }
                        $result = $this->processor->handle($payload);
                        $handled += $result['processed'];

                        continue;
                    }

                    if ($op === 1) {
                        $this->sendJson($socket, ['op' => 1, 'd' => $sequence]);
                        $heartbeatAcked = false;
                        $nextHeartbeatAt = microtime(true) + ($heartbeatMs / 1000);

                        continue;
                    }
                    if ($op === 7) {
                        break;
                    }
                    if ($op === 9) {
                        $resumable = ($payload['d'] ?? false) === true;
                        if (! $resumable) {
                            $sessionId = null;
                            $resumeUrl = null;
                            $sequence = null;
                        }
                        break;
                    }
                    if ($op === 11) {
                        $heartbeatAcked = true;
                    }
                }
            } catch (Throwable $exception) {
                report($exception);
                $this->markSubscriptionsDegraded('gateway_disconnected');
                if ($maxSeconds !== 0 && microtime(true) - $startedAt >= $maxSeconds) {
                    break;
                }
                sleep($backoffSeconds);
                $backoffSeconds = min(30, $backoffSeconds * 2);
            } finally {
                if (is_resource($socket)) {
                    @fclose($socket);
                }
            }
        }

        return $handled;
    }

    private function gatewayUrl(string $token): string
    {
        $response = Http::withHeaders(['Authorization' => 'Bot '.$token, 'Accept' => 'application/json'])
            ->timeout(max(1, min(30, (int) config('game_world.gift_codes.ingestion_timeout_seconds', 10))))
            ->withOptions(['allow_redirects' => false])
            ->get('https://discord.com/api/v10/gateway/bot');
        if (! $response->successful() || ! str_contains(mb_strtolower((string) $response->header('Content-Type')), 'json')) {
            throw new RuntimeException(sprintf('Discord Gateway discovery failed with HTTP %d.', $response->status()));
        }
        $payload = $response->json();
        $url = is_array($payload) && is_string($payload['url'] ?? null) ? trim($payload['url']) : '';
        if (! str_starts_with($url, 'wss://')) {
            throw new RuntimeException('Discord Gateway discovery did not return a secure WebSocket URL.');
        }

        return $url;
    }

    private function gatewayEndpoint(string $base): string
    {
        $separator = str_contains($base, '?') ? '&' : '?';

        return $base.$separator.'v=10&encoding=json';
    }

    /** @return resource */
    private function connect(string $url)
    {
        $parts = parse_url($url);
        if ($parts === false || ($parts['scheme'] ?? null) !== 'wss' || ! is_string($parts['host'] ?? null)) {
            throw new RuntimeException('Discord Gateway URL is invalid.');
        }
        $host = $parts['host'];
        $port = isset($parts['port']) ? (int) $parts['port'] : 443;
        $path = is_string($parts['path'] ?? null) && $parts['path'] !== '' ? $parts['path'] : '/';
        if (isset($parts['query']) && is_string($parts['query']) && $parts['query'] !== '') {
            $path .= '?'.$parts['query'];
        }

        $context = stream_context_create([
            'ssl' => [
                'verify_peer' => true,
                'verify_peer_name' => true,
                'peer_name' => $host,
                'SNI_enabled' => true,
            ],
        ]);
        $errno = 0;
        $errstr = '';
        $socket = @stream_socket_client(
            'tls://'.$host.':'.$port,
            $errno,
            $errstr,
            10,
            STREAM_CLIENT_CONNECT,
            $context,
        );
        if (! is_resource($socket)) {
            throw new RuntimeException(sprintf('Discord Gateway TLS connection failed (%d: %s).', $errno, $errstr));
        }
        stream_set_timeout($socket, 10);

        $key = base64_encode(random_bytes(16));
        $request = implode("\r\n", [
            'GET '.$path.' HTTP/1.1',
            'Host: '.$host,
            'Upgrade: websocket',
            'Connection: Upgrade',
            'Sec-WebSocket-Key: '.$key,
            'Sec-WebSocket-Version: 13',
            'User-Agent: Kingshot-GiftCodes/2',
            '',
            '',
        ]);
        $this->writeAll($socket, $request);

        $headers = '';
        while (! str_contains($headers, "\r\n\r\n")) {
            $chunk = fread($socket, 1024);
            if ($chunk === false || $chunk === '') {
                throw new RuntimeException('Discord Gateway WebSocket handshake ended unexpectedly.');
            }
            $headers .= $chunk;
            if (strlen($headers) > 16_384) {
                throw new RuntimeException('Discord Gateway WebSocket handshake exceeded the header bound.');
            }
        }
        if (preg_match('/^HTTP\/1\.[01] 101\b/m', $headers) !== 1) {
            throw new RuntimeException('Discord Gateway WebSocket handshake was not upgraded.');
        }
        if (preg_match('/^Sec-WebSocket-Accept:\s*(\S+)\s*$/mi', $headers, $matches) !== 1) {
            throw new RuntimeException('Discord Gateway WebSocket handshake omitted Sec-WebSocket-Accept.');
        }
        $expected = base64_encode(sha1($key.self::WEBSOCKET_MAGIC, true));
        if (! hash_equals($expected, trim($matches[1]))) {
            throw new RuntimeException('Discord Gateway WebSocket handshake validation failed.');
        }

        return $socket;
    }

    /**
     * @param resource $socket
     * @return array<string, mixed>|null
     */
    private function receiveJson($socket, int $timeoutSeconds): ?array
    {
        $message = $this->readMessage($socket, $timeoutSeconds);
        if ($message === null) {
            return null;
        }
        try {
            $payload = json_decode($message, true, 64, JSON_THROW_ON_ERROR);
        } catch (\JsonException $exception) {
            throw new RuntimeException('Discord Gateway returned invalid JSON.', previous: $exception);
        }
        if (! is_array($payload)) {
            throw new RuntimeException('Discord Gateway payload must be a JSON object.');
        }

        return $payload;
    }

    /** @param resource $socket */
    private function readMessage($socket, int $timeoutSeconds): ?string
    {
        $buffer = '';
        $started = false;
        while (true) {
            $frame = $this->readFrame($socket, $timeoutSeconds);
            if ($frame === null) {
                return $started ? throw new RuntimeException('Discord Gateway fragmented message timed out.') : null;
            }
            if ($frame['opcode'] === 0x8) {
                throw new RuntimeException('Discord Gateway closed the WebSocket connection.');
            }
            if ($frame['opcode'] === 0x9) {
                $this->writeFrame($socket, $frame['payload'], 0xA);

                continue;
            }
            if ($frame['opcode'] === 0xA) {
                continue;
            }
            if ($frame['opcode'] === 0x1) {
                if ($started) {
                    throw new RuntimeException('Discord Gateway started a second text message before completing the first.');
                }
                $started = true;
                $buffer = $frame['payload'];
            } elseif ($frame['opcode'] === 0x0 && $started) {
                $buffer .= $frame['payload'];
            } else {
                continue;
            }
            if (strlen($buffer) > max(65_536, min(2_000_000, (int) config('game_world.gift_codes.push_payload_max_bytes', 1_000_000)))) {
                throw new RuntimeException('Discord Gateway payload exceeded the configured size bound.');
            }
            if ($frame['fin']) {
                return $buffer;
            }
        }
    }

    /**
     * @param resource $socket
     * @return array{fin: bool, opcode: int, payload: string}|null
     */
    private function readFrame($socket, int $timeoutSeconds): ?array
    {
        $read = [$socket];
        $write = null;
        $except = null;
        $ready = @stream_select($read, $write, $except, max(0, $timeoutSeconds));
        if ($ready === false) {
            throw new RuntimeException('Discord Gateway WebSocket select failed.');
        }
        if ($ready === 0) {
            return null;
        }

        $header = $this->readExact($socket, 2);
        $first = ord($header[0]);
        $second = ord($header[1]);
        $fin = ($first & 0x80) !== 0;
        $opcode = $first & 0x0F;
        $masked = ($second & 0x80) !== 0;
        $length = $second & 0x7F;
        if ($length === 126) {
            $decodedLength = unpack('nlength', $this->readExact($socket, 2));
            if ($decodedLength === false) {
                throw new RuntimeException('Discord Gateway frame length could not be decoded.');
            }
            $length = (int) $decodedLength['length'];
        } elseif ($length === 127) {
            $parts = unpack('Nhigh/Nlow', $this->readExact($socket, 8));
            if (($parts['high'] ?? 0) !== 0) {
                throw new RuntimeException('Discord Gateway frame exceeded the supported payload size.');
            }
            $length = (int) ($parts['low'] ?? 0);
        }
        $maximum = max(65_536, min(2_000_000, (int) config('game_world.gift_codes.push_payload_max_bytes', 1_000_000)));
        if ($length > $maximum) {
            throw new RuntimeException('Discord Gateway frame exceeded the configured payload bound.');
        }
        $mask = $masked ? $this->readExact($socket, 4) : null;
        $payload = $length > 0 ? $this->readExact($socket, $length) : '';
        if ($masked && $mask !== null) {
            $decoded = '';
            for ($i = 0; $i < $length; $i++) {
                $decoded .= $payload[$i] ^ $mask[$i % 4];
            }
            $payload = $decoded;
        }

        return ['fin' => $fin, 'opcode' => $opcode, 'payload' => $payload];
    }

    /**
     * @param resource $socket
     * @param array<string, mixed> $payload
     */
    private function sendJson($socket, array $payload): void
    {
        $this->writeFrame($socket, json_encode($payload, JSON_THROW_ON_ERROR), 0x1);
    }

    /** @param resource $socket */
    private function writeFrame($socket, string $payload, int $opcode): void
    {
        $length = strlen($payload);
        $header = chr(0x80 | ($opcode & 0x0F));
        if ($length < 126) {
            $header .= chr(0x80 | $length);
        } elseif ($length <= 65_535) {
            $header .= chr(0x80 | 126).pack('n', $length);
        } else {
            $header .= chr(0x80 | 127).pack('NN', 0, $length);
        }
        $mask = random_bytes(4);
        $encoded = '';
        for ($i = 0; $i < $length; $i++) {
            $encoded .= $payload[$i] ^ $mask[$i % 4];
        }
        $this->writeAll($socket, $header.$mask.$encoded);
    }

    /** @param resource $socket */
    private function readExact($socket, int $length): string
    {
        $buffer = '';
        while (strlen($buffer) < $length) {
            $remaining = $length - strlen($buffer);
            if ($remaining < 1) {
                break;
            }
            $chunk = fread($socket, $remaining);
            if ($chunk === false || $chunk === '') {
                $meta = stream_get_meta_data($socket);
                throw new RuntimeException($meta['timed_out']
                    ? 'Discord Gateway socket read timed out.'
                    : 'Discord Gateway socket closed while reading a frame.');
            }
            $buffer .= $chunk;
        }

        return $buffer;
    }

    /** @param resource $socket */
    private function writeAll($socket, string $payload): void
    {
        $offset = 0;
        $length = strlen($payload);
        while ($offset < $length) {
            $written = fwrite($socket, substr($payload, $offset));
            if ($written === false || $written === 0) {
                throw new RuntimeException('Discord Gateway socket write failed.');
            }
            $offset += $written;
        }
    }

    private function markSubscriptionsActive(): void
    {
        foreach ($this->discordSources() as $source) {
            $policy = $source->provenance_policy ?? [];
            GiftCodeSourceSubscription::query()->updateOrCreate(
                [
                    'gift_code_source_id' => (string) $source->id,
                    'provider' => 'discord',
                    'transport' => 'gateway',
                ],
                [
                    'topic_or_rule' => 'MESSAGE_CREATE,MESSAGE_UPDATE',
                    'configured_identity' => [
                        'guild_id' => $policy['discord_guild_id'] ?? null,
                        'channel_id' => $policy['discord_channel_id'] ?? null,
                    ],
                    'status' => 'active',
                    'activated_at' => now(),
                    'last_verified_at' => now(),
                    'last_error_code' => null,
                ],
            );
        }
    }

    private function markSubscriptionsDegraded(string $code): void
    {
        $sourceIds = $this->discordSources()->pluck('id')->all();
        if ($sourceIds === []) {
            return;
        }
        GiftCodeSourceSubscription::query()
            ->whereIn('gift_code_source_id', $sourceIds)
            ->where('provider', 'discord')
            ->where('transport', 'gateway')
            ->update(['status' => 'degraded', 'last_error_code' => $code]);
    }

    /** @return Collection<int,GiftCodeSourceRegistry> */
    private function discordSources()
    {
        return GiftCodeSourceRegistry::query()
            ->where('adapter_key', DiscordChannelGiftCodeSourceAdapter::KEY)
            ->where('is_active', true)
            ->where('ingestion_enabled', true)
            ->where('push_enabled', true)
            ->whereNull('revoked_at')
            ->get();
    }
}
