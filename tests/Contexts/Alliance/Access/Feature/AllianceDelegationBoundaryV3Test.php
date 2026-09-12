<?php

declare(strict_types=1);

namespace Tests\Contexts\Alliance\Access\Feature;

use App\Contexts\Accounts\Identity\Models\User;
use App\Contexts\Alliance\Access\Actions\AssignMembershipRole;
use App\Contexts\Alliance\Access\Actions\BulkChangeMembershipRole;
use App\Contexts\Alliance\Access\Actions\CreateAllianceRole;
use App\Contexts\Alliance\Access\Actions\PreviewBulkMembershipRoleChange;
use App\Contexts\Alliance\Access\Actions\UpdateAllianceRole;
use App\Contexts\Alliance\Access\Enums\AlliancePermission;
use App\Contexts\Alliance\Access\Enums\DefaultAllianceRole;
use App\Contexts\Alliance\Access\Models\Role;
use App\Contexts\Alliance\Access\Services\AllianceAuthorization;
use App\Contexts\Alliance\Lifecycle\ValueObjects\AllianceReference;
use App\Contexts\Alliance\Membership\Actions\BulkUpdateAllianceRank;
use App\Contexts\Alliance\Membership\Actions\PreviewBulkAllianceRankChange;
use App\Contexts\Alliance\Membership\Actions\UpdateAllianceRank;
use App\Contexts\Alliance\Membership\Enums\AllianceRank;
use App\Contexts\Alliance\Membership\Enums\MembershipStatus;
use App\Contexts\Alliance\Membership\Models\AllianceMembership;
use App\Contexts\Alliance\Membership\Queries\PlayerIdentityContextQuery;
use App\Contexts\GameWorld\Governance\Queries\KingdomAuthorityFactsQuery;
use App\Contexts\GameWorld\Players\Http\Middleware\RequireCurrentPlayerContextVersion;
use App\Contexts\GameWorld\Players\Services\PlayerAuthorityContextVersion;
use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;
use App\Contexts\Operations\Access\Enums\OperationsPermission;
use App\Contexts\Operations\Access\Services\AllianceOperationsAuthorization;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Support\ScenarioFactory;
use Tests\TestCase;

final class AllianceDelegationBoundaryV3Test extends TestCase
{
    use RefreshDatabase;

    /** @return iterable<string,array{bool}> */
    public static function recipients(): iterable
    {
        yield 'self' => [true];
        yield 'another member' => [false];
    }

