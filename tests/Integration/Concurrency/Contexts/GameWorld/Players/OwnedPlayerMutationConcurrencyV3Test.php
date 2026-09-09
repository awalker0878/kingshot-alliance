<?php

declare(strict_types=1);

namespace Tests\Integration\Concurrency\Contexts\GameWorld\Players;

use App\Contexts\GameWorld\Players\Actions\ClaimPlayerAccount;
use App\Contexts\GameWorld\Players\Actions\MoveOwnedPlayerToKingdom;
use App\Contexts\GameWorld\Players\Actions\PersistPlayerIdentity;
use App\Contexts\GameWorld\Players\Actions\ReleasePlayerAccount;
use App\Contexts\GameWorld\Players\Actions\UpdateOwnedPlayerIdentity;
use App\Contexts\GameWorld\Players\Enums\PlayerIdentitySource;
use App\Contexts\GameWorld\Players\Models\Player;
use App\Contexts\GameWorld\Players\Models\PlayerIdentityHistory;
use App\Contexts\Platform\DataGovernance\Actions\ProcessAccountDeletionRequests;
use App\Contexts\Platform\DataGovernance\Models\AccountDeletionRequest;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Support\ScenarioFactory;
use Tests\TestCase;

final class OwnedPlayerMutationConcurrencyV3Test extends TestCase
{
    use DatabaseTruncation;

    /** @return iterable<string,array{bool,string,bool}> */
    public static function ownershipChanges(): iterable
    {
        foreach ([true, false] as $move) {
            foreach (['release', 'deletion', 'reassignment'] as $change) {
                yield ($move ? 'move' : 'rename').' before '.$change => [$move, $change, true];
                yield $change.' before '.($move ? 'move' : 'rename') => [$move, $change, false];
            }
        }
    }

    #[DataProvider('ownershipChanges')]
    public function test_owned_mutations_serialize_with_ownership_revocation_in_both_orders(bool $move, string $change, bool $mutationFirst): void
    {
        $factory = app(ScenarioFactory::class);
        $account = $factory->account();
        $other = $factory->account();
        $player = $factory->player($account->userId, 59264);
        $destination = $factory->kingdom(59265);
        if ($change === 'deletion') {
            AccountDeletionRequest::query()->create([
                'user_id' => $account->userId, 'status' => 'pending',
                'requested_at' => now()->subDay(), 'eligible_at' => now()->subMinute(),
            ]);
        }
        $mutate = static fn () => $move
            ? app(MoveOwnedPlayerToKingdom::class)->handle($account->userId, $player->playerId, $destination->kingdomId)
            : app(UpdateOwnedPlayerIdentity::class)->handle($account->userId, $player->playerId, 'Current Name', $player->gamePlayerId);
        $revoke = static function () use ($change, $account, $other, $player): void {
            if ($change === 'deletion') {
                self::assertSame(1, app(ProcessAccountDeletionRequests::class)->handle());

                return;
            }
            DB::transaction(static function () use ($change, $account, $other, $player): void {
                app(ReleasePlayerAccount::class)->handle($account->userId, $player->playerId);
                if ($change === 'reassignment') {
                    app(ClaimPlayerAccount::class)->handle($player->playerId, $other->userId);
                }
            });
        };
        $primary = $this->competitor();
        $attempted = false;
        DB::listen(static function (QueryExecuted $query) use ($primary, $account, $mutationFirst, $mutate, $revoke, &$attempted): void {
            if ($attempted || $query->connectionName !== $primary || ! str_starts_with($query->sql, 'select * from "users"')
                || ! str_contains($query->sql, 'for update') || ! in_array((string) $account->userId, array_map('strval', $query->bindings), true)) {
                return;
            }
            $attempted = true;
            DB::setDefaultConnection('owned_mutation');
            try {
                try {
                    $mutationFirst ? $revoke() : $mutate();
                    self::fail('The competing operation must wait for the account owner.');
                } catch (QueryException $exception) {
                    self::assertSame('55P03', $exception->errorInfo[0] ?? null);
                }
            } finally {
                DB::setDefaultConnection($primary);
            }
        });
        try {
            $mutationFirst ? $mutate() : $revoke();
            self::assertTrue($attempted);
            DB::setDefaultConnection('owned_mutation');
            if ($mutationFirst) {
                $revoke();
            } else {
                $before = $this->state();
                try {
                    $mutate();
                    self::fail('A former owner cannot mutate the durable Player identity.');
                } catch (ModelNotFoundException|ValidationException $exception) {
                    self::assertInstanceOf($change === 'deletion' ? ValidationException::class : ModelNotFoundException::class, $exception);
                }
                self::assertSame($before, $this->state());
            }
            $current = Player::query()->findOrFail($player->playerId);
            self::assertSame($change === 'reassignment' ? $other->userId : null, $current->user_id);
            self::assertSame($mutationFirst && $move ? $destination->kingdomId : $player->kingdomId, $current->current_kingdom_id);
            self::assertSame($mutationFirst && ! $move ? 'Current Name' : $player->currentName, $current->current_name);
            self::assertSame(1, PlayerIdentityHistory::query()->where('player_id', $player->playerId)->whereNull('valid_to')->count());
        } finally {
            DB::setDefaultConnection($primary);
            DB::purge('owned_mutation');
        }
    }

