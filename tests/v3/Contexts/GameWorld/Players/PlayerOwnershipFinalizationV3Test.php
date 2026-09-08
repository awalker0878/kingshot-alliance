<?php

declare(strict_types=1);

namespace Tests\v3\Contexts\GameWorld\Players;

use App\Contexts\Accounts\Identity\Models\User;
use App\Contexts\GameWorld\Players\Actions\ClaimPlayerAccount;
use App\Contexts\GameWorld\Players\Actions\CreatePlayerForAccount;
use App\Contexts\GameWorld\Players\Actions\PersistPlayerIdentity;
use App\Contexts\GameWorld\Players\Actions\ReconcilePlayers;
use App\Contexts\GameWorld\Players\Models\Player;
use App\Contexts\GameWorld\Players\Models\PlayerIdentityHistory;
use App\Contexts\GameWorld\Players\Models\PlayerReconciliation;
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

final class PlayerOwnershipFinalizationV3Test extends TestCase
{
    use DatabaseMigrations;

    /** @return iterable<string,array{string}> */
    public static function assignments(): iterable
    {
        yield 'claim' => ['claim'];
        yield 'create' => ['create'];
        yield 'reconcile' => ['reconcile'];
    }

    #[DataProvider('assignments')]
    public function test_finalized_accounts_cannot_receive_new_or_reconciled_player_ownership(string $operation): void
    {
        $fixture = $this->fixture();
        User::query()->whereKey($fixture['userId'])->update(['anonymized_at' => now()]);
        $players = Player::query()->orderBy('id')->get()->toArray();
        $history = PlayerIdentityHistory::query()->orderBy('id')->get()->toArray();

        try {
            $this->assign($operation, $fixture);
            self::fail('Finalized accounts must not receive Player ownership.');
        } catch (ValidationException $exception) {
            self::assertArrayHasKey('account', $exception->errors());
        }

        self::assertSame($players, Player::query()->orderBy('id')->get()->toArray());
        self::assertSame($history, PlayerIdentityHistory::query()->orderBy('id')->get()->toArray());
        self::assertSame(0, PlayerReconciliation::query()->count());
    }

    public function test_finalization_holds_off_a_competing_claim_and_rejects_it_after_commit(): void
    {
        $fixture = $this->fixture();
        $this->dueDeletion($fixture['userId']);
        $primary = $this->competitor();
        $attempted = false;
        $blocked = false;
        DB::listen(static function (QueryExecuted $query) use ($fixture, $primary, &$attempted, &$blocked): void {
            if ($attempted || $query->connectionName !== $primary || ! str_contains($query->sql, 'from "users"') || ! str_contains($query->sql, 'for update')) {
                return;
            }
            $attempted = true;
            DB::setDefaultConnection('ownership_competitor');
            try {
                app(ClaimPlayerAccount::class)->handle($fixture['canonicalId'], $fixture['userId']);
            } catch (QueryException $exception) {
                self::assertSame('55P03', $exception->errorInfo[0]);
                $blocked = true;
            } finally {
                DB::setDefaultConnection($primary);
            }
        });

        try {
            self::assertSame(1, app(ProcessAccountDeletionRequests::class)->handle());
            self::assertTrue($attempted);
            self::assertTrue($blocked);
            try {
                $this->assign('claim', $fixture);
                self::fail('A claim waiting behind finalization cannot reopen ownership.');
            } catch (ValidationException $exception) {
                self::assertArrayHasKey('account', $exception->errors());
            }
            self::assertSame(0, Player::query()->where('user_id', $fixture['userId'])->count());
        } finally {
            DB::purge('ownership_competitor');
        }
    }

