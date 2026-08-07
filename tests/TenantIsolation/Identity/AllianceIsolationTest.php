<?php

declare(strict_types=1);

namespace Tests\TenantIsolation\Identity;

use App\Domain\Alliances\Actions\CreateAlliance;
use App\Domain\Alliances\Services\AllianceContext;
use App\Domain\Authorization\Enums\DefaultAllianceRole;
use App\Domain\Authorization\Enums\PermissionKey;
use App\Domain\Authorization\Models\Role;
use App\Domain\Authorization\Services\AllianceAuthorization;
use App\Domain\Identity\Models\User;
use App\Domain\Memberships\Enums\MembershipStatus;
use App\Domain\Memberships\Models\AllianceMembership;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use LogicException;
use Tests\TestCase;

final class AllianceIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorization_and_database_constraints_reject_cross_alliance_role_leakage(): void
    {
        $firstOwner = User::factory()->create();
        $secondOwner = User::factory()->create();
        $createAlliance = $this->app->make(CreateAlliance::class);

        $firstAlliance = $createAlliance->handle($firstOwner, 'First Alliance', 'first-alliance');
        $secondAlliance = $createAlliance->handle($secondOwner, 'Second Alliance', 'second-alliance');

        $authorization = $this->app->make(AllianceAuthorization::class);

        self::assertTrue($authorization->allows($firstOwner, $firstAlliance, PermissionKey::AllianceManage));
        self::assertFalse($authorization->allows($firstOwner, $secondAlliance, PermissionKey::AllianceView));

        $secondMembership = AllianceMembership::query()->create([
            'alliance_id' => $secondAlliance->id,
            'user_id' => $firstOwner->id,
            'status' => MembershipStatus::Active,
            'joined_at' => now(),
        ]);

        $firstOwnerRole = Role::query()
            ->where('alliance_id', $firstAlliance->id)
            ->where('key', DefaultAllianceRole::Owner->value)
            ->sole();

        try {
            DB::transaction(static function () use ($secondMembership, $firstOwnerRole, $secondAlliance): void {
                $secondMembership->roles()->attach($firstOwnerRole->id, [
                    'alliance_id' => $secondAlliance->id,
                ]);
            });

            self::fail('The database must reject a role owned by another alliance.');
        } catch (QueryException) {
            self::assertFalse($authorization->allows($firstOwner, $secondAlliance, PermissionKey::AllianceView));
            self::assertFalse($authorization->allows($firstOwner, $secondAlliance, PermissionKey::AllianceManage));
        }

        $secondMemberRole = Role::query()
            ->where('alliance_id', $secondAlliance->id)
            ->where('key', DefaultAllianceRole::Member->value)
            ->sole();

        $secondMembership->roles()->attach($secondMemberRole->id, [
            'alliance_id' => $secondAlliance->id,
        ]);

        self::assertTrue($authorization->allows($firstOwner, $secondAlliance, PermissionKey::AllianceView));
        self::assertFalse($authorization->allows($firstOwner, $secondAlliance, PermissionKey::AllianceManage));
    }

    public function test_suspended_membership_fails_closed(): void
    {
        $owner = User::factory()->create();
        $alliance = $this->app->make(CreateAlliance::class)
            ->handle($owner, 'Suspended Alliance', 'suspended-alliance');

        AllianceMembership::query()
            ->where('alliance_id', $alliance->id)
            ->where('user_id', $owner->id)
            ->update(['status' => MembershipStatus::Suspended->value]);

        self::assertFalse($this->app->make(AllianceAuthorization::class)
            ->allows($owner, $alliance, PermissionKey::AllianceView));
    }

    public function test_alliance_context_can_be_activated_and_cleared(): void
    {
        $owner = User::factory()->create();
        $alliance = $this->app->make(CreateAlliance::class)
            ->handle($owner, 'Context Alliance', 'context-alliance');
        $context = $this->app->make(AllianceContext::class);

        $context->activate($alliance, $owner);
        self::assertSame($alliance->id, $context->alliance()->id);
        self::assertSame($owner->id, $context->membership()->user_id);

        $context->clear();

        $this->expectException(LogicException::class);
        $context->alliance();
    }

    public function test_outsider_cannot_activate_alliance_context(): void
    {
        $owner = User::factory()->create();
        $outsider = User::factory()->create();
        $alliance = $this->app->make(CreateAlliance::class)
            ->handle($owner, 'Private Alliance', 'private-alliance');

        $this->expectException(LogicException::class);
        $this->app->make(AllianceContext::class)->activate($alliance, $outsider);
    }
}
