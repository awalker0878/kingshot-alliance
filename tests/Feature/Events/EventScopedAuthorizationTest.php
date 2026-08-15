<?php

declare(strict_types=1);

namespace Tests\Feature\Events;

use App\Contexts\Accounts\Models\User;
use App\Contexts\Alliance\Access\Actions\AssignMembershipRole;
use App\Contexts\Alliance\Access\Enums\DefaultAllianceRole;
use App\Contexts\Alliance\Access\Models\Role;
use App\Contexts\Alliance\Core\Actions\CreateAlliance;
use App\Contexts\Alliance\Membership\Actions\UpdateAllianceRank;
use App\Contexts\Alliance\Membership\Enums\AllianceRank;
use App\Contexts\Alliance\Membership\Enums\MembershipStatus;
use App\Contexts\Alliance\Membership\Enums\RosterState;
use App\Contexts\Alliance\Membership\Models\AllianceMembership;
use App\Contexts\GameWorld\Models\Kingdom;
use App\Contexts\GameWorld\Models\Player;
use App\Domain\Authorization\Enums\DefaultKingdomRole;
use App\Domain\Authorization\Enums\PermissionKey;
use App\Domain\Authorization\Models\KingdomRoleAssignment;
use App\Domain\Authorization\Services\KingdomRoleProvisioner;
use App\Domain\Events\Enums\EventScope;
use App\Domain\Events\Services\EventAuthorization;
use App\Domain\Events\Services\EventCreationContextResolver;
use App\Domain\Events\Services\EventVisibilityResolver;
use App\Domain\Kingdoms\Actions\SaveRosterEntry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

final class EventScopedAuthorizationTest extends TestCase
{
    use RefreshDatabase;

    public function test_alliance_event_authority_comes_from_active_player_rank_or_specialist_role_only(): void
    {
        $ownerUser = User::factory()->create();
        $r4User = User::factory()->create();
        $coordinatorUser = User::factory()->create();
        $memberUser = User::factory()->create();
        $kingdom = Kingdom::query()->create(['number' => 1501, 'status' => 'active']);
        $ownerPlayer = Player::query()->create([
            'user_id' => $ownerUser->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => 'event-alliance-owner',
            'current_name' => 'Alliance Owner',
        ]);
        $r4Player = Player::query()->create([
            'user_id' => $r4User->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => 'event-alliance-r4',
            'current_name' => 'Alliance R4',
        ]);
        $coordinatorPlayer = Player::query()->create([
            'user_id' => $coordinatorUser->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => 'event-alliance-coordinator',
            'current_name' => 'Alliance Coordinator',
        ]);
        $memberPlayer = Player::query()->create([
            'user_id' => $memberUser->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => 'event-alliance-member',
            'current_name' => 'Alliance Member',
        ]);
        $alliance = $this->app->make(CreateAlliance::class)->handle($ownerPlayer, 'Scoped Alliance', 'scoped-alliance');
        $r4Membership = AllianceMembership::query()->create([
            'alliance_id' => $alliance->id,
            'player_id' => $r4Player->id,
            'status' => MembershipStatus::Active,
            'rank' => AllianceRank::R1,
            'joined_at' => now(),
        ]);
        $coordinatorMembership = AllianceMembership::query()->create([
            'alliance_id' => $alliance->id,
            'player_id' => $coordinatorPlayer->id,
            'status' => MembershipStatus::Active,
            'rank' => AllianceRank::R1,
            'joined_at' => now(),
        ]);
        AllianceMembership::query()->create([
            'alliance_id' => $alliance->id,
            'player_id' => $memberPlayer->id,
            'status' => MembershipStatus::Active,
            'rank' => AllianceRank::R1,
            'joined_at' => now(),
        ]);
        $this->app->make(UpdateAllianceRank::class)
            ->handle($alliance, $ownerPlayer, $r4Membership->id, AllianceRank::R4);
        $role = Role::query()
            ->where('alliance_id', $alliance->id)
            ->where('key', DefaultAllianceRole::EventCoordinator->value)
            ->sole();
        $this->app->make(AssignMembershipRole::class)
            ->handle($alliance, $ownerPlayer, $coordinatorMembership->id, $role->id);

        $authorization = $this->app->make(EventAuthorization::class);
        foreach ([$ownerPlayer, $r4Player, $coordinatorPlayer] as $actor) {
            self::assertTrue($authorization->allows(
                $actor,
                EventScope::Alliance,
                $alliance,
                PermissionKey::EventAllianceCreate,
            ));
            self::assertTrue($authorization->allows(
                $actor,
                EventScope::Alliance,
                $alliance,
                PermissionKey::EventAllianceManage,
            ));
        }

        self::assertFalse($authorization->allows(
            $memberPlayer,
            EventScope::Alliance,
            $alliance,
            PermissionKey::EventAllianceCreate,
        ));
        self::assertFalse($authorization->allows(
            $ownerPlayer,
            EventScope::Kingdom,
            $kingdom,
            PermissionKey::EventKingdomManage,
        ));
    }

