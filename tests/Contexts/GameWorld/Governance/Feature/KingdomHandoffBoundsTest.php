<?php

declare(strict_types=1);

namespace Tests\Contexts\GameWorld\Governance\Feature;

use App\Contexts\Accounts\Identity\Queries\AccountIdentityQuery;
use App\Contexts\GameWorld\Governance\Actions\AssignKingdomRole;
use App\Contexts\GameWorld\Governance\Actions\HandoffKingdomAdministrator;
use App\Contexts\GameWorld\Governance\Actions\RemoveKingdomRole;
use App\Contexts\GameWorld\Governance\Actions\RepairKingdomAdministratorAssignment;
use App\Contexts\GameWorld\Governance\Enums\KingdomPermission;
use App\Contexts\GameWorld\Governance\Models\KingdomRoleAssignment;
use App\Contexts\GameWorld\Governance\Services\KingdomAuthorization;
use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;
use App\Workflows\KingdomGovernance\Actions\BootstrapKingdomAdministrator;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Support\ScenarioFactory;
use Tests\TestCase;

final class KingdomHandoffBoundsTest extends TestCase
{
    use RefreshDatabase;

    public function test_oversized_handoff_is_atomic_and_additive_cleanup_reaches_bounded_replacement_including_future_grants(): void
    {
        [$actor, $target, $role, $assignment] = $this->administration();
        $rows = [];
        for ($i = 0; $i < 500; $i++) {
            $rows[] = ['id' => strtolower((string) Str::ulid()), 'kingdom_id' => $actor->kingdomId, 'player_id' => $actor->playerId,
                'kingdom_role_id' => $role, 'effective_from' => now()->addDay(), 'created_at' => now(), 'updated_at' => now()];
        }
        DB::table('kingdom_role_assignments')->insert($rows);
        $handoff = app(HandoffKingdomAdministrator::class);
        DB::enableQueryLog();
        try {
            $handoff->handle($actor->playerId, $actor->kingdomId, $target->playerId, true, 'Planned handoff.');
            self::fail('Oversized replacement must not grant or revoke partial authority.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey('replace_actor', $exception->errors());
        }
        $sql = implode("\n", array_column(DB::getQueryLog(), 'query'));
        DB::disableQueryLog();
        self::assertStringContainsString('limit 501 for update', $sql);
        self::assertSame(0, DB::table('kingdom_role_assignments')->where('player_id', $target->playerId)->count());
        self::assertSame(501, DB::table('kingdom_role_assignments')->whereNull('revoked_at')->count());
        self::assertSame(0, DB::table('audit_events')->where('event', 'kingdom.administrator_handoff')->count());
        $targetAssignment = $handoff->handle($actor->playerId, $actor->kingdomId, $target->playerId, false, 'Planned handoff.');
        app(RemoveKingdomRole::class)->handle($target->playerId, $actor->kingdomId, $rows[0]['id'], 'Remove scheduled duplicate.');
        self::assertSame($targetAssignment, $handoff->handle($actor->playerId, $actor->kingdomId, $target->playerId, true, str_repeat('界', 500)));
        self::assertSame(str_repeat('界', 500), KingdomRoleAssignment::query()->findOrFail($assignment)->revocation_reason);
        self::assertSame(500, DB::table('kingdom_role_assignments')->whereIn('id', array_column($rows, 'id'))->whereNotNull('revoked_at')->count());
        $this->travel(2)->days();
        self::assertFalse(app(KingdomAuthorization::class)->allows($actor->playerId, $actor->kingdomId, KingdomPermission::RoleManage));
        self::assertTrue(app(KingdomAuthorization::class)->allows($target->playerId, $actor->kingdomId, KingdomPermission::RoleManage));
    }

    public function test_self_and_additive_replays_do_not_create_additional_audit_or_outbox_effects(): void
    {
        [$actor, $target, , $assignment] = $this->administration();
        $handoff = app(HandoffKingdomAdministrator::class);
        $before = $this->counts();
        self::assertSame($assignment, $handoff->handle($actor->playerId, $actor->kingdomId, $actor->playerId, true));
        self::assertSame($before, $this->counts());
        $created = $handoff->handle($actor->playerId, $actor->kingdomId, $target->playerId, false, str_repeat('界', 500));
        self::assertSame(str_repeat('界', 500), DB::table('kingdom_role_assignments')->where('id', $created)->value('reason'));
        $after = $this->counts();
        self::assertSame($created, $handoff->handle($actor->playerId, $actor->kingdomId, $target->playerId, false, 'Retry same operation.'));
        self::assertSame($after, $this->counts());
    }

    /** @return iterable<string,array{bool}> */
    public static function recoveryCommands(): iterable
    {
        yield 'Governor handoff' => [false];
        yield 'operator recovery' => [true];
    }

    #[DataProvider('recoveryCommands')]
    public function test_replacement_of_a_temporary_target_establishes_lasting_authority(bool $recovery): void
    {
        [$actor, $target, $role] = $this->administration();
        $temporary = app(AssignKingdomRole::class)->handle($actor->playerId, $actor->kingdomId, $target->playerId, $role, null, now()->addDay()->toIso8601String());
        if ($recovery) {
            $operator = app(ScenarioFactory::class)->account();
            $result = app(RepairKingdomAdministratorAssignment::class)->handle(app(AccountIdentityQuery::class)->require($operator->userId), $actor->kingdomId, $target->playerId, 'Verified lasting recovery.', true)->assignmentId;
        } else {
            $result = app(HandoffKingdomAdministrator::class)->handle($actor->playerId, $actor->kingdomId, $target->playerId, true);
        }
        self::assertNotSame($temporary, $result);
        self::assertNull(DB::table('kingdom_role_assignments')->where('id', $result)->value('expires_at'));
        $this->travel(2)->days();
        self::assertTrue(app(KingdomAuthorization::class)->allows($target->playerId, $actor->kingdomId, KingdomPermission::RoleManage));
        self::assertFalse(app(KingdomAuthorization::class)->allows($actor->playerId, $actor->kingdomId, KingdomPermission::RoleManage));
    }

    /** @return iterable<string,array{string}> */
    public static function invalidInputs(): iterable
    {
        foreach (['alias', 'foreign', 'long reason'] as $state) {
            yield $state => [$state];
        }
    }

    #[DataProvider('invalidInputs')]
    public function test_invalid_target_or_reason_has_no_effects(string $state): void
    {
        [$actor, $target] = $this->administration();
        if ($state === 'alias') {
            DB::table('players')->where('id', $target->playerId)->update(['canonical_player_id' => $actor->playerId]);
        } elseif ($state === 'foreign') {
            DB::table('players')->where('id', $target->playerId)->update(['current_kingdom_id' => app(ScenarioFactory::class)->kingdom(61626)->kingdomId]);
        }
        $before = $this->counts();
        try {
            app(HandoffKingdomAdministrator::class)->handle($actor->playerId, $actor->kingdomId, $target->playerId, true, $state === 'long reason' ? str_repeat('界', 501) : null);
            self::fail('Invalid handoff input must be rejected.');
        } catch (ValidationException) {
            self::assertSame($before, $this->counts());
        }
    }

    /** @return iterable<string,array{string}> */
    public static function effectFailures(): iterable
    {
        yield 'audit failure' => ['audit_events'];
        yield 'outbox failure' => ['outbox_messages'];
    }

    #[DataProvider('effectFailures')]
    public function test_late_failure_rolls_back_target_grant_and_actor_revocation_before_successful_retry(string $table): void
    {
        [$actor, $target, , $assignment] = $this->administration();
        $before = $this->counts();
        $failed = false;
        DB::listen(static function (QueryExecuted $query) use ($table, &$failed): void {
            if (! $failed && str_starts_with($query->sql, 'insert into "'.$table.'"') && in_array('kingdom.administrator_handoff', $query->bindings, true)) {
                $failed = true;
                throw new RuntimeException('Injected handoff effect failure.');
            }
        });
        $handoff = app(HandoffKingdomAdministrator::class);
        try {
            $handoff->handle($actor->playerId, $actor->kingdomId, $target->playerId, true);
            self::fail('Late failure must restore both sides of the handoff.');
        } catch (RuntimeException $exception) {
            self::assertSame('Injected handoff effect failure.', $exception->getMessage());
        }
        self::assertTrue($failed);
        self::assertSame($before, $this->counts());
        self::assertNull(DB::table('kingdom_role_assignments')->where('id', $assignment)->value('revoked_at'));
        $handoff->handle($actor->playerId, $actor->kingdomId, $target->playerId, true);
        self::assertSame(1, DB::table('audit_events')->where('event', 'kingdom.administrator_handoff')->count());
        self::assertSame(1, DB::table('outbox_messages')->where('event_type', 'kingdom.administrator_handoff')->count());
        self::assertNotNull(DB::table('kingdom_role_assignments')->where('id', $assignment)->value('revoked_at'));
    }

    /** @return iterable<string,array{string}> */
    public static function ineligibleSurvivors(): iterable
    {
        foreach (['alias', 'foreign', 'future', 'temporary'] as $state) {
            yield $state => [$state];
        }
    }

    #[DataProvider('ineligibleSurvivors')]
    public function test_last_lasting_administrator_is_not_removed_based_on_ineligible_survivor(string $state): void
    {
        [$actor, $target, $role, $assignment] = $this->administration();
        KingdomRoleAssignment::query()->create(['kingdom_id' => $actor->kingdomId, 'player_id' => $target->playerId, 'kingdom_role_id' => $role,
            'effective_from' => $state === 'future' ? now()->addDay() : null, 'expires_at' => $state === 'temporary' ? now()->addDay() : null]);
        if ($state === 'alias') {
            DB::table('players')->where('id', $target->playerId)->update(['canonical_player_id' => $actor->playerId]);
        } elseif ($state === 'foreign') {
            DB::table('players')->where('id', $target->playerId)->update(['current_kingdom_id' => app(ScenarioFactory::class)->kingdom(61626)->kingdomId]);
        }
        try {
            app(RemoveKingdomRole::class)->handle($actor->playerId, $actor->kingdomId, $assignment);
            self::fail('A current lasting administrator must survive removal.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey('role', $exception->errors());
        }
        self::assertNull(DB::table('kingdom_role_assignments')->where('id', $assignment)->value('revoked_at'));
    }

    /** @return array{PlayerReference,PlayerReference,string,string} */
    private function administration(): array
    {
        $factory = app(ScenarioFactory::class);
        $actor = $factory->unclaimedPlayer(61625);
        $target = $factory->unclaimedPlayer(61625);
        $roles = app(BootstrapKingdomAdministrator::class)->handle($actor->kingdomId, $actor->playerId);

        return [$actor, $target, $roles->administratorRoleId, $roles->assignmentId];
    }

    /** @return array{int,int,int} */
    private function counts(): array
    {
        return [DB::table('kingdom_role_assignments')->count(), DB::table('audit_events')->count(), DB::table('outbox_messages')->count()];
    }
}
