<?php

declare(strict_types=1);

namespace Tests\Contexts\GameWorld\KingdomTransfers\Integration\Concurrency;

use App\Contexts\Alliance\Lifecycle\ValueObjects\AllianceReference;
use App\Contexts\Alliance\Membership\Actions\ActivateRosterEntryForTransfer;
use App\Contexts\Alliance\Membership\Enums\AllianceRank;
use App\Contexts\Alliance\Membership\Enums\MembershipStatus;
use App\Contexts\Alliance\Membership\Models\AllianceMembership;
use App\Contexts\GameWorld\Kingdoms\Actions\ArchiveKingdom;
use App\Contexts\GameWorld\KingdomTransfers\Actions\CompleteTransferParticipant;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferDirection;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferPlanState;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferReadinessState;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferSourceType;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferCompletion;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferParticipant;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferPlan;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferWindow;
use App\Contexts\GameWorld\Players\Actions\PersistPlayerIdentity;
use App\Contexts\GameWorld\Players\Enums\PlayerIdentitySource;
use App\Contexts\GameWorld\Players\Models\Player;
use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Support\ScenarioFactory;
use Tests\TestCase;

final class TransferCompletionLockOrderV3Test extends TestCase
{
    use DatabaseTruncation;

    /** @return iterable<string,array{TransferDirection,bool}> */
    public static function archivalOrders(): iterable
    {
        foreach (TransferDirection::cases() as $direction) {
            yield $direction->value.' completion first' => [$direction, true];
            yield $direction->value.' archival first' => [$direction, false];
        }
    }

    #[DataProvider('archivalOrders')]
    public function test_completion_locks_required_kingdoms_before_player_in_both_archival_orders(TransferDirection $direction, bool $completionFirst): void
    {
        [$owner, $alliance, $target, $participant] = $this->fixture($direction);
        $kingdomId = $direction === TransferDirection::Outgoing ? (string) $participant->destination_kingdom_id : $owner->kingdomId;
        $complete = static fn () => app(CompleteTransferParticipant::class)->handle($alliance->allianceId, $owner->playerId, (string) $participant->transfer_plan_id, (string) $participant->id);
        $archive = static fn () => app(ArchiveKingdom::class)->handle($kingdomId);
        $primary = $this->competitor();
        $attempted = false;
        $kingdomLocks = [];
        $playerLocks = [];
        DB::listen(static function (QueryExecuted $query) use ($primary, $target, $owner, $kingdomId, $direction, $completionFirst, $complete, $archive, &$attempted, &$kingdomLocks, &$playerLocks): void {
            if (str_starts_with($query->sql, 'select * from "players"') && (str_contains($query->sql, 'for update') || str_contains($query->sql, 'for share'))) {
                $playerLocks[] = $query->connectionName;
            }
            if ($query->connectionName === $primary && str_starts_with($query->sql, 'select * from "kingdoms"') && str_contains($query->sql, 'for share')) {
                foreach ($query->bindings as $binding) {
                    if (in_array($binding, [$kingdomId, $owner->kingdomId], true)) {
                        $kingdomLocks[] = $binding;
                    }
                }
            }
            $barrier = $completionFirst
                ? str_starts_with($query->sql, 'select * from "players"') && (str_contains($query->sql, 'for update') || str_contains($query->sql, 'for share')) && in_array($target->playerId, $query->bindings, true)
                : str_starts_with($query->sql, 'select * from "kingdoms"') && str_contains($query->sql, 'for update') && in_array($kingdomId, $query->bindings, true);
            if ($attempted || $query->connectionName !== $primary || ! $barrier) {
                return;
            }
            $attempted = true;
            if ($completionFirst) {
                $expected = $direction === TransferDirection::Outgoing ? [$owner->kingdomId, $kingdomId] : [$kingdomId];
                sort($expected, SORT_STRING);
                self::assertSame($expected, $kingdomLocks);
            }
            DB::setDefaultConnection('completion_writer');
            try {
                try {
                    $completionFirst ? $archive() : $complete();
                    self::fail('The competing owner must wait on the Kingdom lifecycle barrier.');
                } catch (QueryException $exception) {
                    self::assertSame('55P03', $exception->errorInfo[0] ?? null);
                }
                self::assertNotContains('completion_writer', $playerLocks);
            } finally {
                DB::setDefaultConnection($primary);
            }
        });
        try {
            $completionFirst ? $complete() : $archive();
            self::assertTrue($attempted);
            DB::setDefaultConnection('completion_writer');
            if ($completionFirst) {
                $archive();
                self::assertSame(1, TransferCompletion::query()->where('transfer_participant_id', $participant->id)->count());
                self::assertSame($kingdomId, Player::query()->findOrFail($target->playerId)->current_kingdom_id);
                if ($direction === TransferDirection::Outgoing) {
                    $beforeRetry = $this->state();
                    $complete();
                    self::assertSame($beforeRetry, $this->state());
                }
            } else {
                $before = $this->state();
                try {
                    $complete();
                    self::fail('Current archived placement must reject new completion.');
                } catch (ValidationException|AuthorizationException) {
                    self::assertSame($before, $this->state());
                }
                self::assertSame(0, TransferCompletion::query()->count());
                self::assertSame($target->kingdomId, Player::query()->findOrFail($target->playerId)->current_kingdom_id);
            }
        } finally {
            DB::setDefaultConnection($primary);
            DB::purge('completion_writer');
        }
    }

