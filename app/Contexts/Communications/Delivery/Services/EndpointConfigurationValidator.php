<?php

declare(strict_types=1);

namespace App\Contexts\Communications\Delivery\Services;

use App\Contexts\Communications\Delivery\Enums\DeliveryChannel;
use Illuminate\Validation\ValidationException;

final readonly class EndpointConfigurationValidator
{
    /** @param array<string,string> $configuration
     *  @return array<string,string>
     */
    public function validate(DeliveryChannel $channel, array $configuration): array
    {
        return match ($channel) {
            DeliveryChannel::Discord => $this->discord($configuration),
            DeliveryChannel::Telegram => $this->telegram($configuration),
            DeliveryChannel::WebPush => $this->webPush($configuration),
            DeliveryChannel::InApp, DeliveryChannel::Email => throw ValidationException::withMessages([
                'channel' => 'Choose a configurable external delivery channel.',
            ]),
        };
    }

    /** @param array<string,string> $configuration
     *  @return array{webhook_url:string}
     */
    private function discord(array $configuration): array
    {
        $url = trim($configuration['webhook_url'] ?? '');
        $scheme = mb_strtolower((string) parse_url($url, PHP_URL_SCHEME));
        $host = mb_strtolower((string) parse_url($url, PHP_URL_HOST));
        $path = (string) parse_url($url, PHP_URL_PATH);
        $port = parse_url($url, PHP_URL_PORT);
        $user = parse_url($url, PHP_URL_USER);

        if ($scheme !== 'https'
            || ! in_array($host, ['discord.com', 'www.discord.com', 'discordapp.com'], true)
            || ($port !== null && $port !== 443)
            || $user !== null
            || ! preg_match('#^/api(?:/v\d+)?/webhooks/\d+/[A-Za-z0-9._-]+$#', $path)) {
            throw ValidationException::withMessages([
                'webhook_url' => 'Enter an official HTTPS Discord webhook URL.',
            ]);
        }

        return ['webhook_url' => $url];
    }

    /** @param array<string,string> $configuration
     *  @return array{bot_token:string,chat_id:string}
     */
    private function telegram(array $configuration): array
    {
        $botToken = trim($configuration['bot_token'] ?? '');
        $chatId = trim($configuration['chat_id'] ?? '');
        if (! preg_match('/^\d{6,12}:[A-Za-z0-9_-]{30,}$/', $botToken)) {
            throw ValidationException::withMessages(['bot_token' => 'Enter a valid Telegram bot token.']);
        }
        if (! preg_match('/^-?\d{5,20}$/', $chatId)) {
            throw ValidationException::withMessages(['chat_id' => 'Enter a valid Telegram chat ID.']);
        }

        return ['bot_token' => $botToken, 'chat_id' => $chatId];
    }

    /** @param array<string,string> $configuration
     *  @return array{endpoint:string,p256dh:string,auth:string}
     */
    private function webPush(array $configuration): array
    {
        $endpoint = trim($configuration['endpoint'] ?? '');
        $p256dh = trim($configuration['p256dh'] ?? '');
        $auth = trim($configuration['auth'] ?? '');

        $scheme = mb_strtolower((string) parse_url($endpoint, PHP_URL_SCHEME));
        $host = mb_strtolower((string) parse_url($endpoint, PHP_URL_HOST));
        $port = parse_url($endpoint, PHP_URL_PORT);
        $user = parse_url($endpoint, PHP_URL_USER);
        if ($scheme !== 'https'
            || $host === ''
            || ($port !== null && $port !== 443)
            || $user !== null
            || mb_strlen($endpoint) > 2048
            || ! $this->allowedPushHost($host)) {
            throw ValidationException::withMessages([
                'endpoint' => 'The browser push service endpoint is not supported.',
            ]);
        }

        $publicKey = $this->base64UrlDecode($p256dh);
        $authSecret = $this->base64UrlDecode($auth);
        if ($publicKey === null || strlen($publicKey) !== 65 || ord($publicKey[0]) !== 4) {
            throw ValidationException::withMessages([
                'p256dh' => 'The browser push public key is invalid.',
            ]);
        }
        if ($authSecret === null || strlen($authSecret) !== 16) {
            throw ValidationException::withMessages([
                'auth' => 'The browser push authentication secret is invalid.',
            ]);
        }

        return ['endpoint' => $endpoint, 'p256dh' => $p256dh, 'auth' => $auth];
    }

    private function allowedPushHost(string $host): bool
    {
        $allowed = [
            'fcm.googleapis.com',
            'updates.push.services.mozilla.com',
            'push.services.mozilla.com',
            'web.push.apple.com',
        ];

        $configured = config('services.webpush.allowed_hosts', []);
        if (is_array($configured)) {
            foreach ($configured as $candidate) {
                if (is_string($candidate) && trim($candidate) !== '') {
                    $allowed[] = mb_strtolower(trim($candidate));
                }
            }
        }

        foreach (array_unique($allowed) as $candidate) {
            if ($host === $candidate || str_ends_with($host, '.'.$candidate)) {
                return true;
            }
        }

        return false;
    }

    private function base64UrlDecode(string $value): ?string
    {
        if ($value === '' || ! preg_match('/^[A-Za-z0-9_-]+$/', $value)) {
            return null;
        }

        $padding = (4 - (strlen($value) % 4)) % 4;
        $decoded = base64_decode(strtr($value.str_repeat('=', $padding), '-_', '+/'), true);

        return is_string($decoded) ? $decoded : null;
    }
}
