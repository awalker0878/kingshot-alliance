<?php

declare(strict_types=1);

namespace App\Contexts\Platform\Integrations\Services;

use App\Contexts\Platform\Integrations\ValueObjects\ResolvedWebhookEndpoint;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\IpUtils;

final readonly class WebhookEndpointPolicy
{
    public function __construct(private WebhookHostResolver $resolver) {}

    public function assertAllowed(string $url): void
    {
        $this->parse($url);
    }

    public function resolveAllowed(string $url): ResolvedWebhookEndpoint
    {
        [$canonicalUrl, $host, $port] = $this->parse($url);
        $addresses = $this->resolver->resolve($host);
        if ($addresses === [] || count($addresses) > 64) {
            $this->deny();
        }
        foreach ($addresses as $address) {
            if (! $this->isPublicAddress($address)) {
                $this->deny();
            }
        }

        return new ResolvedWebhookEndpoint($canonicalUrl, $host, $port, $addresses[0]);
    }

    /** @return array{string, string, int} */
    private function parse(string $url): array
    {
        if (strlen($url) > 2048 || preg_match('/[\\x00-\\x20\\x7f\\\\\\x80-\\xff]/', $url)) {
            $this->deny();
        }
        $parts = parse_url($url);
        if (! is_array($parts) || ($parts['scheme'] ?? null) !== 'https'
            || ! is_string($parts['host'] ?? null) || $parts['host'] === ''
            || isset($parts['user']) || isset($parts['pass']) || isset($parts['fragment'])) {
            $this->deny();
        }
        $port = $parts['port'] ?? 443;
        if ($port < 1 || $port > 65535) {
            $this->deny();
        }
        $host = strtolower(rtrim($parts['host'], '.'));
        if (str_starts_with($host, '[') && str_ends_with($host, ']')) {
            $host = substr($host, 1, -1);
        }
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            if (! $this->isPublicAddress($host)) {
                $this->deny();
            }
        } elseif (strlen($host) > 253
            || ! preg_match('/^(?:[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?\\.)+[a-z][a-z0-9-]{0,61}[a-z0-9]$/D', $host)
            || str_ends_with($host, '.localhost') || str_ends_with($host, '.local')) {
            $this->deny();
        }
        $authority = str_contains($host, ':') ? '['.$host.']' : $host;
        $canonicalUrl = 'https://'.$authority.($port === 443 ? '' : ':'.$port)
            .($parts['path'] ?? '/').(isset($parts['query']) ? '?'.$parts['query'] : '');

        return [$canonicalUrl, $host, $port];
    }

    private function isPublicAddress(string $address): bool
    {
        if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
            return ! IpUtils::checkIp($address, [
                '0.0.0.0/8', '10.0.0.0/8', '100.64.0.0/10', '127.0.0.0/8',
                '169.254.0.0/16', '172.16.0.0/12', '192.0.0.0/24', '192.0.2.0/24',
                '192.88.99.0/24', '192.168.0.0/16', '198.18.0.0/15',
                '198.51.100.0/24', '203.0.113.0/24', '224.0.0.0/3',
            ]);
        }

        // Only ordinary allocated global unicast: exclude mapped/translated
        // IPv4, local scopes, protocol assignments, 6to4 and documentation.
        return filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_IPV6) !== false
            && IpUtils::checkIp($address, '2000::/3')
            && ! IpUtils::checkIp($address, ['2001::/23', '2001:db8::/32', '2002::/16', '3fff::/20']);
    }

    private function deny(): never
    {
        throw ValidationException::withMessages([
            'url' => 'Webhook endpoints must use HTTPS with a public destination and no credentials or fragment.',
        ]);
    }
}