    public function test_kingdom_event_authority_is_bound_to_explicit_player_assignment_in_exact_kingdom(): void
    {
        $adminUser = User::factory()->create();
        $firstKingdom = Kingdom::query()->create(['number' => 1601, 'status' => 'active']);
        $secondKingdom = Kingdom::query()->create(['number' => 1602, 'status' => 'active']);
        $adminPlayer = Player::query()->create([
            'user_id' => $adminUser->id,
            'current_kingdom_id' => $firstKingdom->id,
            'game_player_id' => 'kingdom-event-admin',
            'current_name' => 'Kingdom Event Admin',
        ]);
        $roles = $this->app->make(KingdomRoleProvisioner::class)->provision($firstKingdom);
        $administrator = $roles[DefaultKingdomRole::Administrator->value];
        KingdomRoleAssignment::query()->create([
            'kingdom_id' => $firstKingdom->id,
            'player_id' => $adminPlayer->id,
            'kingdom_role_id' => $administrator->id,
        ]);

        $authorization = $this->app->make(EventAuthorization::class);
        self::assertTrue($authorization->allows(
            $adminPlayer,
            EventScope::Kingdom,
            $firstKingdom,
            PermissionKey::EventKingdomCreate,
        ));
        self::assertTrue($authorization->allows(
            $adminPlayer,
            EventScope::Kingdom,
            $firstKingdom,
            PermissionKey::EventKingdomManage,
        ));
        self::assertFalse($authorization->allows(
            $adminPlayer,
            EventScope::Kingdom,
            $secondKingdom,
            PermissionKey::EventKingdomManage,
        ));
    }

    public function test_player_event_authorization_uses_exact_player_identity_and_roster_manager_permission(): void
    {
        $ownerUser = User::factory()->create();
        $memberUser = User::factory()->create();
        $peerUser = User::factory()->create();
        $outsiderUser = User::factory()->create();
        $kingdom = Kingdom::query()->create(['number' => 1701, 'status' => 'active']);
        $ownerPlayer = Player::query()->create([
            'user_id' => $ownerUser->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => 'player-event-owner',
            'current_name' => 'Player Event Owner',
        ]);
        $memberPlayer = Player::query()->create([
            'user_id' => $memberUser->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => 'player-event-member',
            'current_name' => 'Player One',
        ]);
        $peerPlayer = Player::query()->create([
            'user_id' => $peerUser->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => 'player-event-peer',
            'current_name' => 'Peer',
        ]);
        $outsiderPlayer = Player::query()->create([
            'user_id' => $outsiderUser->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => 'player-event-outsider',
            'current_name' => 'Outsider',
        ]);
        $alliance = $this->app->make(CreateAlliance::class)->handle($ownerPlayer, 'Player Scope', 'player-scope');
        AllianceMembership::query()->create([
            'alliance_id' => $alliance->id,
            'player_id' => $memberPlayer->id,
            'status' => MembershipStatus::Active,
            'rank' => AllianceRank::R1,
            'joined_at' => now(),
        ]);
        AllianceMembership::query()->create([
            'alliance_id' => $alliance->id,
            'player_id' => $peerPlayer->id,
            'status' => MembershipStatus::Active,
            'rank' => AllianceRank::R1,
            'joined_at' => now(),
        ]);
        $entry = $this->app->make(SaveRosterEntry::class)->handle($alliance, $ownerPlayer, [
            'name' => 'Player One',
            'game_player_id' => 'player-event-member',
        ]);
        $this->app->make(SaveRosterEntry::class)->handle($alliance, $ownerPlayer, [
            'name' => 'Peer',
            'game_player_id' => 'player-event-peer',
        ]);

        $authorization = $this->app->make(EventAuthorization::class);
        self::assertTrue($authorization->allows($memberPlayer, EventScope::Player, $memberPlayer, PermissionKey::EventPlayerView));
        self::assertTrue($authorization->allows($memberPlayer, EventScope::Player, $memberPlayer, PermissionKey::EventPlayerCreate));
        self::assertTrue($authorization->allows($memberPlayer, EventScope::Player, $memberPlayer, PermissionKey::EventPlayerManage));
        self::assertFalse($authorization->allows($peerPlayer, EventScope::Player, $memberPlayer, PermissionKey::EventPlayerView));
        self::assertTrue($authorization->allows($ownerPlayer, EventScope::Player, $memberPlayer, PermissionKey::EventPlayerManage));
        self::assertFalse($authorization->allows($outsiderPlayer, EventScope::Player, $memberPlayer, PermissionKey::EventPlayerManage));

        $entry->forceFill(['state' => RosterState::Left])->save();
        self::assertTrue($authorization->allows($memberPlayer, EventScope::Player, $memberPlayer, PermissionKey::EventPlayerManage));
        self::assertFalse($authorization->allows($ownerPlayer, EventScope::Player, $memberPlayer, PermissionKey::EventPlayerManage));
    }

