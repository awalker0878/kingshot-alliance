<?php

declare(strict_types=1);

namespace Tests\v3\Fixtures;

use App\Contexts\Accounts\Identity\Models\User;
use App\Contexts\GameWorld\GiftCodes\Models\GiftCode;
use App\Contexts\GameWorld\Kingdoms\Models\Kingdom;
use App\Contexts\GameWorld\Players\Models\Player;
use App\Contexts\Platform\Administration\Actions\ManagePlatformAdministrator;
use Illuminate\Support\Facades\Hash;

final class GiftCodeVisualFixture
{
    public static function seed(): void
    {
        $catalogueUser = User::factory()->create([
            'name' => 'Gift Code Catalogue Visual',
            'email' => 'gift-code-catalogue-visual@example.test',
            'password' => Hash::make('password'),
            'timezone' => 'UTC',
        ]);
        $kingdoms = [
            Kingdom::query()->create(['number' => 1623, 'status' => 'active']),
            Kingdom::query()->create(['number' => 1624, 'status' => 'active']),
        ];
        foreach ($kingdoms as $index => $kingdom) {
            Player::query()->create([
                'user_id' => $catalogueUser->id,
                'current_kingdom_id' => $kingdom->id,
                'game_player_id' => sprintf('GOV-GIFT-%d', $index + 1),
                'current_name' => sprintf('Gift Code Governor %d', $index + 1),
            ]);
        }
        foreach (['VISUAL-GIFT-DESKTOP', 'VISUAL-GIFT-MOBILE'] as $code) {
            GiftCode::query()->create([
                'code' => $code,
                'normalized_code' => $code,
                'status' => 'valid',
                'status_revision' => 1,
                'status_reason_code' => 'qualified_positive_evidence',
                'status_evidence_ids' => [],
                'status_changed_at' => now(),
                'status_derived_at' => now(),
                'discovered_at' => now(),
                'expires_revision' => 0,
            ]);
        }

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
