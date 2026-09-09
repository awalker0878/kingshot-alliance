<?php

declare(strict_types=1);

namespace Tests\Integration\Concurrency\Contexts\GameWorld\KingdomTransfers;

use App\Contexts\Alliance\Lifecycle\ValueObjects\AllianceReference;
use App\Contexts\GameWorld\Kingdoms\Actions\ArchiveKingdom;
use App\Contexts\GameWorld\KingdomTransfers\Actions\ResolveTransferPlayer;
use App\Contexts\GameWorld\KingdomTransfers\Actions\SaveTransferParticipant;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferDirection;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferPlanState;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferSourceType;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferParticipant;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferPlan;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferWindow;
use App\Contexts\GameWorld\Players\Actions\PersistPlayerIdentity;
use App\Contexts\GameWorld\Players\Actions\ReconcilePlayers;
use App\Contexts\GameWorld\Players\Models\Player;
use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Support\ScenarioFactory;
use Tests\TestCase;

final class TransferPlanningIdentityConcurrencyV3Test extends TestCase
{
    use DatabaseMigrations;

    /** @return iterable<string,array{bool,bool}> */
    public static function archivalOrders(): iterable
    {
        foreach ([false, true] as $existing) {
            yield ($existing ? 'edit ' : 'new ').'planning first' => [$existing, true];
            yield ($existing ? 'edit ' : 'new ').'archival first' => [$existing, false];
        }
    }

    #[DataProvider('archivalOrders')]
    public function test_planning_stabilizes_source_kingdom_before_player_in_both_orders(bool $existing, bool $planningFirst): void
    {
        [$owner, $alliance, $plan, $target] = $this->fixture();
        $participantId = null;
        if ($existing) {
            $this->save($owner, $alliance, $plan, $target, null, 'Earlier observation');
            $participantId = (string) TransferParticipant::query()->sole()->id;
        }
        $save = fn () => $this->save($owner, $alliance, $plan, $target, $participantId, 'Current observation');
        $archive = static fn () => app(ArchiveKingdom::class)->handle($target->kingdomId);
        $primary = $this->competitor();
        $attempted = false;
        $sourceLocked = false;
        $contenderPlayerLocked = false;
        DB::listen(static function (QueryExecuted $query) use ($primary, $target, $planningFirst, $save, $archive, &$attempted, &$sourceLocked, &$contenderPlayerLocked): void {
            if ($query->connectionName === $primary && str_starts_with($query->sql, 'select * from "kingdoms"') && str_contains($query->sql, 'for share') && in_array($target->kingdomId, $query->bindings, true)) {
                $sourceLocked = true;
            }
            if ($query->connectionName === 'planning_writer' && str_starts_with($query->sql, 'select * from "players"') && str_contains($query->sql, 'for update')) {
                $contenderPlayerLocked = true;
            }
            $barrier = $planningFirst
                ? str_starts_with($query->sql, 'select * from "players"') && str_contains($query->sql, 'for update')
                : str_starts_with($query->sql, 'select * from "kingdoms"') && str_contains($query->sql, 'for update') && in_array($target->kingdomId, $query->bindings, true);
            if ($attempted || $query->connectionName !== $primary || ! $barrier) {
                return;
            }
            $attempted = true;
            if ($planningFirst) {
                self::assertTrue($sourceLocked);
            }
            DB::setDefaultConnection('planning_writer');
            try {
                try {
                    $planningFirst ? $archive() : $save();
                    self::fail('Planning and source archival must serialize before Player mutation.');
                } catch (QueryException $exception) {
                    self::assertSame('55P03', $exception->errorInfo[0] ?? null);
                }
                self::assertFalse($contenderPlayerLocked);
            } finally {
                DB::setDefaultConnection($primary);
            }
        });
        try {
            $planningFirst ? $save() : $archive();
            self::assertTrue($attempted);
            if ($planningFirst) {
                $archive();
                self::assertSame('Current observation', Player::query()->findOrFail($target->playerId)->current_name);
                self::assertSame(1, TransferParticipant::query()->count());
            }
            $beforeRetry = $this->state();
            try {
                $save();
                self::fail('Archived source must reject later planning without partial identity changes.');
            } catch (ValidationException $exception) {
                self::assertArrayHasKey('source_kingdom', $exception->errors());
            }
            self::assertSame($beforeRetry, $this->state());
        } finally {
            DB::setDefaultConnection($primary);
            DB::purge('planning_writer');
        }
    }

