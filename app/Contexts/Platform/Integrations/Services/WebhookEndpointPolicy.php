<?php

declare(strict_types=1);

namespace App\Contexts\Platform\Integrations\Services;

use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\IpUtils;

final class WebhookEndpointPolicy
{
    public function assertAllowed(string $url): void
    {
        $parts = parse_url($url);
        $scheme = is_array($parts) ? ($parts['scheme'] ?? null) : null;
        $host = is_array($parts) ? ($parts['host'] ?? null) : null;

        if ($scheme !== 'https' || ! is_string($host) || $host === '') {
            throw ValidationException::withMessages([
                'url' => 'Webhook endpoints must use HTTPS and include a valid host.',
            ]);
        }

        $normalizedHost = strtolower(rtrim($host, '.'));
        if ($normalizedHost === 'localhost' || str_ends_with($normalizedHost, '.localhost') || str_ends_with($normalizedHost, '.local')) {
            throw ValidationException::withMessages(['url' => 'Local webhook endpoints are not permitted.']);
        }

        if (filter_var($normalizedHost, FILTER_VALIDATE_IP) !== false
            && IpUtils::checkIp($normalizedHost, [
                '0.0.0.0/8',
                '10.0.0.0/8',
                '100.64.0.0/10',
                '127.0.0.0/8',
                '169.254.0.0/16',
                '172.16.0.0/12',
                '192.0.0.0/24',
                '192.168.0.0/16',
                '224.0.0.0/4',
                '::/128',
                '::1/128',
                'fc00::/7',
                'fe80::/10',
                'ff00::/8',
            ])) {
            throw ValidationException::withMessages(['url' => 'Private or reserved webhook destinations are not permitted.']);
        }
    }
}