    public function test_independent_alliance_completion_proceeds_in_the_same_kingdoms(): void
    {
        [$owner, $alliance, $target, $participant] = $this->fixture(TransferDirection::Outgoing);
        [$otherOwner, $otherAlliance, , $otherParticipant] = $this->fixture(TransferDirection::Outgoing);
        $primary = $this->competitor();
        $completed = false;
        DB::listen(static function (QueryExecuted $query) use ($primary, $target, $otherOwner, $otherAlliance, $otherParticipant, &$completed): void {
            if ($completed || $query->connectionName !== $primary || ! str_starts_with($query->sql, 'select * from "players"') || ! str_contains($query->sql, 'for update') || ! in_array($target->playerId, $query->bindings, true)) {
                return;
            }
            $completed = true;
            DB::setDefaultConnection('completion_writer');
            try {
                app(CompleteTransferParticipant::class)->handle($otherAlliance->allianceId, $otherOwner->playerId, (string) $otherParticipant->transfer_plan_id, (string) $otherParticipant->id);
            } finally {
                DB::setDefaultConnection($primary);
            }
        });
        try {
            app(CompleteTransferParticipant::class)->handle($alliance->allianceId, $owner->playerId, (string) $participant->transfer_plan_id, (string) $participant->id);
            self::assertTrue($completed);
            self::assertSame(2, TransferCompletion::query()->count());
        } finally {
            DB::setDefaultConnection($primary);
            DB::purge('completion_writer');
        }
    }

    public function test_changed_destination_routing_is_rejected_without_partial_handoff(): void
    {
        [$owner, $alliance, , $participant] = $this->fixture(TransferDirection::Outgoing);
        $replacement = app(ScenarioFactory::class)->kingdom(59279);
        $primary = $this->competitor();
        $changed = false;
        $before = $this->state();
        DB::listen(static function (QueryExecuted $query) use ($primary, $participant, $replacement, &$changed): void {
            if ($changed || $query->connectionName !== $primary || ! str_starts_with($query->sql, 'select "player_id", "direction", "destination_kingdom_id" from "transfer_participants"')) {
                return;
            }
            $changed = true;
            DB::connection('completion_writer')->table('transfer_participants')->where('id', $participant->id)->update(['destination_kingdom_id' => $replacement->kingdomId]);
        });
        try {
            try {
                app(CompleteTransferParticipant::class)->handle($alliance->allianceId, $owner->playerId, (string) $participant->transfer_plan_id, (string) $participant->id);
                self::fail('The acquired Kingdom scope must still match current participant routing.');
            } catch (ValidationException $exception) {
                self::assertArrayHasKey('completion', $exception->errors());
            }
            self::assertTrue($changed);
            self::assertSame($replacement->kingdomId, $participant->fresh()?->destination_kingdom_id);
            self::assertSame($before, $this->state());
        } finally {
            DB::setDefaultConnection($primary);
            DB::purge('completion_writer');
        }
    }

    /** @return iterable<string,array{bool}> */
    public static function opposingOrders(): iterable
    {
        yield 'first Alliance initiates' => [false];
        yield 'second Alliance initiates' => [true];
    }

