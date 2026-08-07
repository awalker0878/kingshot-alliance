<?php

declare(strict_types=1);

namespace Tests\Feature\Platform;

use App\Domain\Alliances\Actions\CreateAlliance;
use App\Domain\Alliances\Enums\AllianceStatus;
use App\Domain\Identity\Models\User;
use App\Domain\Platform\Actions\ManageAllianceLifecycle;
use App\Domain\Platform\Actions\ManagePlatformAdministrator;
use App\Domain\Platform\Services\LegalHoldService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use InvalidArgumentException;
use Tests\TestCase;

final class PlatformAdministrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_alliance_owner_is_not_a_platform_administrator(): void
    {
        $owner = User::factory()->create(['two_factor_confirmed_at' => now()]);
        $this->app->make(CreateAlliance::class)->handle($owner, 'Owner Tenant', 'owner-tenant');

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

    public function test_suspension_invalidates_member_tenant_context_and_restore_reenables_it(): void
    {
        $administrator = User::factory()->create(['two_factor_confirmed_at' => now()]);
        $owner = User::factory()->create();
        $alliance = $this->app->make(CreateAlliance::class)->handle($owner, 'Lifecycle Tenant', 'lifecycle-tenant');
        $lifecycle = $this->app->make(ManageAllianceLifecycle::class);
        $sessionKey = (string) config('identity.active_alliance_session_key');

        $lifecycle->suspend($administrator, $alliance, 'Operational investigation');
        self::assertSame(AllianceStatus::Suspended, $alliance->refresh()->status);

        $this->actingAs($owner)
            ->withSession([$sessionKey => $alliance->id])
            ->get('/alliance')
            ->assertForbidden();

        $lifecycle->restore($administrator, $alliance->refresh(), 'Investigation complete');
        self::assertSame(AllianceStatus::Active, $alliance->refresh()->status);
    }

    public function test_legal_hold_blocks_logical_alliance_deletion(): void
    {
        $administrator = User::factory()->create(['two_factor_confirmed_at' => now()]);
        $owner = User::factory()->create();
        $alliance = $this->app->make(CreateAlliance::class)->handle($owner, 'Held Tenant', 'held-tenant');
        $lifecycle = $this->app->make(ManageAllianceLifecycle::class);
        $lifecycle->close($administrator, $alliance, 'Closure requested');
        $this->app->make(LegalHoldService::class)->place($administrator, 'alliance', (string) $alliance->id, 'Preserve records');

        $this->expectException(InvalidArgumentException::class);
        $lifecycle->delete($administrator, $alliance->refresh(), 'Delete after closure');
    }
}
