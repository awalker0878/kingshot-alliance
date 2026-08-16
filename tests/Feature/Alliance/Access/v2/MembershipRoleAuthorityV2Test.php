<?php

declare(strict_types=1);

namespace Tests\Feature\Alliance\Access\v2;

use App\Contexts\Accounts\Models\User;
use App\Contexts\Alliance\Access\Actions\AssignMembershipRole;
use App\Contexts\Alliance\Access\Actions\RemoveMembershipRole;
use App\Contexts\Alliance\Access\Enums\DefaultAllianceRole;
use App\Contexts\Alliance\Access\Models\Role;
use App\Contexts\Alliance\Membership\Actions\AcceptInvitation;
use App\Contexts\Alliance\Membership\Actions\CreateInvitation;
use App\Contexts\Alliance\Membership\Actions\UpdateAllianceRank;
use App\Contexts\Alliance\Membership\Enums\AllianceRank;
use App\Contexts\Intelligence\Roster\Actions\SaveRosterEntry;
use App\Shared\Infrastructure\Messaging\Outbox\Models\OutboxMessage;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\V2\ScenarioFactory;
use Tests\TestCase;

final class MembershipRoleAuthorityV2Test extends TestCase
{
    use RefreshDatabase;

    public function test_r4_cannot_assign_specialist_roles_even_when_it_can_manage_lower_rank_members(): void
    {
        $scenario = (new ScenarioFactory)->alliance(4550, 'Access Owner', 'Access V2', 'access-v2-4550');
        $r4User = User::factory()->create(['email' => 'access-r4-v2@example.com']);
        $memberUser = User::factory()->create(['email' => 'access-member-v2@example.com']);

        $r4Entry = app(SaveRosterEntry::class)->handle($scenario['alliance'], $scenario['player'], [
            'name' => 'Access R4 V2',
            'game_player_id' => 'access-r4-v2-4550',
        ]);
        $r4Invite = app(CreateInvitation::class)->handle($scenario['alliance'], $scenario['player'], $r4Entry->player, $r4User->email);
        $r4Membership = app(AcceptInvitation::class)->handle($r4User, $r4Invite->token);
        app(UpdateAllianceRank::class)->handle($scenario['alliance'], $scenario['player'], $r4Membership->id, AllianceRank::R4);

        $memberEntry = app(SaveRosterEntry::class)->handle($scenario['alliance'], $scenario['player'], [
            'name' => 'Access Member V2',
            'game_player_id' => 'access-member-v2-4550',
        ]);
        $memberInvite = app(CreateInvitation::class)->handle($scenario['alliance'], $scenario['player'], $memberEntry->player, $memberUser->email);
        $memberMembership = app(AcceptInvitation::class)->handle($memberUser, $memberInvite->token);
        $role = Role::query()
            ->where('alliance_id', $scenario['alliance']->id)
            ->where('key', DefaultAllianceRole::EventCoordinator->value)
            ->sole();

        $this->expectException(AuthorizationException::class);
        app(AssignMembershipRole::class)->handle(
            $scenario['alliance'],
            $r4Entry->player,
            $memberMembership->id,
            $role->id,
        );
    }

    public function test_r5_role_assignment_is_idempotent_and_can_be_repeated_after_explicit_removal(): void
    {
        $scenario = (new ScenarioFactory)->alliance(4551, 'Role Owner', 'Role Lifecycle V2', 'role-lifecycle-v2-4551');
        $memberUser = User::factory()->create(['email' => 'role-member-v2@example.com']);
        $entry = app(SaveRosterEntry::class)->handle($scenario['alliance'], $scenario['player'], [
            'name' => 'Role Member V2',
            'game_player_id' => 'role-member-v2-4551',
        ]);
        $invite = app(CreateInvitation::class)->handle($scenario['alliance'], $scenario['player'], $entry->player, $memberUser->email);
        $membership = app(AcceptInvitation::class)->handle($memberUser, $invite->token);
        $role = Role::query()
            ->where('alliance_id', $scenario['alliance']->id)
            ->where('key', DefaultAllianceRole::EventCoordinator->value)
            ->sole();
        $assign = app(AssignMembershipRole::class);

        $assign->handle($scenario['alliance'], $scenario['player'], $membership->id, $role->id);
        $assign->handle($scenario['alliance'], $scenario['player'], $membership->id, $role->id);
        self::assertSame(1, OutboxMessage::query()
            ->where('event_type', 'membership.role_assigned')
            ->where('aggregate_id', $membership->id)
            ->count());

        app(RemoveMembershipRole::class)->handle($scenario['alliance'], $scenario['player'], $membership->id, $role->id);
        $reassigned = $assign->handle($scenario['alliance'], $scenario['player'], $membership->id, $role->id);

        self::assertTrue($reassigned->roles()->where('roles.id', $role->id)->exists());
        self::assertSame(2, OutboxMessage::query()
            ->where('event_type', 'membership.role_assigned')
            ->where('aggregate_id', $membership->id)
            ->count());
    }
}