    #[DataProvider('opposingOrders')]
    public function test_opposing_completions_reject_active_foreign_officers_without_actor_player_locks(bool $reverse): void
    {
        [$firstOwner, $firstAlliance, , $firstParticipant] = $this->fixture(TransferDirection::Incoming);
        [$secondOwner, $secondAlliance, , $secondParticipant] = $this->fixture(TransferDirection::Incoming);
        $firstParticipant->forceFill(['player_id' => $secondOwner->playerId, 'source_kingdom_id' => $secondOwner->kingdomId])->save();
        $secondParticipant->forceFill(['player_id' => $firstOwner->playerId, 'source_kingdom_id' => $firstOwner->kingdomId])->save();
        [$owner, $alliance, $participant, $otherOwner, $otherAlliance, $otherParticipant] = $reverse
            ? [$secondOwner, $secondAlliance, $secondParticipant, $firstOwner, $firstAlliance, $firstParticipant]
            : [$firstOwner, $firstAlliance, $firstParticipant, $secondOwner, $secondAlliance, $secondParticipant];
        $primary = $this->competitor();
        $attempted = false;
        $before = $this->state();
        DB::listen(static function (QueryExecuted $query) use ($primary, $owner, $otherOwner, $otherAlliance, $otherParticipant, &$attempted): void {
            if ($attempted || $query->connectionName !== $primary || ! str_starts_with($query->sql, 'select * from "players"') || ! in_array($owner->playerId, $query->bindings, true)) {
                return;
            }
            $attempted = true;
            DB::setDefaultConnection('completion_writer');
            try {
                try {
                    app(CompleteTransferParticipant::class)->handle($otherAlliance->allianceId, $otherOwner->playerId, (string) $otherParticipant->transfer_plan_id, (string) $otherParticipant->id);
                    self::fail('An active foreign officer cannot move through transfer completion.');
                } catch (ValidationException $exception) {
                    self::assertArrayHasKey('completion', $exception->errors());
                }
            } finally {
                DB::setDefaultConnection($primary);
            }
        });
        try {
            try {
                app(CompleteTransferParticipant::class)->handle($alliance->allianceId, $owner->playerId, (string) $participant->transfer_plan_id, (string) $participant->id);
                self::fail('Both current foreign memberships must reject completion.');
            } catch (ValidationException $exception) {
                self::assertArrayHasKey('completion', $exception->errors());
            }
            self::assertTrue($attempted);
            self::assertSame($before, $this->state());
        } finally {
            DB::setDefaultConnection($primary);
            DB::purge('completion_writer');
        }
    }