    /** @return iterable<string,array{bool}> */
    public static function orders(): iterable
    {
        yield 'first identity initiates' => [false];
        yield 'second identity initiates' => [true];
    }

    #[DataProvider('orders')]
    public function test_opposing_stable_identifier_edits_reject_without_locking_foreign_identity(bool $reverse): void
    {
        $factory = app(ScenarioFactory::class);
        $first = $factory->unclaimedPlayer(59281);
        $second = $factory->unclaimedPlayer(59281);
        [$target, $other] = $reverse ? [$second, $first] : [$first, $second];
        $primary = $this->competitor();
        $attempted = false;
        $before = $this->state();
        DB::listen(static function (QueryExecuted $query) use ($primary, $target, $other, &$attempted): void {
            if ($attempted || $query->connectionName !== $primary || ! str_starts_with($query->sql, 'select * from "players"') || ! str_contains($query->sql, 'for update') || ! in_array($target->playerId, $query->bindings, true)) {
                return;
            }
            $attempted = true;
            DB::setDefaultConnection('planning_writer');
            try {
                try {
                    app(ResolveTransferPlayer::class)->handle($other->kingdomId, 'Rejected observation', $target->gamePlayerId, $other->playerId);
                    self::fail('Opposing identity replacement must reject current stable identifiers.');
                } catch (ValidationException $exception) {
                    self::assertArrayHasKey('game_player_id', $exception->errors());
                }
            } finally {
                DB::setDefaultConnection($primary);
            }
        });
        try {
            try {
                app(ResolveTransferPlayer::class)->handle($target->kingdomId, 'Rejected observation', $other->gamePlayerId, $target->playerId);
                self::fail('Identity replacement must reject without waiting for an unrelated Player.');
            } catch (ValidationException $exception) {
                self::assertArrayHasKey('game_player_id', $exception->errors());
            }
            self::assertTrue($attempted);
            self::assertSame($before, $this->state());
        } finally {
            DB::setDefaultConnection($primary);
            DB::purge('planning_writer');
        }
    }

    #[DataProvider('orders')]
    public function test_planning_and_identity_movement_use_current_placement_after_either_commit(bool $movementFirst): void
    {
        $factory = app(ScenarioFactory::class);
        $target = $factory->unclaimedPlayer(59281);
        $destination = $factory->kingdom(59282);
        $plan = static fn () => app(ResolveTransferPlayer::class)->handle($target->kingdomId, 'Planned source observation', $target->gamePlayerId, $target->playerId);
        $move = static fn () => app(PersistPlayerIdentity::class)->handle($destination->kingdomId, 'Later movement', $target->gamePlayerId, $target->playerId);
        $primary = $this->competitor();
        $attempted = false;
        DB::listen(static function (QueryExecuted $query) use ($primary, $target, $movementFirst, $plan, $move, &$attempted): void {
            if ($attempted || $query->connectionName !== $primary || ! str_starts_with($query->sql, 'select * from "players"') || ! str_contains($query->sql, 'for update') || ! in_array($target->playerId, $query->bindings, true)) {
                return;
            }
            $attempted = true;
            DB::setDefaultConnection('planning_writer');
            try {
                try {
                    $movementFirst ? $plan() : $move();
                    self::fail('Identity movement and source observation must serialize.');
                } catch (QueryException $exception) {
                    self::assertSame('55P03', $exception->errorInfo[0] ?? null);
                }
            } finally {
                DB::setDefaultConnection($primary);
            }
        });
        try {
            $movementFirst ? $move() : $plan();
            self::assertTrue($attempted);
            $movementFirst ? $plan() : $move();
            self::assertSame($movementFirst ? $target->kingdomId : $destination->kingdomId, Player::query()->findOrFail($target->playerId)->current_kingdom_id);
            self::assertSame(1, DB::table('player_identity_history')->where('player_id', $target->playerId)->whereNull('valid_to')->count());
        } finally {
            DB::setDefaultConnection($primary);
            DB::purge('planning_writer');
        }
    }

