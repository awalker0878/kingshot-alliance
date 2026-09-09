<?php

declare(strict_types=1);

namespace Tests\Integration\Concurrency\Contexts\GameWorld\Players;

use App\Contexts\GameWorld\Kingdoms\Actions\ArchiveKingdom;
use App\Contexts\GameWorld\Players\Actions\CreatePlayerForAccount;
use App\Contexts\GameWorld\Players\Actions\PersistPlayerIdentity;
use App\Contexts\GameWorld\Players\Models\Player;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Support\ScenarioFactory;
use Tests\TestCase;

final class StablePlayerIdentityConcurrencyV3Test extends TestCase
{
    use DatabaseTruncation;

    /** @return iterable<string,array{bool}> */
    public static function orders(): iterable
    {
        yield 'first contender initiates' => [false];
        yield 'second contender initiates' => [true];
    }

    #[DataProvider('orders')]
    public function test_opposing_identity_edits_reject_without_foreign_player_locks(bool $reverse): void
    {
        $factory = app(ScenarioFactory::class);
        $first = $factory->unclaimedPlayer(59283);
        $second = $factory->unclaimedPlayer(59283);
        [$target, $other] = $reverse ? [$second, $first] : [$first, $second];
        $primary = $this->competitor();
        $attempted = false;
        $before = $this->state();
        DB::listen(static function (QueryExecuted $query) use ($primary, $target, $other, &$attempted): void {
            if ($attempted || $query->connectionName !== $primary || ! str_starts_with($query->sql, 'select * from "players"') || ! str_contains($query->sql, 'for update') || ! in_array($target->playerId, $query->bindings, true)) {
                return;
            }
            $attempted = true;
            DB::setDefaultConnection('stable_identity_writer');
            try {
                try {
                    app(PersistPlayerIdentity::class)->handle($other->kingdomId, 'Rejected reverse edit', $target->gamePlayerId, $other->playerId);
                    self::fail('The reverse stable-ID replacement must reject without waiting.');
                } catch (ValidationException $exception) {
                    self::assertArrayHasKey('game_player_id', $exception->errors());
                }
            } finally {
                DB::setDefaultConnection($primary);
            }
        });
        try {
            try {
                app(PersistPlayerIdentity::class)->handle($target->kingdomId, 'Rejected edit', $other->gamePlayerId, $target->playerId);
                self::fail('Stable identity cannot be replaced.');
            } catch (ValidationException $exception) {
                self::assertArrayHasKey('game_player_id', $exception->errors());
            }
            self::assertTrue($attempted);
            self::assertSame($before, $this->state());
        } finally {
            DB::setDefaultConnection($primary);
            DB::purge('stable_identity_writer');
        }
    }

    /** @return iterable<string,array{bool,bool}> */
    public static function absentClaims(): iterable
    {
        foreach ([false, true] as $attach) {
            yield ($attach ? 'attachment ' : 'creation ').'first winner' => [$attach, false];
            yield ($attach ? 'attachment ' : 'creation ').'second winner' => [$attach, true];
        }
    }

