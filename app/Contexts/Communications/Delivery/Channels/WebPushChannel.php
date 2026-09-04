<?php

declare(strict_types=1);

namespace App\Contexts\Communications\Delivery\Channels;

use App\Contexts\Communications\Delivery\Contracts\ExternalDeliveryChannel;
use App\Contexts\Communications\Delivery\Enums\DeliveryChannel;
use App\Contexts\Communications\Delivery\ValueObjects\DeliveryAttempt;
use App\Contexts\Communications\Delivery\ValueObjects\DeliveryOutcome;
use Carbon\CarbonImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use OpenSSLAsymmetricKey;
use RuntimeException;
use Throwable;

final class WebPushChannel implements ExternalDeliveryChannel
{
    private const P256_SPKI_PREFIX_HEX = '3059301306072a8648ce3d020106082a8648ce3d030107034200';

    public function channel(): DeliveryChannel
    {
        return DeliveryChannel::WebPush;
    }

    public function deliver(DeliveryAttempt $attempt, array $configuration): DeliveryOutcome
    {
        $endpoint = trim($configuration['endpoint'] ?? '');
        $clientPublic = $this->decodeBase64Url($configuration['p256dh'] ?? '');
        $authSecret = $this->decodeBase64Url($configuration['auth'] ?? '');
        if ($endpoint === '' || $clientPublic === null || $authSecret === null) {
            return DeliveryOutcome::failed('Web Push subscription is incomplete.', false);
        }

        $vapidPublic = trim((string) config('services.webpush.public_key', ''));
        $vapidPrivate = trim((string) config('services.webpush.private_key', ''));
        $vapidSubject = trim((string) config('services.webpush.subject', ''));
        if ($vapidPublic === '' || $vapidPrivate === '' || $vapidSubject === '') {
            return DeliveryOutcome::failed('Web Push VAPID configuration is incomplete.', false);
        }

        try {
            $payload = json_encode([
                'title' => $attempt->title(),
                'body' => $attempt->body(),
                'action_url' => $attempt->actionUrl(),
                'notification_type' => $attempt->notificationType,
                'message_id' => $attempt->messageId,
            ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            if (strlen($payload) > 3000) {
                $payload = json_encode([
                    'title' => mb_substr($attempt->title(), 0, 200),
                    'body' => mb_substr($attempt->body(), 0, 1200),
                    'action_url' => $attempt->actionUrl(),
                    'notification_type' => $attempt->notificationType,
                    'message_id' => $attempt->messageId,
                ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
            }

            $body = $this->encryptPayload($payload, $clientPublic, $authSecret);
            $jwt = $this->vapidJwt($endpoint, $vapidPrivate, $vapidSubject);
        } catch (Throwable $exception) {
            return DeliveryOutcome::failed($exception->getMessage(), false);
        }

        try {
            $response = Http::withBody($body, 'application/octet-stream')
                ->withHeaders([
                    'Authorization' => 'vapid t='.$jwt.', k='.$vapidPublic,
                    'Content-Encoding' => 'aes128gcm',
                    'TTL' => '2419200',
                    'Urgency' => 'normal',
                ])
                ->timeout(10)
                ->post($endpoint);
        } catch (ConnectionException $exception) {
            return DeliveryOutcome::failed($exception->getMessage(), true);
        } catch (Throwable $exception) {
            return DeliveryOutcome::failed($exception->getMessage(), false);
        }

        if ($response->successful()) {
            return DeliveryOutcome::delivered();
        }

        $retryAfter = $response->header('Retry-After');
        $retryAt = is_numeric($retryAfter)
            ? CarbonImmutable::now('UTC')->addSeconds(max(1, (int) ceil((float) $retryAfter)))
            : null;
        $retryable = $response->status() === 429 || $response->status() >= 500;
        $error = in_array($response->status(), [404, 410], true)
            ? 'Web Push subscription is no longer accepted by the push service.'
            : 'Web Push service returned HTTP '.$response->status().'.';

        return DeliveryOutcome::failed($error, $retryable, $retryAt);
    }

    private function encryptPayload(string $payload, string $clientPublic, string $authSecret): string
    {
        if (strlen($clientPublic) !== 65 || ord($clientPublic[0]) !== 4 || strlen($authSecret) !== 16) {
            throw new RuntimeException('Web Push subscription key material is invalid.');
        }

        $serverKey = openssl_pkey_new([
            'private_key_type' => OPENSSL_KEYTYPE_EC,
            'curve_name' => 'prime256v1',
        ]);
        if (! $serverKey instanceof OpenSSLAsymmetricKey) {
            throw new RuntimeException('Unable to create Web Push ephemeral key.');
        }
        $details = openssl_pkey_get_details($serverKey);
        $x = is_array($details) && is_array($details['ec'] ?? null) ? ($details['ec']['x'] ?? null) : null;
        $y = is_array($details) && is_array($details['ec'] ?? null) ? ($details['ec']['y'] ?? null) : null;
        if (! is_string($x) || ! is_string($y)) {
            throw new RuntimeException('Unable to export Web Push ephemeral key.');
        }
        $serverPublic = "\x04".str_pad($x, 32, "\0", STR_PAD_LEFT).str_pad($y, 32, "\0", STR_PAD_LEFT);

        $clientPem = "-----BEGIN PUBLIC KEY-----\n"
            .chunk_split(base64_encode(hex2bin(self::P256_SPKI_PREFIX_HEX).$clientPublic), 64, "\n")
            ."-----END PUBLIC KEY-----\n";
        $clientKey = openssl_pkey_get_public($clientPem);
        if (! $clientKey instanceof OpenSSLAsymmetricKey) {
            throw new RuntimeException('Unable to load Web Push client public key.');
        }
        $sharedSecret = openssl_pkey_derive($clientKey, $serverKey, 32);
        if (! is_string($sharedSecret) || strlen($sharedSecret) !== 32) {
            throw new RuntimeException('Unable to derive Web Push shared secret.');
        }

        $authPrk = hash_hmac('sha256', $sharedSecret, $authSecret, true);
        $ikm = $this->hkdfExpand($authPrk, "WebPush: info\0".$clientPublic.$serverPublic, 32);
        $salt = random_bytes(16);
        $contentPrk = hash_hmac('sha256', $ikm, $salt, true);
        $cek = $this->hkdfExpand($contentPrk, "Content-Encoding: aes128gcm\0", 16);
        $nonce = $this->hkdfExpand($contentPrk, "Content-Encoding: nonce\0", 12);

        $tag = '';
        $ciphertext = openssl_encrypt($payload."\x02", 'aes-128-gcm', $cek, OPENSSL_RAW_DATA, $nonce, $tag);
        if (! is_string($ciphertext) || strlen($tag) !== 16) {
            throw new RuntimeException('Unable to encrypt Web Push payload.');
        }

        return $salt.pack('N', 4096).chr(strlen($serverPublic)).$serverPublic.$ciphertext.$tag;
    }

    private function vapidJwt(string $endpoint, string $privateKey, string $subject): string
    {
        $scheme = mb_strtolower((string) parse_url($endpoint, PHP_URL_SCHEME));
        $host = mb_strtolower((string) parse_url($endpoint, PHP_URL_HOST));
        $port = parse_url($endpoint, PHP_URL_PORT);
        if ($scheme !== 'https' || $host === '') {
            throw new RuntimeException('Web Push endpoint origin is invalid.');
        }
        $audience = $scheme.'://'.$host.(is_int($port) && $port !== 443 ? ':'.$port : '');

        $pem = str_contains($privateKey, 'BEGIN PRIVATE KEY') || str_contains($privateKey, 'BEGIN EC PRIVATE KEY')
            ? str_replace('\\n', "\n", $privateKey)
            : base64_decode($privateKey, true);
        if (! is_string($pem) || trim($pem) === '') {
            throw new RuntimeException('Web Push VAPID private key is invalid.');
        }
        $key = openssl_pkey_get_private($pem);
        if (! $key instanceof OpenSSLAsymmetricKey) {
            throw new RuntimeException('Web Push VAPID private key cannot be loaded.');
        }

        $header = $this->encodeBase64Url(json_encode(['typ' => 'JWT', 'alg' => 'ES256'], JSON_THROW_ON_ERROR));
        $claims = $this->encodeBase64Url(json_encode([
            'aud' => $audience,
            'exp' => time() + 43200,
            'sub' => $subject,
        ], JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));
        $signingInput = $header.'.'.$claims;
        $derSignature = '';
        if (! openssl_sign($signingInput, $derSignature, $key, OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('Unable to sign Web Push VAPID token.');
        }

        return $signingInput.'.'.$this->encodeBase64Url($this->ecdsaDerToRaw($derSignature));
    }

    private function hkdfExpand(string $prk, string $info, int $length): string
    {
        $output = '';
        $block = '';
        $counter = 1;
        while (strlen($output) < $length) {
            $block = hash_hmac('sha256', $block.$info.chr($counter), $prk, true);
            $output .= $block;
            $counter++;
        }

        return substr($output, 0, $length);
    }

    private function ecdsaDerToRaw(string $der): string
    {
        $offset = 0;
        if (ord($der[$offset++] ?? "\0") !== 0x30) {
            throw new RuntimeException('VAPID signature is not a DER sequence.');
        }
        $this->readDerLength($der, $offset);
        if (ord($der[$offset++] ?? "\0") !== 0x02) {
            throw new RuntimeException('VAPID signature R value is invalid.');
        }
        $rLength = $this->readDerLength($der, $offset);
        $r = substr($der, $offset, $rLength);
        $offset += $rLength;
        if (ord($der[$offset++] ?? "\0") !== 0x02) {
            throw new RuntimeException('VAPID signature S value is invalid.');
        }
        $sLength = $this->readDerLength($der, $offset);
        $s = substr($der, $offset, $sLength);

        return str_pad(ltrim($r, "\0"), 32, "\0", STR_PAD_LEFT)
            .str_pad(ltrim($s, "\0"), 32, "\0", STR_PAD_LEFT);
    }

    private function readDerLength(string $der, int &$offset): int
    {
        $length = ord($der[$offset++] ?? "\0");
        if (($length & 0x80) === 0) {
            return $length;
        }
        $bytes = $length & 0x7F;
        if ($bytes < 1 || $bytes > 2) {
            throw new RuntimeException('VAPID DER signature length is invalid.');
        }
        $length = 0;
        for ($i = 0; $i < $bytes; $i++) {
            $length = ($length << 8) | ord($der[$offset++] ?? "\0");
        }

        return $length;
    }

    private function decodeBase64Url(string $value): ?string
    {
        $padding = (4 - (strlen($value) % 4)) % 4;
        $decoded = base64_decode(strtr($value.str_repeat('=', $padding), '-_', '+/'), true);

        return is_string($decoded) ? $decoded : null;
    }

    private function encodeBase64Url(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
