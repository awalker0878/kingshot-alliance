<?php

declare(strict_types=1);

namespace App\Contexts\Platform\Administration\Console\Commands;

use App\Contexts\Accounts\Identity\Models\User;
use App\Contexts\Platform\Administration\Actions\ManagePlatformAdministrator;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

final class GrantPlatformAdministratorCommand extends Command
{
    protected $signature = 'platform:admin:grant {email}';

    protected $description = 'Bootstrap a platform administrator grant.';

    public function handle(ManagePlatformAdministrator $manage): int
    {
        $emailArgument = $this->argument('email');
        if (! is_string($emailArgument)) {
            $this->error('A valid email argument is required.');

            return self::FAILURE;
        }

        $email = Str::lower(trim($emailArgument));
        $user = User::query()->where('email', $email)->first();
        if (! $user instanceof User) {
            $this->error('No user exists with that email address.');

            return self::FAILURE;
        }

        $manage->grant((int) $user->id);
        $this->info('Platform administrator grant created. Web access still requires verified email and MFA.');

        return self::SUCCESS;
    }
}