    public function test_movement_uses_current_name_and_stable_id_after_concurrent_system_observation(): void
    {
        $factory = app(ScenarioFactory::class);
        $account = $factory->account();
        $kingdom = $factory->kingdom(59264);
        $unclaimed = app(PersistPlayerIdentity::class)->handle($kingdom->kingdomId, 'Initial Name', null);
        $player = app(ClaimPlayerAccount::class)->handle($unclaimed->playerId, $account->userId);
        $destination = $factory->kingdom(59265);
        $primary = $this->competitor();
        $observed = false;
        DB::listen(static function (QueryExecuted $query) use ($primary, $player, &$observed): void {
            if ($observed || $query->connectionName !== $primary || ! str_starts_with($query->sql, 'select * from "kingdoms"') || ! str_contains($query->sql, 'for share')) {
                return;
            }
            $observed = true;
            DB::setDefaultConnection('owned_mutation');
            try {
                app(PersistPlayerIdentity::class)->handle($player->kingdomId, 'Observed Name', 'observed-stable-id', $player->playerId, PlayerIdentitySource::Import);
            } finally {
                DB::setDefaultConnection($primary);
            }
        });
        try {
            $current = app(MoveOwnedPlayerToKingdom::class)->handle($account->userId, $player->playerId, $destination->kingdomId);
            self::assertTrue($observed);
            self::assertSame('Observed Name', $current->currentName);
            self::assertSame('observed-stable-id', $current->gamePlayerId);
            self::assertSame($destination->kingdomId, $current->kingdomId);
            $history = PlayerIdentityHistory::query()->where('player_id', $player->playerId)->whereNull('valid_to')->sole();
            self::assertSame('Observed Name', $history->name);
            self::assertSame('observed-stable-id', $history->game_player_id);
        } finally {
            DB::setDefaultConnection($primary);
            DB::purge('owned_mutation');
        }
    }

    public function test_rename_rejects_a_changed_kingdom_instead_of_moving_the_player_back(): void
    {
        $factory = app(ScenarioFactory::class);
        $account = $factory->account();
        $player = $factory->player($account->userId, 59264);
        $destination = $factory->kingdom(59265);
        $primary = $this->competitor();
        $moved = false;
        DB::listen(static function (QueryExecuted $query) use ($primary, $player, $destination, &$moved): void {
            if ($moved || $query->connectionName !== $primary || ! str_starts_with($query->sql, 'select * from "kingdoms"') || ! str_contains($query->sql, 'for share')) {
                return;
            }
            $moved = true;
            DB::setDefaultConnection('owned_mutation');
            try {
                app(PersistPlayerIdentity::class)->handle($destination->kingdomId, $player->currentName, $player->gamePlayerId, $player->playerId, PlayerIdentitySource::Import);
            } finally {
                DB::setDefaultConnection($primary);
            }
        });
        try {
            try {
                app(UpdateOwnedPlayerIdentity::class)->handle($account->userId, $player->playerId, 'Stale Rename', $player->gamePlayerId);
                self::fail('An identity edit must not revert a concurrent Kingdom move.');
            } catch (ValidationException $exception) {
                self::assertArrayHasKey('kingdom', $exception->errors());
            }
            self::assertTrue($moved);
            $current = Player::query()->findOrFail($player->playerId);
            self::assertSame($destination->kingdomId, $current->current_kingdom_id);
            self::assertSame($player->currentName, $current->current_name);
        } finally {
            DB::setDefaultConnection($primary);
            DB::purge('owned_mutation');
        }
    }

    /** @return iterable<string,array{bool}> */
    public static function mutations(): iterable
    {
        yield 'move' => [true];
        yield 'rename' => [false];
    }

    #[DataProvider('mutations')]
    public function test_late_audit_failure_rolls_back_identity_and_all_history(bool $move): void
    {
        $factory = app(ScenarioFactory::class);
        $account = $factory->account();
        $player = $factory->player($account->userId, 59264);
        $destination = $factory->kingdom(59265);
        $before = $this->state();
        $failed = false;
        DB::listen(static function (QueryExecuted $query) use ($move, &$failed): void {
            if (! $failed && str_starts_with($query->sql, 'insert into "audit_events"') && in_array($move ? 'player.kingdom_changed' : 'player.name_changed', $query->bindings, true)) {
                $failed = true;
                throw new RuntimeException('Injected owned identity audit failure.');
            }
        });
        try {
            $move
                ? app(MoveOwnedPlayerToKingdom::class)->handle($account->userId, $player->playerId, $destination->kingdomId)
                : app(UpdateOwnedPlayerIdentity::class)->handle($account->userId, $player->playerId, 'Rolled Back Name', $player->gamePlayerId);
            self::fail('The whole identity transition must roll back.');
        } catch (RuntimeException $exception) {
            self::assertSame('Injected owned identity audit failure.', $exception->getMessage());
        }
        self::assertTrue($failed);
        self::assertSame($before, $this->state());
    }

    private function competitor(): string
    {
        $primary = DB::getDefaultConnection();
        config()->set('database.connections.owned_mutation', array_replace(DB::connection()->getConfig(), ['name' => 'owned_mutation']));
        DB::connection('owned_mutation')->statement("SET lock_timeout = '100ms'");

        return $primary;
    }

    /** @return array<string,string> */
    private function state(): array
    {
        return [
            'players' => DB::table('players')->orderBy('id')->get()->toJson(),
            'history' => DB::table('player_identity_history')->orderBy('id')->get()->toJson(),
            'audit' => DB::table('audit_events')->orderBy('id')->get()->toJson(),
        ];
    }
}
