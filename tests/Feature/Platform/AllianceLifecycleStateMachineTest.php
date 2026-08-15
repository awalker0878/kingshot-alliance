<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use App\Domain\Alliances\Actions\CreateAlliance;
use App\Domain\Alliances\Enums\AllianceStatus;
use App\Domain\Alliances\Models\Alliance;
use App\Contexts\Accounts\Models\User;
use App\Contexts\GameWorld\Models\Kingdom;
use App\Contexts\GameWorld\Models\Player;
use App\Domain\Platform\Actions\ManageAllianceLifecycle;
use App\Domain\Platform\Actions\ManagePlatformAdministrator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

final class AllianceLifecycleStateMachineTest extends TestCase
{
    use RefreshDatabase;

    public function test_stale_active_model_cannot_overwrite_a_newer_closed_state_with_suspended(): void
    {
        $administrator = User::factory()->create();
        $this->app->make(ManagePlatformAdministrator::class)->grant($administrator);

        $owner = User::factory()->create();
        $kingdom = Kingdom::query()->create(['number' => 7720, 'status' => 'active']);
        $ownerPlayer = Player::query()->create([
            'user_id' => $owner->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => 'lifecycle-stale-owner',
            'current_name' => 'Lifecycle Owner',
        ]);
        $alliance = $this->app->make(CreateAlliance::class)
            ->handle($ownerPlayer, 'Lifecycle Race', 'lifecycle-race');

        self::assertSame(AllianceStatus::Active, $alliance->status);

        Alliance::query()->whereKey($alliance->id)->update([
            'status' => AllianceStatus::Closed->value,
            'closed_at' => now(),
        ]);

        try {
            $this->app->make(ManageAllianceLifecycle::class)
                ->suspend($administrator, $alliance, 'Stale caller must fail');
            self::fail('A stale active model must not overwrite a newer closed lifecycle state.');
        } catch (InvalidArgumentException) {
            // Expected: the locked database state is authoritative.
        }

        self::assertSame(
            AllianceStatus::Closed,
            Alliance::query()->findOrFail($alliance->id)->status,
        );
    }
}