    public function test_one_user_multiple_players_never_aggregate_player_event_contexts(): void
    {
        $user = User::factory()->create();
        $kingdom = Kingdom::query()->create(['number' => 1751, 'status' => 'active']);
        $firstPlayer = Player::query()->create([
            'user_id' => $user->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => 'multi-event-primary',
            'current_name' => 'Primary Account',
        ]);
        $secondPlayer = Player::query()->create([
            'user_id' => $user->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => 'multi-event-farm',
            'current_name' => 'Farm Account',
        ]);

        $resolver = $this->app->make(EventCreationContextResolver::class);
        $firstContexts = $resolver->forPlayer($firstPlayer);
        $secondContexts = $resolver->forPlayer($secondPlayer);

        self::assertSame(
            [(string) $firstPlayer->id],
            collect($firstContexts)->where('scope', EventScope::Player->value)->pluck('targetId')->values()->all(),
        );
        self::assertSame(
            [(string) $secondPlayer->id],
            collect($secondContexts)->where('scope', EventScope::Player->value)->pluck('targetId')->values()->all(),
        );
        self::assertNotContains((string) $secondPlayer->id, array_column($firstContexts, 'targetId'));
        self::assertNotContains((string) $firstPlayer->id, array_column($secondContexts, 'targetId'));
    }

    public function test_same_user_switching_players_switches_event_visibility_instead_of_unioning_it(): void
    {
        $user = User::factory()->create();
        $kingdom = Kingdom::query()->create(['number' => 1761, 'status' => 'active']);
        $firstPlayer = Player::query()->create([
            'user_id' => $user->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => 'visibility-first',
            'current_name' => 'Visibility First',
        ]);
        $secondPlayer = Player::query()->create([
            'user_id' => $user->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => 'visibility-second',
            'current_name' => 'Visibility Second',
        ]);
        $createAlliance = $this->app->make(CreateAlliance::class);
        $firstAlliance = $createAlliance->handle($firstPlayer, 'First Visibility', 'first-visibility');
        $secondAlliance = $createAlliance->handle($secondPlayer, 'Second Visibility', 'second-visibility');

        $resolver = $this->app->make(EventVisibilityResolver::class);
        $firstTargets = $resolver->targetIds($firstPlayer);
        $secondTargets = $resolver->targetIds($secondPlayer);

        self::assertContains((string) $firstAlliance->id, $firstTargets['alliance']);
        self::assertNotContains((string) $secondAlliance->id, $firstTargets['alliance']);
        self::assertContains((string) $firstPlayer->id, $firstTargets['player']);
        self::assertNotContains((string) $secondPlayer->id, $firstTargets['player']);

        self::assertContains((string) $secondAlliance->id, $secondTargets['alliance']);
        self::assertNotContains((string) $firstAlliance->id, $secondTargets['alliance']);
        self::assertContains((string) $secondPlayer->id, $secondTargets['player']);
        self::assertNotContains((string) $firstPlayer->id, $secondTargets['player']);
    }

    public function test_creation_context_resolver_returns_only_scopes_granted_to_active_player(): void
    {
        $ownerUser = User::factory()->create();
        $actorUser = User::factory()->create();
        $kingdom = Kingdom::query()->create(['number' => 1801, 'status' => 'active']);
        $ownerPlayer = Player::query()->create([
            'user_id' => $ownerUser->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => 'context-owner',
            'current_name' => 'Context Owner',
        ]);
        $actorPlayer = Player::query()->create([
            'user_id' => $actorUser->id,
            'current_kingdom_id' => $kingdom->id,
            'game_player_id' => 'context-actor',
            'current_name' => 'Context Actor',
        ]);
        $alliance = $this->app->make(CreateAlliance::class)->handle($ownerPlayer, 'Multi Context', 'multi-context');
        $membership = AllianceMembership::query()->create([
            'alliance_id' => $alliance->id,
            'player_id' => $actorPlayer->id,
            'status' => MembershipStatus::Active,
            'rank' => AllianceRank::R1,
            'joined_at' => now(),
        ]);

        $resolver = $this->app->make(EventCreationContextResolver::class);
        self::assertSame([EventScope::Player->value], array_column($resolver->forPlayer($actorPlayer), 'scope'));

        $role = Role::query()
            ->where('alliance_id', $alliance->id)
            ->where('key', DefaultAllianceRole::EventCoordinator->value)
            ->sole();
        $this->app->make(AssignMembershipRole::class)
            ->handle($alliance, $ownerPlayer, $membership->id, $role->id);

        $kingdomRoles = $this->app->make(KingdomRoleProvisioner::class)->provision($kingdom);
        $eventCoordinator = $kingdomRoles[DefaultKingdomRole::EventCoordinator->value];
        KingdomRoleAssignment::query()->create([
            'kingdom_id' => $kingdom->id,
            'player_id' => $actorPlayer->id,
            'kingdom_role_id' => $eventCoordinator->id,
        ]);

        $contexts = $resolver->forPlayer($actorPlayer);
        self::assertSame([
            EventScope::Player->value,
            EventScope::Alliance->value,
            EventScope::Kingdom->value,
        ], array_column($contexts, 'scope'));
        self::assertSame((string) $actorPlayer->id, $contexts[0]['targetId']);
        self::assertSame((string) $alliance->id, $contexts[1]['targetId']);
        self::assertSame((string) $kingdom->id, $contexts[2]['targetId']);
    }
}
