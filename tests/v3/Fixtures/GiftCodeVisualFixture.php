<?php

declare(strict_types=1);

namespace Tests\v3\Fixtures;

use App\Contexts\Accounts\Identity\Models\User;
use App\Contexts\Platform\Administration\Actions\ManagePlatformAdministrator;

final class GiftCodeVisualFixture
{
    public static function seed(): void
    {
        $user = User::factory()->create([
            'name' => 'Gift Code Moderation Visual',
            'email' => 'gift-code-moderation-visual@example.test',
            'timezone' => 'UTC',
            'email_verified_at' => now(),
            'two_factor_confirmed_at' => now(),
        ]);
        app(ManagePlatformAdministrator::class)->grant((int) $user->id);
    }
}
