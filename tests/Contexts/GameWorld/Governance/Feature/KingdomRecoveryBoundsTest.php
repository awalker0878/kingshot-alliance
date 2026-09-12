<?php

declare(strict_types=1);

namespace Tests\Contexts\GameWorld\Governance\Feature;

use App\Contexts\Accounts\Identity\Queries\AccountIdentityQuery;
use App\Contexts\GameWorld\Governance\Actions\RemoveKingdomRole;
use App\Contexts\GameWorld\Governance\Actions\RepairKingdomAdministratorAssignment;
use App\Contexts\GameWorld\Governance\Models\KingdomRoleAssignment;
use App\Workflows\KingdomGovernance\Actions\BootstrapKingdomAdministrator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\ScenarioFactory;
use Tests\TestCase;

final class KingdomRecoveryBoundsTest extends TestCase
{
    use RefreshDatabase;

    public function test_replacement_bounds_history_and_preserves_a_reachable_recovery_path(): void
    {
        $factory = app(ScenarioFactory::class);
        $account = $factory->account();
        $old = $factory->player($account->userId, 61585);
        $target = $factory->player($account->userId, 61585);
        $kingdom = $factory->kingdom(61585);
        $initial = app(BootstrapKingdomAdministrator::class)->handle($kingdom->kingdomId, $old->playerId);
        $rows = [];
        for ($i = 0; $i < 500; $i++) {
            $rows[] = ['id' => strtolower((string) Str::ulid()), 'kingdom_id' => $kingdom->kingdomId,
                'player_id' => $old->playerId, 'kingdom_role_id' => $initial->administratorRoleId,
                'effective_from' => now()->addDay(), 'created_at' => now(), 'updated_at' => now()];
        }
        DB::table('kingdom_role_assignments')->insert($rows);
        $action = app(RepairKingdomAdministratorAssignment::class);
        $actor = app(AccountIdentityQuery::class)->require($account->userId);
        DB::enableQueryLog();
        try {
            $action->handle($actor, $kingdom->kingdomId, $target->playerId, 'Confirmed recovery incident.', true);
            self::fail('Oversized replacement must fail before creating a target assignment.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey('replace_existing', $exception->errors());
        }
        $sql = implode("\n", array_column(DB::getQueryLog(), 'query'));
        DB::disableQueryLog();
        self::assertStringContainsString('limit 501 for update', $sql);
        self::assertFalse(KingdomRoleAssignment::query()->where('player_id', $target->playerId)->exists());
        self::assertSame(501, KingdomRoleAssignment::query()->whereNull('revoked_at')->count());
        self::assertSame(0, DB::table('audit_events')->where('event', 'kingdom.administrator_recovered')->count());
        $recovered = $action->handle($actor, $kingdom->kingdomId, $target->playerId, 'Confirmed recovery incident.', false);
        // Existing GameWorld removal accepts scheduled assignments by ID and protects the last administrator.
        app(RemoveKingdomRole::class)->handle($target->playerId, $kingdom->kingdomId, $rows[0]['id'], 'Scheduled duplicate cleanup.');
        $reason = str_repeat('界', 500);
        $same = $action->handle($actor, $kingdom->kingdomId, $target->playerId, $reason, true);
        self::assertSame($recovered->assignmentId, $same->assignmentId);
        self::assertSame(1, KingdomRoleAssignment::query()->whereNull('revoked_at')->count());
        self::assertSame($reason, KingdomRoleAssignment::query()->findOrFail($initial->assignmentId)->revocation_reason);
        self::assertSame(499, KingdomRoleAssignment::query()->whereIn('id', array_column(array_slice($rows, 1), 'id'))->whereNotNull('revoked_at')->count());
    }

    /** @return iterable<string,array{string}> */
    public static function invalidStates(): iterable
    {
        foreach (['archived', 'alias', 'foreign', 'short reason', 'long reason'] as $state) {
            yield $state => [$state];
        }
    }

    #[DataProvider('invalidStates')]
    public function test_owner_rejects_invalid_current_target_or_reason_without_writes(string $state): void
    {
        $factory = app(ScenarioFactory::class);
        $account = $factory->account();
        $kingdom = $factory->kingdom(61586);
        $target = $factory->unclaimedPlayer($state === 'foreign' ? 61587 : 61586);
        if ($state === 'archived') {
            DB::table('kingdoms')->where('id', $kingdom->kingdomId)->update(['status' => 'archived']);
        } elseif ($state === 'alias') {
            $canonical = $factory->unclaimedPlayer(61586);
            DB::table('players')->where('id', $target->playerId)->update(['canonical_player_id' => $canonical->playerId]);
        }
        $reason = match ($state) {
            'short reason' => 'too short',
            'long reason' => str_repeat('界', 501),
            default => 'Verified recovery incident.',
        };
        try {
            app(RepairKingdomAdministratorAssignment::class)->handle(app(AccountIdentityQuery::class)->require($account->userId),
                $kingdom->kingdomId, $target->playerId, $reason, true);
            self::fail('Invalid current recovery input must fail.');
        } catch (ValidationException) {
            self::assertSame(0, DB::table('kingdom_role_assignments')->count());
            self::assertSame(0, DB::table('kingdom_roles')->count());
        }
    }

    public function test_maximum_unicode_reason_fits_the_canonical_assignment_column(): void
    {
        $factory = app(ScenarioFactory::class);
        $account = $factory->account();
        $kingdom = $factory->kingdom(61588);
        $target = $factory->unclaimedPlayer(61588);
        $reason = str_repeat('界', 500);
        $result = app(RepairKingdomAdministratorAssignment::class)->handle(app(AccountIdentityQuery::class)->require($account->userId),
            $kingdom->kingdomId, $target->playerId, $reason, true);
        self::assertSame($reason, KingdomRoleAssignment::query()->findOrFail($result->assignmentId)->reason);
    }
}
