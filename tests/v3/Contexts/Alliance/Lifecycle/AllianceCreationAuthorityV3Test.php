<?php

declare(strict_types=1);

namespace Tests\v3\Contexts\Alliance\Lifecycle;

use App\Contexts\Alliance\Lifecycle\Actions\CreateAlliance;
use App\Contexts\Alliance\Lifecycle\Models\Alliance;
use App\Contexts\Alliance\Membership\Actions\UpdateMembershipStatus;
use App\Contexts\Alliance\Membership\Enums\AllianceRank;
use App\Contexts\Alliance\Membership\Enums\MembershipStatus;
use App\Contexts\Alliance\Membership\Models\AllianceMembership;
use App\Contexts\GameWorld\Kingdoms\Actions\ArchiveKingdom;
use App\Contexts\GameWorld\Players\Actions\PersistPlayerIdentity;
use App\Contexts\GameWorld\Players\Actions\ReconcilePlayers;
use App\Contexts\GameWorld\Players\Actions\ReleasePlayerAccount;
use App\Contexts\GameWorld\Players\Enums\PlayerIdentitySource;
use App\Contexts\GameWorld\Players\Models\Player;
use App\Contexts\Platform\DataGovernance\Actions\ProcessAccountDeletionRequests;
use App\Contexts\Platform\DataGovernance\Models\AccountDeletionRequest;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\v3\Support\ScenarioFactory;
use Tests\v3\TestCase;

final class AllianceCreationAuthorityV3Test extends TestCase
{
    use DatabaseMigrations;

    /** @return iterable<string,array{string,bool}> */
    public static function competingOwners(): iterable
    {
        foreach (['release', 'deletion', 'reconciliation', 'creation'] as $operation) {
            yield $operation.' first' => [$operation, false];
            yield 'creation before '.$operation => [$operation, true];
        }
    }

    #[DataProvider('competingOwners')]
    public function test_creation_and_owner_changes_serialize_in_both_commit_orders(string $operation, bool $creationFirst): void
    {
        $factory = app(ScenarioFactory::class);
        $account = $factory->account();
        $player = $factory->player($account->userId, 59261);
        $canonical = app(PersistPlayerIdentity::class)->handle($player->kingdomId, 'Canonical Governor', null);
        $request = $operation === 'deletion' ? AccountDeletionRequest::query()->create([
            'user_id' => $account->userId, 'status' => 'pending',
            'requested_at' => now()->subDay(), 'eligible_at' => now()->subMinute(),
        ]) : null;
        $create = static fn () => app(CreateAlliance::class)->handle($account->userId, $player->playerId, 'Primary Alliance', 'primary-alliance');
        $change = static fn () => match ($operation) {
            'release' => app(ReleasePlayerAccount::class)->handle($account->userId, $player->playerId),
            'deletion' => app(ProcessAccountDeletionRequests::class)->handle(),
            'reconciliation' => app(ReconcilePlayers::class)->handle($canonical->playerId, $player->playerId, 'Confirmed duplicate'),
            'creation' => app(CreateAlliance::class)->handle($account->userId, $player->playerId, 'Competing Alliance', 'competing-alliance'),
        };

        $primary = DB::getDefaultConnection();
        config()->set('database.connections.creation_competitor', array_replace(DB::connection()->getConfig(), ['name' => 'creation_competitor']));
        DB::connection()->statement("SET lock_timeout = '100ms'");
        DB::connection('creation_competitor')->statement("SET lock_timeout = '100ms'");
        $holder = $creationFirst ? $primary : 'creation_competitor';
        $contender = $creationFirst ? 'creation_competitor' : $primary;
        $attempted = false;
        DB::listen(static function (QueryExecuted $query) use ($account, $holder, $contender, $creationFirst, $create, $change, &$attempted): void {
            if ($attempted || $query->connectionName !== $holder || ! str_starts_with($query->sql, 'select * from "users"')
                || ! str_contains($query->sql, 'for update') || ! in_array((string) $account->userId, array_map('strval', $query->bindings), true)) {
                return;
            }
            $attempted = true;
            DB::setDefaultConnection($contender);
            try {
                try {
                    $creationFirst ? $change() : $create();
                    self::fail('The competing owner must wait for the current account transaction.');
                } catch (QueryException $exception) {
                    self::assertSame('55P03', $exception->errorInfo[0] ?? null);
                }
            } finally {
                DB::setDefaultConnection($holder);
            }
        });

        try {
            DB::setDefaultConnection($holder);
            $creationFirst ? $create() : $change();
            self::assertTrue($attempted);
            DB::setDefaultConnection($contender);
            if ($creationFirst && $operation === 'deletion') {
                self::assertSame(0, $change());
                self::assertSame('blocked', $request?->fresh()?->status);
            } else {
                try {
                    $creationFirst ? $change() : $create();
                    self::fail('The later operation must use the winner\'s current facts.');
                } catch (ValidationException $exception) {
                    self::assertNotEmpty($exception->errors());
                }
            }
            self::assertSame($creationFirst || $operation === 'creation' ? 1 : 0, Alliance::query()->count());
            $persisted = Player::query()->findOrFail($player->playerId);
            self::assertSame($creationFirst || $operation === 'creation' ? $account->userId : null, $persisted->user_id);
            if (! $creationFirst && $operation === 'deletion') {
                self::assertSame('processed', $request?->fresh()?->status);
            }
        } finally {
            DB::setDefaultConnection($primary);
            DB::connection()->statement('RESET lock_timeout');
            DB::purge('creation_competitor');
        }
    }