    #[DataProvider('absentClaims')]
    public function test_absent_stable_id_claims_have_one_winner_and_preserve_caller_transaction(bool $attach, bool $reverse): void
    {
        $kingdom = app(ScenarioFactory::class)->kingdom(59283);
        $first = $attach ? app(PersistPlayerIdentity::class)->handle($kingdom->kingdomId, 'First Governor', null) : null;
        $second = $attach ? app(PersistPlayerIdentity::class)->handle($kingdom->kingdomId, 'Second Governor', null) : null;
        [$winnerId, $loserId] = $reverse ? [$second?->playerId, $first?->playerId] : [$first?->playerId, $second?->playerId];
        $winnerName = $reverse ? 'Second committed Governor' : 'First committed Governor';
        $stableId = 'simultaneous-stable-59283';
        $primary = $this->competitor();
        $attempted = false;
        $afterWinner = null;
        DB::listen(function (QueryExecuted $query) use ($primary, $kingdom, $stableId, $winnerId, $winnerName, $attach, &$attempted, &$afterWinner): void {
            $absentLookup = $attach
                ? str_starts_with($query->sql, 'select exists(select * from "players"')
                : str_starts_with($query->sql, 'select * from "players"') && str_contains($query->sql, '"game_player_id"') && str_contains($query->sql, 'for update');
            if ($attempted || $query->connectionName !== $primary || ! $absentLookup || ! in_array($stableId, $query->bindings, true)) {
                return;
            }
            $attempted = true;
            DB::setDefaultConnection('stable_identity_writer');
            try {
                app(PersistPlayerIdentity::class)->handle($kingdom->kingdomId, $winnerName, $stableId, $winnerId);
                $afterWinner = $this->state();
            } finally {
                DB::setDefaultConnection($primary);
            }
        });
        try {
            DB::transaction(function () use ($kingdom, $stableId, $loserId, &$afterWinner): void {
                try {
                    app(PersistPlayerIdentity::class)->handle($kingdom->kingdomId, 'Uncommitted losing Governor', $stableId, $loserId);
                    self::fail('The unique stable-ID winner must become ordinary validation.');
                } catch (ValidationException $exception) {
                    self::assertArrayHasKey('game_player_id', $exception->errors());
                }
                self::assertSame(1, (int) DB::selectOne('select 1 as usable')->usable);
                self::assertSame($afterWinner, $this->state());
            });
            self::assertTrue($attempted);
            $winner = Player::query()->where('game_player_id', $stableId)->sole();
            self::assertSame($winnerName, $winner->current_name);
            self::assertSame(1, DB::table('player_identity_history')->where('player_id', $winner->id)->whereNull('valid_to')->count());
            if ($attach) {
                $beforeRetry = $this->state();
                try {
                    app(PersistPlayerIdentity::class)->handle($kingdom->kingdomId, 'Rejected retry', $stableId, $loserId);
                    self::fail('A losing attachment cannot replace the committed identity owner.');
                } catch (ValidationException) {
                    self::assertSame($beforeRetry, $this->state());
                }
            } else {
                $retry = app(PersistPlayerIdentity::class)->handle($kingdom->kingdomId, $winnerName, $stableId);
                self::assertSame((string) $winner->id, $retry->playerId);
                self::assertSame(1, Player::query()->count());
            }
        } finally {
            DB::setDefaultConnection($primary);
            DB::purge('stable_identity_writer');
        }
    }

    public function test_unrelated_primary_key_violation_is_preserved_after_owner_rollback(): void
    {
        $kingdom = app(ScenarioFactory::class)->kingdom(59283);
        $target = app(PersistPlayerIdentity::class)->handle($kingdom->kingdomId, 'Existing Governor', null);
        $before = $this->state();
        $failed = false;
        DB::listen(static function (QueryExecuted $query) use ($target, &$failed): void {
            if (! $failed && str_starts_with($query->sql, 'insert into "player_identity_history"')) {
                $failed = true;
                DB::table('players')->insert(['id' => $target->playerId, 'current_kingdom_id' => $target->kingdomId, 'current_name' => 'Rejected duplicate primary key']);
            }
        });
        DB::transaction(function () use ($target, $before): void {
            try {
                app(PersistPlayerIdentity::class)->handle($target->kingdomId, 'Uncommitted Governor', 'new-valid-stable-59283', $target->playerId);
                self::fail('Unrelated integrity errors must retain their original exception.');
            } catch (UniqueConstraintViolationException $exception) {
                self::assertStringContainsString('players_pkey', (string) ($exception->errorInfo[2] ?? ''));
            }
            self::assertSame(1, (int) DB::selectOne('select 1 as usable')->usable);
            self::assertSame($before, $this->state());
        });
        self::assertTrue($failed);
    }

