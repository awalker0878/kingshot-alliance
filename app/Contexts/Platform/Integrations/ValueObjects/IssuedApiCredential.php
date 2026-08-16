<?php

declare(strict_types=1);

namespace App\Contexts\Platform\Integrations\ValueObjects;

use App\Contexts\Platform\Integrations\Models\ApiCredential;

final readonly class IssuedApiCredential
{
    public function __construct(
        public ApiCredential $credential,
        public string $token,
    ) {}
}