    #[DataProvider('assignments')]
    public function test_finalization_waits_for_assignments_then_releases_the_complete_current_set(string $operation): void
    {
        $fixture = $this->fixture();
        $this->dueDeletion($fixture['userId']);
        $primary = $this->competitor();
        $attempted = false;
        $blocked = false;
        DB::listen(static function (QueryExecuted $query) use ($primary, &$attempted, &$blocked): void {
            if ($attempted || $query->connectionName !== $primary || ! str_contains($query->sql, 'from "users"') || ! str_contains($query->sql, 'for update')) {
                return;
            }
            $attempted = true;
            DB::setDefaultConnection('ownership_competitor');
            try {
                app(ProcessAccountDeletionRequests::class)->handle();
            } catch (QueryException $exception) {
                self::assertSame('55P03', $exception->errorInfo[0]);
                $blocked = true;
            } finally {
                DB::setDefaultConnection($primary);
            }
        });

        try {
            $this->assign($operation, $fixture);
            self::assertTrue($attempted);
            self::assertTrue($blocked);
            $owned = Player::query()->where('user_id', $fixture['userId'])->pluck('id')->all();
            self::assertNotEmpty($owned);

            self::assertSame(1, app(ProcessAccountDeletionRequests::class)->handle());
            self::assertSame(0, Player::query()->where('user_id', $fixture['userId'])->count());
            foreach ($owned as $id) {
                $history = PlayerIdentityHistory::query()->where('player_id', $id)->whereNull('valid_to')->firstOrFail();
                self::assertNull($history->user_id);
                self::assertSame('data_governance', $history->source_type->value);
            }
        } finally {
            DB::purge('ownership_competitor');
        }
    }

    public function test_reconciliation_rejects_an_owner_changed_after_its_routing_snapshot(): void
    {
        $fixture = $this->fixture();
        $other = User::factory()->create();
        $primary = $this->competitor();
        $changed = false;
        DB::listen(static function (QueryExecuted $query) use ($fixture, $other, $primary, &$changed): void {
            if ($changed || $query->connectionName !== $primary || ! str_starts_with($query->sql, 'select "id", "user_id" from "players"')) {
                return;
            }
            $changed = true;
            DB::setDefaultConnection('ownership_competitor');
            try {
                app(ClaimPlayerAccount::class)->handle($fixture['canonicalId'], (int) $other->id);
            } finally {
                DB::setDefaultConnection($primary);
            }
        });

        try {
            try {
                $this->assign('reconcile', $fixture);
                self::fail('Reconciliation must reject a changed ownership snapshot.');
            } catch (ValidationException $exception) {
                self::assertStringContainsString('ownership changed', $exception->errors()['player'][0]);
            }
            self::assertTrue($changed);
            self::assertSame((int) $other->id, (int) Player::query()->findOrFail($fixture['canonicalId'])->user_id);
            self::assertSame($fixture['userId'], (int) Player::query()->findOrFail($fixture['duplicateId'])->user_id);
            self::assertNull(Player::query()->findOrFail($fixture['duplicateId'])->canonical_player_id);
            self::assertSame(0, PlayerReconciliation::query()->count());
        } finally {
            DB::purge('ownership_competitor');
        }
    }

    /** @param array{userId:int,kingdomId:string,canonicalId:string,duplicateId:string} $fixture */
    private function assign(string $operation, array $fixture): void
    {
        match ($operation) {
            'claim' => app(ClaimPlayerAccount::class)->handle($fixture['canonicalId'], $fixture['userId']),
            'create' => app(CreatePlayerForAccount::class)->handle($fixture['userId'], $fixture['kingdomId'], 'New Governor', 'new-ownership-test'),
            'reconcile' => app(ReconcilePlayers::class)->handle($fixture['canonicalId'], $fixture['duplicateId'], 'Explicit ownership reconciliation.'),
        };
    }

    /** @return array{userId:int,kingdomId:string,canonicalId:string,duplicateId:string} */
    private function fixture(): array
    {
        $user = User::factory()->create();
        $kingdom = (new ScenarioFactory)->kingdom(80901);
        $persist = app(PersistPlayerIdentity::class);
        $canonical = $persist->handle($kingdom->kingdomId, 'Canonical Governor', null);
        $duplicate = $persist->handle($kingdom->kingdomId, 'Duplicate Governor', 'owned-80901');
        app(ClaimPlayerAccount::class)->handle($duplicate->playerId, (int) $user->id);

        return ['userId' => (int) $user->id, 'kingdomId' => $kingdom->kingdomId, 'canonicalId' => $canonical->playerId, 'duplicateId' => $duplicate->playerId];
    }

    private function dueDeletion(int $userId): void
    {
        AccountDeletionRequest::query()->create([
            'user_id' => $userId,
            'status' => 'pending',
            'requested_at' => now()->subDays(8),
            'eligible_at' => now()->subDay(),
        ]);
    }

    private function competitor(): string
    {
        $primary = DB::getDefaultConnection();
        config()->set('database.connections.ownership_competitor', config('database.connections.'.$primary));
        DB::connection('ownership_competitor')->statement("SET lock_timeout = '100ms'");

        return $primary;
    }
}
