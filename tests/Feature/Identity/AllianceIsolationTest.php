<?php

declare(strict_types=1);

namespace Tests\Feature\Identity;

use App\Application\Identity\AllianceAuthorization;
use App\Application\Identity\AllianceContext;
use App\Application\Identity\CreateAlliance;
use App\Domain\Identity\Authorization\DefaultAllianceRole;
use App\Domain\Identity\Authorization\PermissionKey;
use App\Domain\Identity\Enums\MembershipStatus;
use App\Models\AllianceMembership;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use LogicException;
use Tests\TestCase;

final class AllianceIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorization_rejects_non_members_and_cross_alliance_role_leakage(): void
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

        // Deliberately corrupt the pivot with a role owned by another alliance.
        $secondMembership->roles()->attach($firstOwnerRole->id);

        self::assertFalse($authorization->allows($firstOwner, $secondAlliance, PermissionKey::AllianceView));
        self::assertFalse($authorization->allows($firstOwner, $secondAlliance, PermissionKey::AllianceManage));

        $secondMemberRole = Role::query()
            ->where('alliance_id', $secondAlliance->id)
            ->where('key', DefaultAllianceRole::Member->value)
            ->sole();

        $secondMembership->roles()->attach($secondMemberRole->id);

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

    public function test_alliance_context_requires_an_active_membership_and_can_be_cleared(): void
    {
        $owner = User::factory()->create();
        $outsider = User::factory()->create();
        $alliance = $this->app->make(CreateAlliance::class)
            ->handle($owner, 'Context Alliance', 'context-alliance');
        $context = $this->app->make(AllianceContext::class);

        $context->activate($alliance, $owner);
        self::assertSame($alliance->id, $context->alliance()->id);
        self::assertSame($owner->id, $context->membership()->user_id);

        $context->clear();

        $this->expectException(LogicException::class);
        $context->alliance();

        $separateContext = $this->app->make(AllianceContext::class);

        try {
            $separateContext->activate($alliance, $outsider);
            self::fail('An outsider must not activate alliance context.');
        } catch (LogicException) {
            self::assertTrue(true);
        }
    }
}