    public function test_reconciled_participant_identity_is_rejected_without_mutating_its_alias(): void
    {
        [$owner, $alliance, $plan, $target] = $this->fixture();
        $this->save($owner, $alliance, $plan, $target, null, 'Earlier observation');
        $participantId = (string) TransferParticipant::query()->sole()->id;
        $canonical = app(PersistPlayerIdentity::class)->handle($target->kingdomId, 'Canonical Governor', null);
        app(ReconcilePlayers::class)->handle($canonical->playerId, $target->playerId, 'Verified planning identity consolidation.');
        $before = $this->state();
        try {
            $this->save($owner, $alliance, $plan, $target, $participantId, 'Rejected alias observation');
            self::fail('A captured participant cannot silently edit its reconciled alias.');
        } catch (ModelNotFoundException) {
            self::assertSame($before, $this->state());
        }
    }

    #[DataProvider('orders')]
    public function test_late_planning_delivery_failure_rolls_back_identity_history_and_participant(bool $existing): void
    {
        [$owner, $alliance, $plan, $target] = $this->fixture();
        $participantId = null;
        if ($existing) {
            $this->save($owner, $alliance, $plan, $target, null, 'Earlier observation');
            $participantId = (string) TransferParticipant::query()->sole()->id;
        }
        $before = $this->state();
        $failed = false;
        DB::listen(static function (QueryExecuted $query) use (&$failed): void {
            if (! $failed && str_starts_with($query->sql, 'insert into "outbox_messages"') && (in_array('kingdoms.transfer_participant_created', $query->bindings, true) || in_array('kingdoms.transfer_participant_updated', $query->bindings, true))) {
                $failed = true;
                throw new RuntimeException('Injected planning delivery failure.');
            }
        });
        try {
            $this->save($owner, $alliance, $plan, $target, $participantId, 'Uncommitted observation');
            self::fail('Late delivery failure must roll back all composed owner effects.');
        } catch (RuntimeException $exception) {
            self::assertSame('Injected planning delivery failure.', $exception->getMessage());
        }
        self::assertTrue($failed);
        self::assertSame($before, $this->state());
    }

    /** @return array{PlayerReference,AllianceReference,TransferPlan,PlayerReference} */
    private function fixture(): array
    {
        $factory = app(ScenarioFactory::class);
        $owner = $factory->player($factory->account()->userId, 59280);
        $alliance = $factory->alliance($owner);
        $target = $factory->unclaimedPlayer(59281);
        $window = TransferWindow::query()->create([
            'alliance_id' => $alliance->allianceId, 'label' => 'Planning window',
            'pre_transfer_starts_at' => now()->subDays(3), 'invitational_starts_at' => now()->subDays(2),
            'transfer_opens_at' => now()->subDay(), 'ends_at' => now()->addDay(),
            'source_type' => TransferSourceType::InGame, 'source_reference' => 'Observed planning window', 'observed_at' => now()->subDays(4),
        ]);
        $plan = TransferPlan::query()->create(['alliance_id' => $alliance->allianceId, 'home_kingdom_id' => $owner->kingdomId, 'transfer_window_id' => $window->id, 'label' => 'Current planning', 'state' => TransferPlanState::Open]);

        return [$owner, $alliance, $plan, $target];
    }

    private function save(PlayerReference $owner, AllianceReference $alliance, TransferPlan $plan, PlayerReference $target, ?string $participantId, string $name): void
    {
        app(SaveTransferParticipant::class)->handle($alliance->allianceId, $owner->playerId, (string) $plan->id, [
            'direction' => TransferDirection::Incoming, 'name' => $name, 'game_player_id' => $target->gamePlayerId, 'source_kingdom' => 59281,
        ], $participantId);
    }

    private function competitor(): string
    {
        $primary = DB::getDefaultConnection();
        config()->set('database.connections.planning_writer', array_replace(DB::connection()->getConfig(), ['name' => 'planning_writer']));
        DB::connection('planning_writer')->statement("SET lock_timeout = '100ms'");

        return $primary;
    }

    /** @return array<string,string> */
    private function state(): array
    {
        $state = [];
        foreach (['players', 'player_identity_history', 'player_reconciliations', 'transfer_participants', 'audit_events', 'outbox_messages'] as $table) {
            $state[$table] = DB::table($table)->orderBy('id')->get()->toJson();
        }

        return $state;
    }
}
