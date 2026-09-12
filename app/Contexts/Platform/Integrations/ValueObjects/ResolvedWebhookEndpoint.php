<?php

declare(strict_types=1);

namespace App\Contexts\Platform\Integrations\ValueObjects;

final readonly class ResolvedWebhookEndpoint
{
    public function __construct(
        public string $url,
        public string $host,
        public string $address,
    ) {}

    public function curlResolution(): string
    {
        $address = str_contains($this->address, ':') ? '['.$this->address.']' : $this->address;

        return $this->host.':443:'.$address;
    }
}
