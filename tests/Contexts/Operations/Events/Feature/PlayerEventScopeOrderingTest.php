<?php

declare(strict_types=1);

namespace Tests\Contexts\Operations\Events\Feature;

use App\Contexts\Alliance\Lifecycle\ValueObjects\AllianceReference;
use App\Contexts\Alliance\Membership\Actions\MarkRosterEntryLeft;
use App\Contexts\Alliance\Membership\ValueObjects\RosterEntryReference;
use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;
use App\Contexts\Operations\Access\Enums\OperationsPermission;
use App\Contexts\Operations\Events\Actions\CancelEvent;
use App\Contexts\Operations\Events\Actions\CreateEvent;
use App\Contexts\Operations\Events\Enums\EventScope;
use App\Contexts\Operations\Events\Models\EventTypeScope;
use App\Contexts\Operations\Events\Services\PlayerEventAuthorization;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Support\ScenarioFactory;
use Tests\TestCase;

final class PlayerEventScopeOrderingTest extends TestCase
{
    use DatabaseTruncation;

    public function test_manager_authority_uses_one_current_membership_even_with_a_thousand_target_rosters(): void
    {
        [$actor, $target, $alliance, $roster, $event] = $this->scenario();
        $alliances = $entries = [];
        for ($i = 0; $i < 1001; $i++) {
            $id = strtolower((string) Str::ulid());
            $alliances[] = ['id' => $id, 'kingdom_id' => $actor->kingdomId, 'name' => 'Roster history '.$i, 'slug' => 'roster-history-'.$id, 'created_at' => now(), 'updated_at' => now()];
            $entries[] = ['id' => strtolower((string) Str::ulid()), 'alliance_id' => $id, 'player_id' => $target->playerId, 'observed_name' => $target->currentName, 'state' => 'active', 'created_at' => now(), 'updated_at' => now()];
        }
        DB::table('alliances')->insert($alliances);
        DB::table('alliance_roster_entries')->insert($entries);
        $queries = [];
        DB::listen(static function (QueryExecuted $query) use (&$queries): void {
            $queries[] = $query->sql;
        });
        self::assertTrue(app(PlayerEventAuthorization::class)->allows($actor->playerId, $target->playerId, OperationsPermission::EventPlayerManage));
        self::assertLessThanOrEqual(12, count($queries));
        $readCount = count($queries);
        app(CancelEvent::class)->handle($actor->playerId, $event);
        $writeQueries = array_slice($queries, $readCount);
        self::assertLessThanOrEqual(30, count($writeQueries));
        $rosterLocks = array_values(array_filter($writeQueries, static fn ($sql): bool => str_contains($sql, 'alliance_roster_entries') && str_contains($sql, 'for update')));
        self::assertCount(1, $rosterLocks);
        self::assertStringContainsString('"alliance_id" = ?', $rosterLocks[0]);
        self::assertStringContainsString('"player_id" = ?', $rosterLocks[0]);
        app(MarkRosterEntryLeft::class)->handle($actor->playerId, $alliance->allianceId, $roster->rosterEntryId);
        self::assertFalse(app(PlayerEventAuthorization::class)->allows($actor->playerId, $target->playerId, OperationsPermission::EventPlayerManage));
        $this->expectException(AuthorizationException::class);
        app(CancelEvent::class)->handle($actor->playerId, $event);
    }

    /** @return iterable<string,array{bool}> */
    public static function orders(): iterable
    {
        yield 'roster removal first' => [true];
        yield 'event mutation first' => [false];
    }

    #[DataProvider('orders')]
    public function test_roster_removal_and_event_mutation_share_scope_order_and_retry_rechecks_current_presence(bool $removeFirst): void
    {
        [$actor, , $alliance, $roster, $event] = $this->scenario();
        $run = static fn () => app(CancelEvent::class)->handle($actor->playerId, $event);
        $remove = static fn () => app(MarkRosterEntryLeft::class)->handle($actor->playerId, $alliance->allianceId, $roster->rosterEntryId);
        $primary = DB::getDefaultConnection();
        config()->set('database.connections.roster_competitor', [...DB::connection()->getConfig(), 'name' => 'roster_competitor']);
        $other = DB::connection('roster_competitor');
        $other->statement("SET lock_timeout = '150ms'");
        DB::connection($primary)->statement("SET lock_timeout = '150ms'");
        $attempted = false;
        $locks = [];
        DB::listen(static function (QueryExecuted $query) use ($primary, $removeFirst, $remove, &$attempted, &$locks): void {
            if ($query->connectionName !== $primary) {
                return;
            }
            if (str_contains($query->sql, 'for update') || str_contains($query->sql, 'for share')) {
                $locks[] = $query->sql;
            }
            if (! $removeFirst && ! $attempted && str_starts_with($query->sql, 'update "events"')) {
                $attempted = true;
                DB::setDefaultConnection('roster_competitor');
                try {
                    try {
                        $remove();
                        self::fail('Roster removal must wait for the admitted Event mutation.');
                    } catch (QueryException $exception) {
                        self::assertSame('55P03', $exception->errorInfo[0] ?? null);
                    }
                } finally {
                    DB::setDefaultConnection($primary);
                }
            }
        });
        try {
            if ($removeFirst) {
                $other->beginTransaction();
                DB::setDefaultConnection('roster_competitor');
                $remove();
                DB::setDefaultConnection($primary);
                try {
                    $run();
                    self::fail('Event admission must wait at Alliance scope before locking identities.');
                } catch (QueryException $exception) {
                    self::assertSame('55P03', $exception->errorInfo[0] ?? null);
                }
                self::assertSame([], array_values(array_filter($locks, static fn ($sql): bool => str_contains($sql, 'from "players"'))));
                $other->commit();
            } else {
                $run();
                self::assertTrue($attempted);
                $allianceIndex = $playerIndex = null;
                foreach ($locks as $index => $sql) {
                    if ($allianceIndex === null && str_contains($sql, 'from "alliances"')) {
                        $allianceIndex = $index;
                    }
                    if ($playerIndex === null && str_contains($sql, 'from "players"')) {
                        $playerIndex = $index;
                    }
                }
                self::assertNotNull($allianceIndex);
                self::assertNotNull($playerIndex);
                self::assertLessThan($playerIndex, $allianceIndex);
                $remove();
            }
            $this->expectException(AuthorizationException::class);
            $run();
        } finally {
            DB::setDefaultConnection($primary);
            while ($other->transactionLevel() > 0) {
                $other->rollBack();
            }
            DB::connection($primary)->statement("SET lock_timeout = '0'");
            DB::purge('roster_competitor');
        }
    }

    /** @return array{PlayerReference,PlayerReference,AllianceReference,RosterEntryReference,string} */
    private function scenario(): array
    {
        $factory = app(ScenarioFactory::class);
        $actor = $factory->player($factory->account()->userId, 61636);
        $target = $factory->unclaimedPlayer(61636);
        $alliance = $factory->alliance($actor);
        $roster = $factory->roster($actor, $alliance, $target);
        $scope = EventTypeScope::query()->where('scope', EventScope::Player->value)
            ->whereHas('eventType', static fn ($query) => $query->where('slug', 'hall-of-governors'))->firstOrFail();
        $event = app(CreateEvent::class)->handle($target->playerId, (string) $scope->id, EventScope::Player, $target->playerId, CarbonImmutable::now('UTC')->addDays(3), durationMinutes: 60);

        return [$actor, $target, $alliance, $roster, $event->eventId];
    }
}