    #[DataProvider('recipients')]
    public function test_direct_grants_cannot_delegate_unheld_permissions_to_any_recipient(bool $self): void
    {
        $s = $this->delegatedScenario();
        $before = $this->grantSnapshot();
        $target = $self ? $s['actorMembership'] : $s['target'];
        try {
            app(AssignMembershipRole::class)->handle($s['alliance']->allianceId, $s['actor']->playerId, (string) $target->id, $s['roleId']);
            self::fail('Every recipient must be subject to the delegation ceiling.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey('role', $exception->errors());
        }
        self::assertSame($before, $this->grantSnapshot());
    }

    public function test_direct_http_assignment_cannot_bypass_the_bulk_preview_policy(): void
    {
        $s = $this->delegatedScenario();
        $before = $this->grantSnapshot();
        $alliance = app(PlayerIdentityContextQuery::class)->forPlayers([$s['actor']->playerId])[$s['actor']->playerId] ?? null;
        $kingdomPermissions = app(KingdomAuthorityFactsQuery::class)
            ->findCurrent($s['actor']->playerId, $s['actor']->kingdomId)?->permissionKeysObservedAtRead ?? [];
        $version = app(PlayerAuthorityContextVersion::class)->issue($s['actor'], $alliance, $kingdomPermissions);
        $this->actingAs($s['actorUser'])
            ->withSession([
                (string) config('game_world.active_player_session_key') => $s['actor']->playerId,
                'accounts.recent_authentication_at' => now()->timestamp,
            ])
            ->withHeader(RequireCurrentPlayerContextVersion::HEADER_NAME, $version)
            ->putJson(route('alliance.memberships.roles.assign', ['membership' => $s['target']->id, 'role' => $s['roleId']]))
            ->assertUnprocessable()->assertJsonValidationErrors('role');
        self::assertSame($before, $this->grantSnapshot());
    }

    #[DataProvider('recipients')]
    public function test_current_permission_subset_can_be_assigned_without_self_escalation(bool $self): void
    {
        $s = $this->delegatedScenario([AlliancePermission::ContentManage]);
        $target = $self ? $s['actorMembership'] : $s['target'];
        app(AssignMembershipRole::class)->handle($s['alliance']->allianceId, $s['actor']->playerId, (string) $target->id, $s['roleId']);
        self::assertTrue($target->roles()->whereKey($s['roleId'])->exists());
    }

    public function test_role_removal_does_not_require_the_permissions_being_revoked(): void
    {
        $s = $this->delegatedScenario();
        app(AssignMembershipRole::class)->handle($s['alliance']->allianceId, $s['owner']->playerId, (string) $s['target']->id, $s['roleId']);
        $preview = app(PreviewBulkMembershipRoleChange::class)->handle($s['alliance']->allianceId, $s['actor']->playerId, [(string) $s['target']->id], $s['roleId'], 'remove');
        self::assertSame(1, $preview['ready']);
        $result = app(BulkChangeMembershipRole::class)->handle($s['alliance']->allianceId, $s['actor']->playerId, [(string) $s['target']->id], $s['roleId'], 'remove');
        self::assertSame(1, $result['succeeded']);
        self::assertFalse($s['target']->roles()->whereKey($s['roleId'])->exists());
    }

    public function test_leader_can_commission_gift_coverage_for_another_member_without_self_access(): void
    {
        $s = $this->delegatedScenario();
        $role = Role::query()->where('alliance_id', $s['alliance']->allianceId)->where('key', DefaultAllianceRole::GiftCodeCoordinator->value)->sole();
        $leaderMembership = AllianceMembership::query()->where('player_id', $s['owner']->playerId)->sole();
        $result = app(BulkChangeMembershipRole::class)->handle($s['alliance']->allianceId, $s['owner']->playerId,
            [(string) $leaderMembership->id, (string) $s['target']->id], (string) $role->id, 'assign');
        self::assertSame(1, $result['succeeded']);
        self::assertSame(1, $result['skipped']);
        self::assertSame('permission_delegation_denied', $result['items'][0]['code']);
        self::assertFalse(app(AllianceAuthorization::class)->allows($s['owner']->playerId, $s['alliance']->allianceId, AlliancePermission::GiftCodeCoverage));
        self::assertTrue(app(AllianceAuthorization::class)->allows((string) $s['target']->player_id, $s['alliance']->allianceId, AlliancePermission::GiftCodeCoverage));
    }

    #[DataProvider('recipients')]
    public function test_empty_alliance_permission_list_does_not_allow_unheld_operations_role_grants(bool $self): void
    {
        $s = $this->delegatedScenario();
        $role = Role::query()->where('alliance_id', $s['alliance']->allianceId)->where('key', DefaultAllianceRole::EventCoordinator->value)->sole();
        $target = $self ? $s['actorMembership'] : $s['target'];
        $before = $this->grantSnapshot();
        $preview = app(PreviewBulkMembershipRoleChange::class)->handle($s['alliance']->allianceId, $s['actor']->playerId, [(string) $target->id], (string) $role->id, 'assign');
        self::assertSame(1, $preview['blocked']);
        try {
            app(AssignMembershipRole::class)->handle($s['alliance']->allianceId, $s['actor']->playerId, (string) $target->id, (string) $role->id);
            self::fail('Opaque Operations authority must not follow from an empty Alliance permission list.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey('role', $exception->errors());
        }
        self::assertSame($before, $this->grantSnapshot());
        self::assertFalse(app(AllianceOperationsAuthorization::class)->allows((string) $target->player_id, $s['alliance']->allianceId, OperationsPermission::EventAllianceManage));
    }

    public function test_current_event_coordinator_with_role_management_can_delegate_the_role(): void
    {
        $s = $this->delegatedScenario();
        $role = Role::query()->where('alliance_id', $s['alliance']->allianceId)->where('key', DefaultAllianceRole::EventCoordinator->value)->sole();
        app(AssignMembershipRole::class)->handle($s['alliance']->allianceId, $s['owner']->playerId, (string) $s['actorMembership']->id, (string) $role->id);
        app(AssignMembershipRole::class)->handle($s['alliance']->allianceId, $s['actor']->playerId, (string) $s['target']->id, (string) $role->id);
        self::assertTrue(app(AllianceOperationsAuthorization::class)->allows((string) $s['target']->player_id, $s['alliance']->allianceId, OperationsPermission::EventAllianceManage));
    }

    public function test_each_bulk_grant_rechecks_permissions_revoked_during_an_earlier_item(): void
    {
        $s = $this->delegatedScenario([AlliancePermission::ContentManage]);
        $second = $this->membership($s['alliance'], (new ScenarioFactory)->unclaimedPlayer(59230));
        $changed = false;
        DB::listen(static function (QueryExecuted $query) use ($s, &$changed): void {
            if ($changed || ! str_starts_with($query->sql, 'insert into "outbox_messages"')
                || ! in_array('membership.role_assigned', $query->bindings, true)) {
                return;
            }
            $changed = true;
            app(UpdateAllianceRole::class)->handle($s['alliance']->allianceId, $s['owner']->playerId, $s['actorRoleId'], 'Role administrator', [AlliancePermission::RoleManage]);
        });
        $result = app(BulkChangeMembershipRole::class)->handle($s['alliance']->allianceId, $s['actor']->playerId, [(string) $s['target']->id, (string) $second->id], $s['roleId'], 'assign');
        self::assertTrue($changed);
        self::assertSame(1, $result['succeeded']);
        self::assertSame([(string) $second->id], $result['failedItemIds']);
        self::assertTrue($s['target']->roles()->whereKey($s['roleId'])->exists());
        self::assertFalse($second->roles()->whereKey($s['roleId'])->exists());
    }

    /** @return iterable<string,array{bool}> */
    public static function foreignTargets(): iterable
    {
        yield 'role' => [true];
        yield 'membership' => [false];
    }

    #[DataProvider('foreignTargets')]
    public function test_grant_targets_remain_alliance_scoped(bool $foreignRole): void
    {
        $s = $this->delegatedScenario([AlliancePermission::ContentManage]);
        $scenario = new ScenarioFactory;
        $otherOwner = $scenario->player((int) $scenario->authUser()->id, 59231);
        $otherAlliance = $scenario->alliance($otherOwner);
        $otherRole = app(CreateAllianceRole::class)->handle($otherAlliance->allianceId, $otherOwner->playerId, 'Other content role', [AlliancePermission::ContentManage]);
        $otherMembership = AllianceMembership::query()->where('player_id', $otherOwner->playerId)->sole();
        $before = $this->grantSnapshot();
        try {
            app(AssignMembershipRole::class)->handle($s['alliance']->allianceId, $s['actor']->playerId,
                (string) ($foreignRole ? $s['target']->id : $otherMembership->id), $foreignRole ? $otherRole : $s['roleId']);
            self::fail('Cross-Alliance targets must not be writable.');
        } catch (ModelNotFoundException) {
            self::assertSame($before, $this->grantSnapshot());
        }
    }

    public function test_late_audit_failure_rolls_back_a_permitted_grant_and_its_evidence(): void
    {
        $s = $this->delegatedScenario([AlliancePermission::ContentManage]);
        $before = $this->grantSnapshot();
        $failed = false;
        DB::listen(static function (QueryExecuted $query) use (&$failed): void {
            if (! $failed && str_starts_with($query->sql, 'insert into "audit_events"')
                && in_array('membership.role_assigned', $query->bindings, true)) {
                $failed = true;
                throw new RuntimeException('Injected role grant audit failure.');
            }
        });
        try {
            app(AssignMembershipRole::class)->handle($s['alliance']->allianceId, $s['actor']->playerId, (string) $s['target']->id, $s['roleId']);
            self::fail('The real late audit failure must roll back the role grant.');
        } catch (RuntimeException $exception) {
            self::assertSame('Injected role grant audit failure.', $exception->getMessage());
        }
        self::assertTrue($failed);
        self::assertSame($before, $this->grantSnapshot());
    }

    public function test_rank_administrator_cannot_grant_a_rank_above_its_current_rank(): void
    {
        $s = $this->delegatedScenario();
        foreach ([false, true] as $preview) {
            try {
                if ($preview) {
                    app(PreviewBulkAllianceRankChange::class)->handle($s['alliance']->allianceId, $s['actor']->playerId, [(string) $s['target']->id], AllianceRank::R4);
                } else {
                    app(UpdateAllianceRank::class)->handle($s['alliance']->allianceId, $s['actor']->playerId, (string) $s['target']->id, AllianceRank::R4);
                }
                self::fail('Role management must not confer a higher rank.');
            } catch (ValidationException $exception) {
                self::assertArrayHasKey('rank', $exception->errors());
            }
        }
        self::assertSame(AllianceRank::R1, $s['target']->refresh()->rank);
        self::assertSame(0, DB::table('audit_events')->where('event', 'membership.rank_changed')->count());
        $result = app(BulkUpdateAllianceRank::class)->handle($s['alliance']->allianceId, $s['actor']->playerId, [(string) $s['target']->id], AllianceRank::R3);
        self::assertSame(1, $result['succeeded']);
        self::assertSame(AllianceRank::R3, $s['target']->refresh()->rank);
    }

    public function test_each_bulk_rank_change_rechecks_the_actors_current_rank(): void
    {
        $s = $this->delegatedScenario();
        $second = $this->membership($s['alliance'], (new ScenarioFactory)->unclaimedPlayer(59230));
        $changed = false;
        DB::listen(static function (QueryExecuted $query) use ($s, &$changed): void {
            if ($changed || ! str_starts_with($query->sql, 'insert into "outbox_messages"')
                || ! in_array('member.updated', $query->bindings, true)) {
                return;
            }
            $changed = true;
            app(UpdateAllianceRank::class)->handle($s['alliance']->allianceId, $s['owner']->playerId, (string) $s['actorMembership']->id, AllianceRank::R1);
        });
        $result = app(BulkUpdateAllianceRank::class)->handle($s['alliance']->allianceId, $s['actor']->playerId, [(string) $s['target']->id, (string) $second->id], AllianceRank::R2);
        self::assertTrue($changed);
        self::assertSame(1, $result['succeeded']);
        self::assertSame([(string) $second->id], $result['failedItemIds']);
        self::assertSame(AllianceRank::R2, $s['target']->refresh()->rank);
        self::assertSame(AllianceRank::R1, $second->refresh()->rank);
    }

    public function test_bulk_preview_query_count_does_not_grow_with_recipient_count(): void
    {
        $s = $this->delegatedScenario([AlliancePermission::ContentManage]);
        $ids = [(string) $s['target']->id];
        $scenario = new ScenarioFactory;
        for ($i = 0; $i < 19; $i++) {
            $ids[] = (string) $this->membership($s['alliance'], $scenario->unclaimedPlayer(59230))->id;
        }
        $queries = 0;
        DB::listen(static function (QueryExecuted $query) use (&$queries): void {
            if (str_starts_with($query->sql, 'select')) {
                $queries++;
            }
        });
        $preview = app(PreviewBulkMembershipRoleChange::class);
        $preview->handle($s['alliance']->allianceId, $s['actor']->playerId, [$ids[0]], $s['roleId'], 'assign');
        $singleQueries = $queries;
        $queries = 0;
        $result = $preview->handle($s['alliance']->allianceId, $s['actor']->playerId, $ids, $s['roleId'], 'assign');
        self::assertSame(20, $result['ready']);
        self::assertGreaterThan(0, $singleQueries);
        self::assertSame($singleQueries, $queries);
    }

    /**
     * @param  list<AlliancePermission>  $extraPermissions
     * @return array{alliance:AllianceReference,owner:PlayerReference,actor:PlayerReference,actorUser:User,actorMembership:AllianceMembership,target:AllianceMembership,roleId:string,actorRoleId:string}
     */
    private function delegatedScenario(array $extraPermissions = []): array
    {
        $scenario = new ScenarioFactory;
        $owner = $scenario->player((int) $scenario->authUser()->id, 59230);
        $alliance = $scenario->alliance($owner);
        $actorUser = $scenario->authUser();
        $actorUser->forceFill(['email_verified_at' => now()])->save();
        $actor = $scenario->player((int) $actorUser->id, 59230);
        $actorMembership = $this->membership($alliance, $actor, AllianceRank::R3);
        $target = $this->membership($alliance, $scenario->unclaimedPlayer(59230));
        $actorRoleId = app(CreateAllianceRole::class)->handle($alliance->allianceId, $owner->playerId, 'Role administrator', [AlliancePermission::RoleManage, ...$extraPermissions]);
        app(AssignMembershipRole::class)->handle($alliance->allianceId, $owner->playerId, (string) $actorMembership->id, $actorRoleId);
        $roleId = app(CreateAllianceRole::class)->handle($alliance->allianceId, $owner->playerId, 'Content writer', [AlliancePermission::ContentManage]);

        return compact('alliance', 'owner', 'actor', 'actorUser', 'actorMembership', 'target', 'roleId', 'actorRoleId');
    }

    private function membership(AllianceReference $alliance, PlayerReference $player, AllianceRank $rank = AllianceRank::R1): AllianceMembership
    {
        return AllianceMembership::query()->create([
            'alliance_id' => $alliance->allianceId, 'player_id' => $player->playerId,
            'status' => MembershipStatus::Active, 'rank' => $rank, 'joined_at' => now(),
        ]);
    }

    /** @return array{array<mixed>,int,int} */
    private function grantSnapshot(): array
    {
        return [
            DB::table('membership_roles')->orderBy('membership_id')->orderBy('role_id')->get()->map(static fn (object $row): array => (array) $row)->all(),
            DB::table('audit_events')->where('event', 'membership.role_assigned')->count(),
            DB::table('outbox_messages')->where('event_type', 'membership.role_assigned')->count(),
        ];
    }
}
