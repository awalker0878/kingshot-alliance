<?php

declare(strict_types=1);

namespace App\Contexts\Platform\Administration\Actions;

use App\Contexts\Accounts\Identity\ValueObjects\AccountIdentity;
use App\Contexts\Platform\Administration\Services\PlatformAuthorization;
use App\Contexts\Platform\Administration\Services\PlatformWriteState;

/** Owner barrier for explicitly atomic operator Workflows; requires their outer transaction. */
final readonly class AuthorizePlatformOperatorWrite
{
    public function __construct(private PlatformWriteState $writeState, private PlatformAuthorization $authorization) {}

    public function handle(AccountIdentity $actor): void
    {
        $this->authorization->authorizeContext($this->writeState->lock($actor));
    }
}