    #[DataProvider('opposingOrders')]
    public function test_staying_completions_preserve_player_actor_references_across_opposing_alliances(bool $reverse): void
    {
        $factory = app(ScenarioFactory::class);
        [$firstOwner, $firstAlliance, , $firstParticipant] = $this->fixture(TransferDirection::Staying);
        [$secondOwner, $secondAlliance, , $secondParticipant] = $this->fixture(TransferDirection::Staying);
        $firstEntry = $factory->roster($firstOwner, $firstAlliance, $secondOwner);
        $secondEntry = $factory->roster($secondOwner, $secondAlliance, $firstOwner);
        $firstParticipant->forceFill(['player_id' => $secondOwner->playerId, 'roster_entry_id' => $firstEntry->rosterEntryId, 'observed_name' => $secondOwner->currentName, 'game_player_id' => $secondOwner->gamePlayerId])->save();
        $secondParticipant->forceFill(['player_id' => $firstOwner->playerId, 'roster_entry_id' => $secondEntry->rosterEntryId, 'observed_name' => $firstOwner->currentName, 'game_player_id' => $firstOwner->gamePlayerId])->save();
        [$owner, $alliance, $participant, $otherOwner, $otherAlliance, $otherParticipant] = $reverse
            ? [$secondOwner, $secondAlliance, $secondParticipant, $firstOwner, $firstAlliance, $firstParticipant]
            : [$firstOwner, $firstAlliance, $firstParticipant, $secondOwner, $secondAlliance, $secondParticipant];
        $primary = $this->competitor();
        $attempted = false;
        $playersBefore = DB::table('players')->orderBy('id')->get()->toJson();
        $historyBefore = DB::table('player_identity_history')->orderBy('id')->get()->toJson();
        $complete = static fn () => app(CompleteTransferParticipant::class)->handle($alliance->allianceId, $owner->playerId, (string) $participant->transfer_plan_id, (string) $participant->id);
        $otherComplete = static fn () => app(CompleteTransferParticipant::class)->handle($otherAlliance->allianceId, $otherOwner->playerId, (string) $otherParticipant->transfer_plan_id, (string) $otherParticipant->id);
        DB::listen(static function (QueryExecuted $query) use ($primary, $otherOwner, $otherComplete, &$attempted): void {
            if ($attempted || $query->connectionName !== $primary || ! str_starts_with($query->sql, 'select * from "players"') || ! str_contains($query->sql, 'for ') || ! in_array($otherOwner->playerId, $query->bindings, true)) {
                return;
            }
            $attempted = true;
            DB::setDefaultConnection('completion_writer');
            try {
                // The competing completion references its actor, who is the
                // first completion's target. Staying does not mutate identity.
                $otherComplete();
            } finally {
                DB::setDefaultConnection($primary);
            }
        });
        try {
            $complete();
            self::assertTrue($attempted);
            self::assertSame(2, TransferCompletion::query()->count());
            foreach ([$owner, $otherOwner] as $actor) {
                self::assertSame(1, DB::table('audit_events')->where('event', 'kingdoms.transfer_participant_completed')->where('actor_player_id', $actor->playerId)->whereNull('actor_user_id')->count());
                self::assertSame(1, TransferCompletion::query()->where('completed_by_player_id', $actor->playerId)->count());
            }
            self::assertSame($playersBefore, DB::table('players')->orderBy('id')->get()->toJson());
            self::assertSame($historyBefore, DB::table('player_identity_history')->orderBy('id')->get()->toJson());
            $beforeRetry = $this->state();
            $complete();
            $otherComplete();
            self::assertSame($beforeRetry, $this->state());
        } finally {
            DB::setDefaultConnection($primary);
            DB::purge('completion_writer');
        }
    }

    /** @return iterable<string,array{TransferDirection}> */
    public static function directions(): iterable
    {
        foreach (TransferDirection::cases() as $direction) {
            yield $direction->value => [$direction];
        }
    }

    #[DataProvider('directions')]
    public function test_late_completion_delivery_failure_rolls_back_all_owner_effects(TransferDirection $direction): void
    {
        [$owner, $alliance, , $participant] = $this->fixture($direction);
        $before = $this->state();
        $failed = false;
        DB::listen(static function (QueryExecuted $query) use (&$failed): void {
            if (! $failed && str_starts_with($query->sql, 'insert into "outbox_messages"') && in_array('kingdoms.transfer_participant_completed', $query->bindings, true)) {
                $failed = true;
                throw new RuntimeException('Injected transfer completion delivery failure.');
            }
        });
        try {
            app(CompleteTransferParticipant::class)->handle($alliance->allianceId, $owner->playerId, (string) $participant->transfer_plan_id, (string) $participant->id);
            self::fail('All composed transfer effects must roll back.');
        } catch (RuntimeException $exception) {
            self::assertSame('Injected transfer completion delivery failure.', $exception->getMessage());
        }
        self::assertTrue($failed);
        self::assertSame($before, $this->state());
    }

    /** @return iterable<string,array{bool}> */
    public static function activationOrders(): iterable
    {
        yield 'activation first' => [true];
        yield 'movement first' => [false];
    }

