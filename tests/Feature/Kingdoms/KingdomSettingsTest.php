<?php

declare(strict_types=1);

namespace Tests\Feature\Kingdoms;

use App\Domain\Alliances\Actions\CreateAlliance;
use App\Domain\Authorization\Enums\DefaultAllianceRole;
use App\Domain\Authorization\Models\Role;
use App\Domain\Identity\Models\User;
use App\Domain\Kingdoms\Enums\KingdomStatus;
use App\Domain\Kingdoms\Models\Kingdom;
use App\Domain\Memberships\Enums\MembershipStatus;
use App\Domain\Memberships\Models\AllianceMembership;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

final class KingdomSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_view_and_update_the_active_alliance_kingdom(): void
    {
        $owner = User::factory()->create();
        $alliance = $this->app->make(CreateAlliance::class)
            ->handle($owner, 'Kingdom Settings', 'kingdom-settings', 1200);
        $sessionKey = (string) config('identity.active_alliance_session_key');

        $this->actingAs($owner)
            ->withSession([$sessionKey => $alliance->id])
            ->get('/alliance/settings/kingdom')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Alliance/KingdomSettings')
                ->where('alliance.id', $alliance->id)
                ->where('alliance.kingdom', '1200'));

        $this->withSession([
            $sessionKey => $alliance->id,
            'auth.password_confirmed_at' => time(),
        ])->patch('/alliance/settings/kingdom', [
            'kingdom' => 1300,
        ])->assertRedirect();

        $alliance->refresh()->load('kingdom');
        self::assertSame(1300, $alliance->kingdom?->number);
        $this->assertDatabaseHas('audit_events', [
            'alliance_id' => $alliance->id,
            'actor_user_id' => $owner->id,
            'event' => 'alliance.kingdom_updated',
        ]);
        $this->assertDatabaseHas('outbox_messages', [
            'alliance_id' => $alliance->id,
            'event_type' => 'alliance.kingdom_updated',
        ]);
    }

    public function test_kingdom_mutation_requires_recent_password_confirmation(): void
    {
        $owner = User::factory()->create();
        $alliance = $this->app->make(CreateAlliance::class)
            ->handle($owner, 'Confirmed Kingdom', 'confirmed-kingdom', 1400);
        $sessionKey = (string) config('identity.active_alliance_session_key');

        $this->actingAs($owner)
            ->withSession([$sessionKey => $alliance->id])
            ->patch('/alliance/settings/kingdom', ['kingdom' => 1500])
            ->assertRedirect(route('password.confirm'));

        $alliance->refresh()->load('kingdom');
        self::assertSame(1400, $alliance->kingdom?->number);
        $this->assertDatabaseMissing('audit_events', [
            'alliance_id' => $alliance->id,
            'event' => 'alliance.kingdom_updated',
        ]);
    }

    public function test_member_without_alliance_manage_cannot_view_or_change_kingdom_settings(): void
    {
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $alliance = $this->app->make(CreateAlliance::class)
            ->handle($owner, 'Restricted Kingdom', 'restricted-kingdom', 1600);
        $membership = AllianceMembership::query()->create([
            'alliance_id' => $alliance->id,
            'user_id' => $member->id,
            'status' => MembershipStatus::Active,
            'joined_at' => now(),
        ]);
        $memberRole = Role::query()
            ->where('alliance_id', $alliance->id)
            ->where('key', DefaultAllianceRole::Member->value)
            ->sole();
        $membership->roles()->attach($memberRole->id, ['alliance_id' => $alliance->id]);
        $sessionKey = (string) config('identity.active_alliance_session_key');

        $this->actingAs($member)
            ->withSession([$sessionKey => $alliance->id])
            ->get('/alliance/settings/kingdom')
            ->assertForbidden();

        $this->withSession([
            $sessionKey => $alliance->id,
            'auth.password_confirmed_at' => time(),
        ])->patch('/alliance/settings/kingdom', ['kingdom' => 1700])
            ->assertForbidden();

        $alliance->refresh()->load('kingdom');
        self::assertSame(1600, $alliance->kingdom?->number);
    }

    public function test_repeating_the_same_kingdom_assignment_is_a_no_op(): void
    {
        $owner = User::factory()->create();
        $alliance = $this->app->make(CreateAlliance::class)
            ->handle($owner, 'No-op Kingdom', 'no-op-kingdom', 1800);
        $sessionKey = (string) config('identity.active_alliance_session_key');
        $session = [
            $sessionKey => $alliance->id,
            'auth.password_confirmed_at' => time(),
        ];

        $this->actingAs($owner)
            ->withSession($session)
            ->patch('/alliance/settings/kingdom', ['kingdom' => 1900])
            ->assertRedirect();

        $auditCount = $this->auditCount($alliance->id);
        $outboxCount = $this->outboxCount($alliance->id);

        $this->withSession($session)
            ->patch('/alliance/settings/kingdom', ['kingdom' => 1900])
            ->assertRedirect();

        self::assertSame($auditCount, $this->auditCount($alliance->id));
        self::assertSame($outboxCount, $this->outboxCount($alliance->id));
    }

    public function test_archived_kingdom_cannot_be_selected(): void
    {
        $owner = User::factory()->create();
        $alliance = $this->app->make(CreateAlliance::class)
            ->handle($owner, 'Archived Kingdom', 'archived-kingdom');
        Kingdom::query()->create([
            'number' => 2000,
            'status' => KingdomStatus::Archived,
        ]);
        $sessionKey = (string) config('identity.active_alliance_session_key');

        $this->actingAs($owner)
            ->withSession([
                $sessionKey => $alliance->id,
                'auth.password_confirmed_at' => time(),
            ])
            ->from('/alliance/settings/kingdom')
            ->patch('/alliance/settings/kingdom', ['kingdom' => 2000])
            ->assertRedirect('/alliance/settings/kingdom')
            ->assertSessionHasErrors('kingdom');

        self::assertNull($alliance->refresh()->kingdom_id);
    }

    private function auditCount(string $allianceId): int
    {
        return (int) DB::table('audit_events')
            ->where('alliance_id', $allianceId)
            ->where('event', 'alliance.kingdom_updated')
            ->count();
    }

    private function outboxCount(string $allianceId): int
    {
        return (int) DB::table('outbox_messages')
            ->where('alliance_id', $allianceId)
            ->where('event_type', 'alliance.kingdom_updated')
            ->count();
    }
}
