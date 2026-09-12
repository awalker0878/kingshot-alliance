<?php

declare(strict_types=1);

namespace App\Contexts\Platform\Integrations\Services;

use App\Contexts\Platform\Integrations\ValueObjects\ResolvedWebhookEndpoint;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\IpUtils;

final class WebhookEndpointPolicy
{
    public function __construct(private readonly WebhookHostResolver $resolver) {}

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

        if (filter_var($normalizedHost, FILTER_VALIDATE_IP) !== false && ! $this->isPublicAddress($normalizedHost)) {
            throw ValidationException::withMessages(['url' => 'Private or reserved webhook destinations are not permitted.']);
        }
    }

    public function resolveAllowed(string $url): ResolvedWebhookEndpoint
    {
        $this->assertAllowed($url);
        $host = strtolower(rtrim((string) parse_url($url, PHP_URL_HOST), '.'));
        $addresses = $this->resolver->resolve($host);
        if ($addresses === [] || array_filter($addresses, fn (string $address): bool => ! $this->isPublicAddress($address)) !== []) {
            throw ValidationException::withMessages([
                'url' => 'Webhook destination DNS must resolve only to public addresses.',
            ]);
        }

        return new ResolvedWebhookEndpoint($url, $host, $addresses[0]);
    }

    private function isPublicAddress(string $address): bool
    {
        return filter_var($address, FILTER_VALIDATE_IP) !== false
            && ! IpUtils::checkIp($address, [
                '0.0.0.0/8',
                '10.0.0.0/8',
                '100.64.0.0/10',
                '127.0.0.0/8',
                '169.254.0.0/16',
                '172.16.0.0/12',
                '192.0.0.0/24',
                '192.168.0.0/16',
                '192.0.2.0/24',
                '198.18.0.0/15',
                '198.51.100.0/24',
                '203.0.113.0/24',
                '224.0.0.0/4',
                '240.0.0.0/4',
                '::/128',
                '::1/128',
                'fc00::/7',
                'fe80::/10',
                '2001:db8::/32',
                'ff00::/8',
            ]);
    }
}
