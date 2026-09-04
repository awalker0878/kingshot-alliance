<?php

declare(strict_types=1);

namespace Tests\v3\Contexts\Alliance\Access;

use App\Contexts\Alliance\Access\Actions\ArchiveAllianceRole;
use App\Contexts\Alliance\Access\Actions\AssignMembershipRole;
use App\Contexts\Alliance\Access\Actions\CreateAllianceRole;
use App\Contexts\Alliance\Access\Actions\UpdateAllianceRole;
use App\Contexts\Alliance\Access\Enums\AlliancePermission;
use App\Contexts\Alliance\Access\Models\Role;
use App\Contexts\Alliance\Membership\Enums\AllianceRank;
use App\Contexts\Alliance\Membership\Enums\MembershipStatus;
use App\Contexts\Alliance\Membership\Models\AllianceMembership;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\v3\Support\ScenarioFactory;
use Tests\v3\TestCase;

final class AllianceRoleAdministrationBehaviorV3Test extends TestCase
{
    use RefreshDatabase;

    public function test_custom_role_keeps_stable_key_and_archiving_removes_assignments(): void
    {
        $scenario = new ScenarioFactory;
        $account = $scenario->authUser();
        $owner = $scenario->player((int) $account->id, 59101);
        $alliance = $scenario->alliance($owner);
        $memberPlayer = $scenario->unclaimedPlayer(59101);
        $membership = AllianceMembership::query()->create([
            'alliance_id' => $alliance->allianceId,
            'player_id' => $memberPlayer->playerId,
            'status' => MembershipStatus::Active,
            'rank' => AllianceRank::R1,
            'joined_at' => now(),
        ]);

        $roleId = app(CreateAllianceRole::class)->handle(
            $alliance->allianceId,
            $owner->playerId,
            'Event Coordinator',
            [AlliancePermission::ContentManage],
        );
        app(AssignMembershipRole::class)->handle(
            $alliance->allianceId,
            $owner->playerId,
            (string) $membership->id,
            $roleId,
        );

        app(UpdateAllianceRole::class)->handle(
            $alliance->allianceId,
            $owner->playerId,
            $roleId,
            'Event Operations',
            [AlliancePermission::ContentManage, AlliancePermission::InvitationManage],
        );

        $role = Role::query()->findOrFail($roleId);
        self::assertSame('event-coordinator', $role->key);
        self::assertSame('Event Operations', $role->name);
        self::assertTrue($membership->roles()->where('roles.id', $roleId)->exists());

        app(ArchiveAllianceRole::class)->handle($alliance->allianceId, $owner->playerId, $roleId);

        $role->refresh();
        self::assertNotNull($role->archived_at);
        self::assertFalse($membership->roles()->where('roles.id', $roleId)->exists());
    }

    public function test_system_roles_cannot_be_changed_or_archived(): void
    {
        $scenario = new ScenarioFactory;
        $account = $scenario->authUser();
        $owner = $scenario->player((int) $account->id, 59102);
        $alliance = $scenario->alliance($owner);
        $systemRole = Role::query()
            ->where('alliance_id', $alliance->allianceId)
            ->where('is_system', true)
            ->firstOrFail();

        try {
            app(UpdateAllianceRole::class)->handle(
                $alliance->allianceId,
                $owner->playerId,
                (string) $systemRole->id,
                'Changed System Role',
                [],
            );
            self::fail('Expected a system role update to be rejected.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey('role', $exception->errors());
        }

        try {
            app(ArchiveAllianceRole::class)->handle(
                $alliance->allianceId,
                $owner->playerId,
                (string) $systemRole->id,
            );
            self::fail('Expected a system role archive to be rejected.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey('role', $exception->errors());
        }

        $systemRole->refresh();
        self::assertTrue($systemRole->is_system);
        self::assertNull($systemRole->archived_at);
    }
}
