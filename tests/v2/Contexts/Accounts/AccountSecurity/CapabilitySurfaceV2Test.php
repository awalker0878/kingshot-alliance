<?php

declare(strict_types=1);

namespace Tests\v2\Contexts\Accounts\AccountSecurity;

use Tests\v2\Support\CapabilitySurfaceTestCase;

final class CapabilitySurfaceV2Test extends CapabilitySurfaceTestCase
{
    protected const CAPABILITY = 'Contexts/Accounts/AccountSecurity';

    protected const SOURCES = [
        'app/Contexts/Accounts',
    ];

    protected const DOCUMENTATION = 'docs/architecture/contexts/accounts/account-security.md';
}
