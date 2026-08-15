<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use App\Domain\Alliances\Actions\CreateAlliance;
use App\Domain\Alliances\Enums\AllianceStatus;
use App\Contexts\Accounts\Models\User;
use App\Contexts\GameWorld\Models\Kingdom;
use App\Contexts\GameWorld\Models\Player;
use App\Domain\Platform\Actions\ManageAllianceLifecycle;
use App\Domain\Platform\Actions\ManagePlatformAdministrator;
use App\Domain\Platform\Services\LegalHoldService;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

final class PlatformAdministrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_alliance_r5_account_is_not_a_platform_administrator(): void
    {
        $owner = User::factory()->create(['two_factor_confirmed_at' => now()]);
        $kingdom = Kingdom::query()->create(['number' => 4701]);
        $ownerPlayer = Player::query()->create([
            'user_id' => $owner->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => 'platform-boundary-r5',
            'current_name' => 'Platform Boundary R5',
        ]);
        $this->app->make(CreateAlliance::class)->handle($ownerPlayer, 'Owner Tenant', 'owner-tenant');

        $this->actingAs($owner)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->get('/platform')
            ->assertForbidden();
    }

    public function test_platform_administrator_requires_mfa_and_recent_password_confirmation(): void
    {
        $administrator = User::factory()->create();
        $this->app->make(ManagePlatformAdministrator::class)->grant($administrator);

        $this->actingAs($administrator)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->get('/platform')
            ->assertForbidden();

        $administrator->forceFill(['two_factor_confirmed_at' => now()])->save();

        $this->actingAs($administrator)
            ->withSession(['auth.password_confirmed_at' => 0])
            ->get('/platform')
            ->assertRedirect(route('password.confirm'));
    }

    public function test_platform_administrator_can_open_console_after_mfa_and_password_confirmation(): void
    {
        $administrator = User::factory()->create(['two_factor_confirmed_at' => now()]);
        $this->app->make(ManagePlatformAdministrator::class)->grant($administrator);

        $this->actingAs($administrator)
            ->withSession(['auth.password_confirmed_at' => time()])
            ->get('/platform')
            ->assertOk();
    }

    public function test_platform_lifecycle_suspension_blocks_player_alliance_context_and_restore_reenables_it(): void
    {
        $administrator = User::factory()->create(['two_factor_confirmed_at' => now()]);
        $this->app->make(ManagePlatformAdministrator::class)->grant($administrator);
        $owner = User::factory()->create();
        $kingdom = Kingdom::query()->create(['number' => 4711]);
        $ownerPlayer = Player::query()->create([
            'user_id' => $owner->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => 'platform-lifecycle-r5',
            'current_name' => 'Platform Lifecycle R5',
        ]);
        $alliance = $this->app->make(CreateAlliance::class)->handle($ownerPlayer, 'Lifecycle Tenant', 'lifecycle-tenant');
        $lifecycle = $this->app->make(ManageAllianceLifecycle::class);
        $sessionKey = (string) config('game_world.active_player_session_key');

        $lifecycle->suspend($administrator, $alliance, 'Operational investigation');
        self::assertSame(AllianceStatus::Suspended, $alliance->refresh()->status);

        $this->actingAs($owner)
            ->withSession([$sessionKey => $ownerPlayer->id])
            ->get('/alliance')
            ->assertForbidden();

        $lifecycle->restore($administrator, $alliance->refresh(), 'Investigation complete');
        self::assertSame(AllianceStatus::Active, $alliance->refresh()->status);

        $this->actingAs($owner)
            ->withSession([$sessionKey => $ownerPlayer->id])
            ->get('/alliance')
            ->assertOk();
    }

    public function test_platform_actions_reject_a_user_without_platform_administrator_authority(): void
    {
        $actor = User::factory()->create(['two_factor_confirmed_at' => now()]);
        $owner = User::factory()->create();
        $kingdom = Kingdom::query()->create(['number' => 4712]);
        $ownerPlayer = Player::query()->create([
            'user_id' => $owner->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => 'platform-direct-boundary-r5',
            'current_name' => 'Platform Direct Boundary R5',
        ]);
        $alliance = $this->app->make(CreateAlliance::class)->handle($ownerPlayer, 'Direct Boundary', 'direct-boundary');

        $this->expectException(AuthorizationException::class);
        $this->app->make(ManageAllianceLifecycle::class)->suspend($actor, $alliance, 'Unauthorized direct call');
    }

    public function test_legal_hold_blocks_logical_alliance_deletion(): void
    {
        $administrator = User::factory()->create(['two_factor_confirmed_at' => now()]);
        $this->app->make(ManagePlatformAdministrator::class)->grant($administrator);
        $owner = User::factory()->create();
        $kingdom = Kingdom::query()->create(['number' => 4721]);
        $ownerPlayer = Player::query()->create([
            'user_id' => $owner->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => 'platform-held-r5',
            'current_name' => 'Platform Held R5',
        ]);
        $alliance = $this->app->make(CreateAlliance::class)->handle($ownerPlayer, 'Held Tenant', 'held-tenant');
        $lifecycle = $this->app->make(ManageAllianceLifecycle::class);
        $lifecycle->close($administrator, $alliance, 'Closure requested');
        $this->app->make(LegalHoldService::class)->place($administrator, 'alliance', (string) $alliance->id, 'Preserve records');

        $this->expectException(InvalidArgumentException::class);
        $lifecycle->delete($administrator, $alliance->refresh(), 'Delete after closure');
    }
}