    /** @return iterable<string,array{bool,bool}> */
    public static function registrationArrivals(): iterable
    {
        foreach ([false, true] as $owned) {
            yield ($owned ? 'other account registration ' : 'unclaimed identity ').'first caller' => [$owned, false];
            yield ($owned ? 'other account registration ' : 'unclaimed identity ').'second caller' => [$owned, true];
        }
    }

    #[DataProvider('registrationArrivals')]
    public function test_registration_cannot_adopt_an_identity_that_arrived_after_absence_read(bool $owned, bool $reverse): void
    {
        $factory = app(ScenarioFactory::class);
        $first = $factory->account();
        $second = $factory->account();
        [$account, $otherAccount] = $reverse ? [$second, $first] : [$first, $second];
        $kingdom = $factory->kingdom(59283);
        $stableId = 'registration-arrival-59283';
        $primary = $this->competitor();
        $attempted = false;
        $afterArrival = null;
        DB::listen(function (QueryExecuted $query) use ($primary, $kingdom, $stableId, $otherAccount, $owned, &$attempted, &$afterArrival): void {
            if ($attempted || $query->connectionName !== $primary || ! str_starts_with($query->sql, 'select * from "players"') || ! str_contains($query->sql, '"game_player_id"') || ! in_array($stableId, $query->bindings, true)) {
                return;
            }
            $attempted = true;
            DB::setDefaultConnection('stable_identity_writer');
            try {
                $owned
                    ? app(CreatePlayerForAccount::class)->handle($otherAccount->userId, $kingdom->kingdomId, 'Committed other account identity', $stableId)
                    : app(PersistPlayerIdentity::class)->handle($kingdom->kingdomId, 'Committed unclaimed identity', $stableId);
                $afterArrival = $this->state();
            } finally {
                DB::setDefaultConnection($primary);
            }
        });
        try {
            DB::transaction(function () use ($account, $kingdom, $stableId, &$afterArrival): void {
                try {
                    app(CreatePlayerForAccount::class)->handle($account->userId, $kingdom->kingdomId, 'Rejected account registration', $stableId);
                    self::fail('An absent lookup cannot authorize claiming a subsequently discovered identity.');
                } catch (ValidationException $exception) {
                    self::assertArrayHasKey('game_player_id', $exception->errors());
                }
                self::assertSame(1, (int) DB::selectOne('select 1 as usable')->usable);
                self::assertSame($afterArrival, $this->state());
            });
            self::assertTrue($attempted);
            $winner = Player::query()->where('game_player_id', $stableId)->sole();
            self::assertSame($owned ? $otherAccount->userId : null, $winner->user_id);
            $beforeRetry = $this->state();
            try {
                app(CreatePlayerForAccount::class)->handle($account->userId, $kingdom->kingdomId, 'Rejected retry', $stableId);
                self::fail('The current unclaimed/foreign identity still requires explicit claim or recovery.');
            } catch (ValidationException $exception) {
                self::assertArrayHasKey('game_player_id', $exception->errors());
                self::assertSame($beforeRetry, $this->state());
            }
        } finally {
            DB::setDefaultConnection($primary);
            DB::purge('stable_identity_writer');
        }
    }