    public function test_current_actor_must_own_the_player_and_the_kingdom_must_remain_active(): void
    {
        $factory = app(ScenarioFactory::class);
        $account = $factory->account();
        $other = $factory->account();
        $player = $factory->player($account->userId, 59261);
        try {
            app(CreateAlliance::class)->handle($other->userId, $player->playerId, 'Wrong Owner', 'wrong-owner');
            self::fail('A supplied Player ID cannot substitute for account ownership.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey('player', $exception->errors());
        }
        app(ArchiveKingdom::class)->handle($player->kingdomId);
        try {
            app(CreateAlliance::class)->handle($account->userId, $player->playerId, 'Archived Kingdom', 'archived-kingdom');
            self::fail('An archived Kingdom cannot acquire a new active Alliance.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey('kingdom', $exception->errors());
        }
        self::assertSame(0, Alliance::query()->count());
    }

    public function test_a_kingdom_move_between_discovery_and_player_lock_rejects_stale_creation(): void
    {
        $factory = app(ScenarioFactory::class);
        $account = $factory->account();
        $player = $factory->player($account->userId, 59261);
        $destination = $factory->kingdom(59262);
        $primary = DB::getDefaultConnection();
        config()->set('database.connections.moving_player', array_replace(DB::connection()->getConfig(), ['name' => 'moving_player']));
        DB::connection('moving_player')->statement("SET lock_timeout = '100ms'");
        $moved = false;
        DB::listen(static function (QueryExecuted $query) use ($player, $destination, $primary, &$moved): void {
            if ($moved || $query->connectionName !== $primary || ! str_starts_with($query->sql, 'select * from "kingdoms"') || ! str_contains($query->sql, 'for share')) {
                return;
            }
            $moved = true;
            DB::setDefaultConnection('moving_player');
            try {
                app(PersistPlayerIdentity::class)->handle(
                    $destination->kingdomId,
                    $player->currentName,
                    $player->gamePlayerId,
                    $player->playerId,
                    PlayerIdentitySource::Import,
                    reason: 'Current placement observed by system ingestion.',
                );
            } finally {
                DB::setDefaultConnection($primary);
            }
        });
        try {
            try {
                app(CreateAlliance::class)->handle($account->userId, $player->playerId, 'Stale Kingdom', 'stale-kingdom');
                self::fail('Creation must recheck the locked Player placement.');
            } catch (ValidationException $exception) {
                self::assertArrayHasKey('player', $exception->errors());
            }
            self::assertTrue($moved);
            self::assertSame(0, Alliance::query()->count());
            self::assertSame($destination->kingdomId, Player::query()->findOrFail($player->playerId)->current_kingdom_id);
        } finally {
            DB::setDefaultConnection($primary);
            DB::purge('moving_player');
        }
    }

    /** @return iterable<string,array{bool}> */
    public static function admissionOrders(): iterable
    {
        yield 'creation first' => [true];
        yield 'activation first' => [false];
    }

    #[DataProvider('admissionOrders')]
    public function test_competing_membership_activation_leaves_one_membership_and_no_partial_alliance(bool $creationFirst): void
    {
        $factory = app(ScenarioFactory::class);
        $account = $factory->account();
        $player = $factory->player($account->userId, 59261);
        $officerAccount = $factory->account();
        $officer = $factory->player($officerAccount->userId, 59261);
        $alliance = $factory->alliance($officer);
        $membership = AllianceMembership::query()->create([
            'alliance_id' => $alliance->allianceId, 'player_id' => $player->playerId,
            'status' => MembershipStatus::Suspended, 'rank' => AllianceRank::R1,
        ]);
        $create = static fn () => app(CreateAlliance::class)->handle($account->userId, $player->playerId, 'Competing Membership', 'competing-membership');
        $activate = static fn () => app(UpdateMembershipStatus::class)->handle($alliance->allianceId, $officer->playerId, (string) $membership->id, MembershipStatus::Active);
        $primary = DB::getDefaultConnection();
        config()->set('database.connections.membership_activator', array_replace(DB::connection()->getConfig(), ['name' => 'membership_activator']));
        DB::connection('membership_activator')->statement("SET lock_timeout = '100ms'");
        $activated = false;
        DB::listen(static function (QueryExecuted $query) use ($player, $creationFirst, $create, $activate, $primary, &$activated): void {
            if ($activated || $query->connectionName !== $primary || ! str_starts_with($query->sql, 'select * from "players"')
                || ! str_contains($query->sql, $creationFirst ? 'for update' : 'for share') || ! in_array($player->playerId, $query->bindings, true)) {
                return;
            }
            $activated = true;
            DB::setDefaultConnection('membership_activator');
            try {
                try {
                    $creationFirst ? $activate() : $create();
                    self::fail('Creation and activation must serialize on current Player identity.');
                } catch (QueryException $exception) {
                    self::assertSame('55P03', $exception->errorInfo[0] ?? null);
                }
            } finally {
                DB::setDefaultConnection($primary);
            }
        });
        try {
            $creationFirst ? $create() : $activate();
            DB::transaction(static function () use ($creationFirst, $create, $activate): void {
                try {
                    $creationFirst ? $activate() : $create();
                    self::fail('The competing active membership must be preserved.');
                } catch (ValidationException $exception) {
                    self::assertArrayHasKey($creationFirst ? 'status' : 'player', $exception->errors());
                }
                self::assertSame(1, (int) DB::selectOne('select 1 as usable')->usable);
            });
            self::assertTrue($activated);
            self::assertSame($creationFirst ? MembershipStatus::Suspended : MembershipStatus::Active, $membership->fresh()?->status);
            self::assertSame($creationFirst ? 2 : 1, Alliance::query()->count());
            self::assertSame($creationFirst ? 1 : 0, Alliance::query()->where('slug', 'competing-membership')->count());
            self::assertSame(1, AllianceMembership::query()->where('player_id', $player->playerId)->where('status', 'active')->count());
        } finally {
            DB::setDefaultConnection($primary);
            DB::purge('membership_activator');
        }
    }
}
