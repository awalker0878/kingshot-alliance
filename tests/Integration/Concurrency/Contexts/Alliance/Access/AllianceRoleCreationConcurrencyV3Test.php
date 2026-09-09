<?php

declare(strict_types=1);

namespace Tests\Integration\Concurrency\Contexts\Alliance\Access;

use App\Contexts\Alliance\Access\Actions\AssignMembershipRole;
use App\Contexts\Alliance\Access\Actions\CreateAllianceRole;
use App\Contexts\Alliance\Access\Enums\AlliancePermission;
use App\Contexts\Alliance\Access\Models\Role;
use App\Contexts\Alliance\Lifecycle\ValueObjects\AllianceReference;
use App\Contexts\Alliance\Membership\Enums\AllianceRank;
use App\Contexts\Alliance\Membership\Enums\MembershipStatus;
use App\Contexts\Alliance\Membership\Models\AllianceMembership;
use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Support\ScenarioFactory;
use Tests\TestCase;

final class AllianceRoleCreationConcurrencyV3Test extends TestCase
{
    use DatabaseMigrations;

    /** @return iterable<string,array{bool}> */
    public static function competingKeys(): iterable
    {
        yield 'same key' => [true];
        yield 'different keys' => [false];
    }

    #[DataProvider('competingKeys')]
    public function test_competing_creators_preserve_one_winner_per_key_and_usable_outer_transactions(bool $sameKey): void
    {
        $s = $this->scenario();
        $primary = DB::getDefaultConnection();
        config()->set('database.connections.role_creator', array_replace(DB::connection()->getConfig(), ['name' => 'role_creator']));
        DB::connection('role_creator')->statement("SET lock_timeout = '100ms'");
        $competed = false;
        $winnerId = null;
        DB::listen(function (QueryExecuted $query) use ($s, $primary, $sameKey, &$competed, &$winnerId): void {
            if ($competed || $query->connectionName !== $primary
                || ! str_starts_with($query->sql, 'select * from "roles"')
                || ! in_array('contended-role', $query->bindings, true)) {
                return;
            }
            $competed = true;
            DB::setDefaultConnection('role_creator');
            try {
                $winnerId = app(CreateAllianceRole::class)->handle(
                    $s['alliance']->allianceId, $s['other']->playerId,
                    $sameKey ? 'Contended Role' : 'Independent Role', [],
                );
            } finally {
                DB::setDefaultConnection($primary);
            }
        });

        try {
            DB::transaction(function () use ($s, $sameKey): void {
                try {
                    app(CreateAllianceRole::class)->handle($s['alliance']->allianceId, $s['leader']->playerId, 'Contended Role', [AlliancePermission::ContentManage]);
                    self::assertFalse($sameKey, 'A competing winner must produce validation feedback.');
                } catch (ValidationException $exception) {
                    self::assertTrue($sameKey);
                    self::assertArrayHasKey('name', $exception->errors());
                }
                self::assertSame(1, (int) DB::selectOne('select 1 as usable')->usable);
            });
            self::assertTrue($competed);
            self::assertNotNull($winnerId);
            self::assertSame(0, Role::query()->findOrFail($winnerId)->permissions()->count(), 'The loser must not overwrite the winning definition.');
            $role = Role::query()->where('alliance_id', $s['alliance']->allianceId)->where('key', 'contended-role')->sole();
            self::assertSame($sameKey ? 0 : 1, $role->permissions()->count());
            self::assertSame(1, DB::table('audit_events')->where('event', 'alliance.role_created')->where('subject_id', $role->id)->count());
            self::assertSame(1, DB::table('outbox_messages')->where('event_type', 'alliance.role_created')->where('aggregate_id', $role->id)->count());
            $before = $this->state();
            try {
                app(CreateAllianceRole::class)->handle($s['alliance']->allianceId, $s['leader']->playerId, 'Contended Role', []);
                self::fail('Retrying an existing key is still validation feedback.');
            } catch (ValidationException $exception) {
                self::assertArrayHasKey('name', $exception->errors());
            }
            self::assertSame($before, $this->state());
        } finally {
            DB::setDefaultConnection($primary);
            DB::purge('role_creator');
        }
    }

    public function test_an_unrelated_unique_constraint_failure_is_not_mislabeled_as_a_name_collision(): void
    {
        $s = $this->scenario();
        $before = $this->state();
        $existingId = Role::query()->where('alliance_id', $s['alliance']->allianceId)->value('id');
        Role::creating(static function (Role $role) use ($existingId): void {
            if ($role->key === 'different-key') {
                $role->id = (string) $existingId;
            }
        });
        try {
            app(CreateAllianceRole::class)->handle($s['alliance']->allianceId, $s['leader']->playerId, 'Different Key', []);
            self::fail('The original primary-key failure must propagate.');
        } catch (UniqueConstraintViolationException $exception) {
            self::assertSame('23505', $exception->errorInfo[0] ?? null);
        }
        self::assertSame($before, $this->state());
    }

    public function test_late_audit_failure_rolls_back_the_created_role_and_permissions(): void
    {
        $s = $this->scenario();
        $before = $this->state();
        $failed = false;
        DB::listen(static function (QueryExecuted $query) use (&$failed): void {
            if (! $failed && str_starts_with($query->sql, 'insert into "audit_events"') && in_array('alliance.role_created', $query->bindings, true)) {
                $failed = true;
                throw new RuntimeException('Injected role creation audit failure.');
            }
        });
        try {
            app(CreateAllianceRole::class)->handle($s['alliance']->allianceId, $s['leader']->playerId, 'Rolled Back Role', [AlliancePermission::ContentManage]);
            self::fail('The injected audit failure must roll back role creation.');
        } catch (RuntimeException $exception) {
            self::assertSame('Injected role creation audit failure.', $exception->getMessage());
        }
        self::assertTrue($failed);
        self::assertSame($before, $this->state());
    }

    /** @return array{alliance:AllianceReference,leader:PlayerReference,other:PlayerReference} */
    private function scenario(): array
    {
        $factory = app(ScenarioFactory::class);
        $user = $factory->authUser();
        $leader = $factory->player((int) $user->id, 59254);
        $alliance = $factory->alliance($leader);
        $other = $factory->unclaimedPlayer(59254);
        $membership = AllianceMembership::query()->create([
            'alliance_id' => $alliance->allianceId, 'player_id' => $other->playerId,
            'rank' => AllianceRank::R1, 'status' => MembershipStatus::Active, 'joined_at' => now(),
        ]);
        $adminRole = app(CreateAllianceRole::class)->handle($alliance->allianceId, $leader->playerId, 'Role administrator', [AlliancePermission::RoleManage]);
        app(AssignMembershipRole::class)->handle($alliance->allianceId, $leader->playerId, (string) $membership->id, $adminRole);

        return compact('alliance', 'leader', 'other');
    }

    /** @return array<string,mixed> */
    private function state(): array
    {
        return [
            'roles' => DB::table('roles')->orderBy('id')->get()->toJson(),
            'permissions' => DB::table('role_permissions')->orderBy('role_id')->orderBy('permission_id')->get()->toJson(),
            'audit' => DB::table('audit_events')->count(),
            'outbox' => DB::table('outbox_messages')->count(),
        ];
    }
}