    #[DataProvider('orders')]
    public function test_owned_registration_reuse_locks_kingdom_before_player_against_archival(bool $archivalFirst): void
    {
        $factory = app(ScenarioFactory::class);
        $account = $factory->account();
        $target = $factory->player($account->userId, 59283);
        $register = static fn () => app(CreatePlayerForAccount::class)->handle($account->userId, $target->kingdomId, 'Current owned registration', $target->gamePlayerId);
        $archive = static fn () => app(ArchiveKingdom::class)->handle($target->kingdomId);
        $primary = $this->competitor();
        $attempted = false;
        $kingdomLocked = false;
        $contenderPlayerLocked = false;
        DB::listen(static function (QueryExecuted $query) use ($primary, $target, $archivalFirst, $register, $archive, &$attempted, &$kingdomLocked, &$contenderPlayerLocked): void {
            if ($query->connectionName === $primary && str_starts_with($query->sql, 'select * from "kingdoms"') && str_contains($query->sql, 'for share') && in_array($target->kingdomId, $query->bindings, true)) {
                $kingdomLocked = true;
            }
            if ($query->connectionName === 'stable_identity_writer' && str_starts_with($query->sql, 'select * from "players"') && str_contains($query->sql, 'for update')) {
                $contenderPlayerLocked = true;
            }
            $barrier = $archivalFirst
                ? str_starts_with($query->sql, 'select * from "kingdoms"') && str_contains($query->sql, 'for update')
                : str_starts_with($query->sql, 'select * from "players"') && str_contains($query->sql, 'for update');
            if ($attempted || $query->connectionName !== $primary || ! $barrier) {
                return;
            }
            $attempted = true;
            if (! $archivalFirst) {
                self::assertTrue($kingdomLocked);
            }
            DB::setDefaultConnection('stable_identity_writer');
            try {
                try {
                    $archivalFirst ? $register() : $archive();
                    self::fail('Account registration must serialize with Kingdom archival before Player mutation.');
                } catch (QueryException $exception) {
                    self::assertSame('55P03', $exception->errorInfo[0] ?? null);
                }
                self::assertFalse($contenderPlayerLocked);
            } finally {
                DB::setDefaultConnection($primary);
            }
        });
        try {
            $archivalFirst ? $archive() : $register();
            self::assertTrue($attempted);
            if (! $archivalFirst) {
                self::assertSame($target->playerId, Player::query()->sole()->id);
                self::assertSame('Current owned registration', Player::query()->sole()->current_name);
                self::assertSame($account->userId, Player::query()->sole()->user_id);
                $archive();
            }
            $beforeRetry = $this->state();
            try {
                $register();
                self::fail('The current archived Kingdom must reject registration reuse.');
            } catch (ValidationException $exception) {
                self::assertArrayHasKey('kingdom', $exception->errors());
            }
            self::assertSame($beforeRetry, $this->state());
        } finally {
            DB::setDefaultConnection($primary);
            DB::purge('stable_identity_writer');
        }
    }

    #[DataProvider('orders')]
    public function test_late_registration_audit_failure_rolls_back_identity_claim_and_history(bool $existing): void
    {
        $factory = app(ScenarioFactory::class);
        $account = $factory->account();
        $kingdom = $factory->kingdom(59283);
        $stableId = 'registration-rollback-59283';
        if ($existing) {
            app(CreatePlayerForAccount::class)->handle($account->userId, $kingdom->kingdomId, 'Earlier owned Governor', $stableId);
        }
        $before = $this->state();
        $failed = false;
        DB::listen(static function (QueryExecuted $query) use ($existing, &$failed): void {
            if (! $failed && str_starts_with($query->sql, 'insert into "audit_events"') && in_array($existing ? 'player.name_changed' : 'player.claimed', $query->bindings, true)) {
                $failed = true;
                throw new RuntimeException('Injected registration audit failure.');
            }
        });
        try {
            app(CreatePlayerForAccount::class)->handle($account->userId, $kingdom->kingdomId, 'Uncommitted owned Governor', $stableId);
            self::fail('Identity, claim and history must roll back together.');
        } catch (RuntimeException $exception) {
            self::assertSame('Injected registration audit failure.', $exception->getMessage());
        }
        self::assertTrue($failed);
        self::assertSame($before, $this->state());
    }

    private function competitor(): string
    {
        $primary = DB::getDefaultConnection();
        config()->set('database.connections.stable_identity_writer', array_replace(DB::connection()->getConfig(), ['name' => 'stable_identity_writer']));
        DB::connection('stable_identity_writer')->statement("SET lock_timeout = '100ms'");

        return $primary;
    }

    /** @return array<string,string> */
    private function state(): array
    {
        $state = [];
        foreach (['players', 'player_identity_history', 'player_reconciliations', 'audit_events', 'outbox_messages'] as $table) {
            $state[$table] = DB::table($table)->orderBy('id')->get()->toJson();
        }

        return $state;
    }
}
