<?php

declare(strict_types=1);

namespace App\Domain\Integrations\ValueObjects;

use App\Domain\Integrations\Models\ApiCredential;

final readonly class IssuedApiCredential
{
    public function __construct(
        public ApiCredential $credential,
        public string $token,
    ) {}
}
