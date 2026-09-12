<?php

declare(strict_types=1);

namespace App\Contexts\Platform\Administration\Console\Commands;

use App\Contexts\Accounts\Identity\Queries\AccountIdentityQuery;
use App\Contexts\Platform\Administration\Actions\ManagePlatformAdministrator;
use Illuminate\Console\Command;
use InvalidArgumentException;

final class GrantPlatformAdministratorCommand extends Command
{
    protected $signature = 'platform:admin:grant {email}';

    protected $description = 'Bootstrap a platform administrator grant.';

    public function handle(ManagePlatformAdministrator $manage, AccountIdentityQuery $accounts): int
    {
        $emailArgument = $this->argument('email');
        if (! is_string($emailArgument)) {
            $this->error('A valid email argument is required.');

            return self::FAILURE;
        }

        $userId = $accounts->findIdByEmail($emailArgument);
        if ($userId === null) {
            $this->error('No user exists with that email address.');

            return self::FAILURE;
        }

        try {
            $manage->grant($userId);
        } catch (InvalidArgumentException $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }
        $this->info('Platform administrator grant created. Web access still requires verified email and MFA.');

        return self::SUCCESS;
    }
}