    #[DataProvider('activationOrders')]
    public function test_transfer_roster_owner_stabilizes_player_without_relying_on_a_completion_caller(bool $activationFirst): void
    {
        $factory = app(ScenarioFactory::class);
        $owner = $factory->player($factory->account()->userId, 59277);
        $alliance = $factory->alliance($owner);
        $target = $factory->unclaimedPlayer(59277);
        $destination = $factory->kingdom(59278);
        $activate = static fn () => app(ActivateRosterEntryForTransfer::class)->handle($alliance->allianceId, $owner->playerId, $target->playerId, $target->currentName);
        $move = static fn () => app(PersistPlayerIdentity::class)->handle($destination->kingdomId, $target->currentName, $target->gamePlayerId, $target->playerId, PlayerIdentitySource::Import);
        $primary = $this->competitor();
        $attempted = false;
        DB::listen(static function (QueryExecuted $query) use ($primary, $target, $activationFirst, $activate, $move, &$attempted): void {
            if ($attempted || $query->connectionName !== $primary || ! str_starts_with($query->sql, 'select * from "players"') || ! str_contains($query->sql, 'for update') || ! in_array($target->playerId, $query->bindings, true)) {
                return;
            }
            $attempted = true;
            DB::setDefaultConnection('completion_writer');
            try {
                try {
                    $activationFirst ? $move() : $activate();
                    self::fail('Roster admission and identity movement must serialize.');
                } catch (QueryException $exception) {
                    self::assertSame('55P03', $exception->errorInfo[0] ?? null);
                }
            } finally {
                DB::setDefaultConnection($primary);
            }
        });
        try {
            $activationFirst ? $activate() : $move();
            self::assertTrue($attempted);
            $before = $this->state();
            try {
                $activationFirst ? $move() : $activate();
                self::fail('The later owner must use the committed roster or Kingdom facts.');
            } catch (ValidationException $exception) {
                self::assertArrayHasKey($activationFirst ? 'kingdom' : 'completion', $exception->errors());
            }
            self::assertSame($before, $this->state());
        } finally {
            DB::setDefaultConnection($primary);
            DB::purge('completion_writer');
        }
    }

    /** @return array{PlayerReference,AllianceReference,PlayerReference,TransferParticipant} */
    private function fixture(TransferDirection $direction): array
    {
        $factory = app(ScenarioFactory::class);
        // The destination ID precedes home to exercise sorting, not creation order.
        $remote = $factory->kingdom(59278);
        $owner = $factory->player($factory->account()->userId, 59277);
        $alliance = $factory->alliance($owner);
        $target = $factory->unclaimedPlayer($direction === TransferDirection::Incoming ? 59278 : 59277);
        $entry = $direction === TransferDirection::Incoming ? null : $factory->roster($owner, $alliance, $target);
        if ($direction === TransferDirection::Outgoing) {
            AllianceMembership::query()->create(['alliance_id' => $alliance->allianceId, 'player_id' => $target->playerId, 'rank' => AllianceRank::R1, 'status' => MembershipStatus::Active, 'joined_at' => now()]);
        }
        $window = TransferWindow::query()->create([
            'alliance_id' => $alliance->allianceId, 'label' => 'Current transfer',
            'pre_transfer_starts_at' => now()->subDays(3), 'invitational_starts_at' => now()->subDays(2),
            'transfer_opens_at' => now()->subDay(), 'ends_at' => now()->addDay(),
            'source_type' => TransferSourceType::InGame, 'source_reference' => 'Observed transfer window', 'observed_at' => now()->subDays(4),
        ]);
        $plan = TransferPlan::query()->create(['alliance_id' => $alliance->allianceId, 'home_kingdom_id' => $owner->kingdomId, 'transfer_window_id' => $window->id, 'label' => 'Confirmed transfer', 'state' => TransferPlanState::Locked]);
        $participant = TransferParticipant::query()->create([
            'alliance_id' => $alliance->allianceId, 'transfer_plan_id' => $plan->id,
            'direction' => $direction, 'readiness_state' => TransferReadinessState::Confirmed,
            'player_id' => $target->playerId, 'observed_name' => $target->currentName, 'game_player_id' => $target->gamePlayerId,
            'source_kingdom_id' => $target->kingdomId,
            'destination_kingdom_id' => $direction === TransferDirection::Outgoing ? $remote->kingdomId : ($direction === TransferDirection::Incoming ? $owner->kingdomId : null),
            'roster_entry_id' => $entry?->rosterEntryId,
        ]);

        return [$owner, $alliance, $target, $participant];
    }

    private function competitor(): string
    {
        $primary = DB::getDefaultConnection();
        config()->set('database.connections.completion_writer', array_replace(DB::connection()->getConfig(), ['name' => 'completion_writer']));
        DB::connection('completion_writer')->statement("SET lock_timeout = '100ms'");

        return $primary;
    }

    /** @return array<string,string> */
    private function state(): array
    {
        $state = [];
        foreach (['players', 'player_identity_history', 'alliance_memberships', 'alliance_roster_entries', 'transfer_completions', 'transfer_capacity_reservations', 'transfer_invitation_allocations', 'audit_events', 'outbox_messages'] as $table) {
            $state[$table] = DB::table($table)->orderBy('id')->get()->toJson();
        }

        return $state;
    }
}
