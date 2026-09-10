<?php

declare(strict_types=1);

namespace Tests\Contexts\Alliance\Membership\Integration\Concurrency;

use App\Contexts\Alliance\Lifecycle\ValueObjects\AllianceReference;
use App\Contexts\Alliance\Membership\Actions\MarkRosterEntryLeft;
use App\Contexts\Alliance\Membership\Actions\UpsertRosterEntry;
use App\Contexts\Alliance\Membership\Enums\AllianceRank;
use App\Contexts\Alliance\Membership\Enums\MembershipStatus;
use App\Contexts\Alliance\Membership\Enums\RosterState;
use App\Contexts\Alliance\Membership\Models\AllianceMembership;
use App\Contexts\Alliance\Membership\Models\AllianceRosterEntry;
use App\Contexts\Alliance\Membership\ValueObjects\RosterEntryReference;
use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Support\ScenarioFactory;
use Tests\TestCase;

final class RosterWriteLockOrderV3Test extends TestCase
{
    use DatabaseTruncation;

    /** @return iterable<string,array{bool}> */
    public static function mutations(): iterable
    {
        yield 'update first' => [true];
        yield 'departure first' => [false];
    }

    #[DataProvider('mutations')]
    public function test_distinct_administrators_lock_player_before_roster_in_both_orders(bool $updateFirst): void
    {
        [$owner, $manager, $alliance, $entry] = $this->fixture();
        $update = static fn () => app(UpsertRosterEntry::class)->handle($owner->playerId, $alliance->allianceId, ['name' => 'Updated Roster', 'state' => RosterState::Active], $entry->rosterEntryId);
        $leave = static fn () => app(MarkRosterEntryLeft::class)->handle($manager->playerId, $alliance->allianceId, $entry->rosterEntryId);
        $primary = $this->competitor();
        $attempted = false;
        $entryLocks = [];
        DB::listen(static function (QueryExecuted $query) use ($primary, $updateFirst, $update, $leave, &$attempted, &$entryLocks): void {
            if (str_starts_with($query->sql, 'select') && str_contains($query->sql, '"alliance_roster_entries"') && str_contains($query->sql, 'for update')) {
                $entryLocks[] = $query->connectionName;
            }
            if ($attempted || $query->connectionName !== $primary || ! str_starts_with($query->sql, 'select * from "players"') || ! str_contains($query->sql, 'for update')) {
                return;
            }
            $attempted = true;
            self::assertSame([], $entryLocks);
            DB::setDefaultConnection('roster_writer');
            try {
                try {
                    $updateFirst ? $leave() : $update();
                    self::fail('The competing administrator must wait at Player before acquiring the entry.');
                } catch (QueryException $exception) {
                    self::assertSame('55P03', $exception->errorInfo[0] ?? null);
                }
                self::assertNotContains('roster_writer', $entryLocks);
            } finally {
                DB::setDefaultConnection($primary);
            }
        });
        try {
            $updateFirst ? $update() : $leave();
            self::assertTrue($attempted);
            DB::setDefaultConnection('roster_writer');
            $updateFirst ? $leave() : $update();
            $current = AllianceRosterEntry::query()->findOrFail($entry->rosterEntryId);
            self::assertSame($updateFirst ? RosterState::Left : RosterState::Active, $current->state);
            self::assertSame('Updated Roster', $current->observed_name);
            self::assertSame(1, DB::table('audit_events')->where('event', 'membership.roster_entry_updated')->count());
            self::assertSame(1, DB::table('audit_events')->where('event', 'membership.roster_entry_left')->count());
        } finally {
            DB::setDefaultConnection($primary);
            DB::purge('roster_writer');
        }
    }

    public function test_an_independent_entry_can_be_updated_while_another_player_is_locked(): void
    {
        [$owner, $manager, $alliance, $entry] = $this->fixture();
        $factory = app(ScenarioFactory::class);
        $other = $factory->roster($owner, $alliance, $factory->unclaimedPlayer(59271));
        $primary = $this->competitor();
        $updated = false;
        DB::listen(static function (QueryExecuted $query) use ($primary, $manager, $alliance, $other, &$updated): void {
            if ($updated || $query->connectionName !== $primary || ! str_starts_with($query->sql, 'select * from "players"') || ! str_contains($query->sql, 'for update')) {
                return;
            }
            $updated = true;
            DB::setDefaultConnection('roster_writer');
            try {
                app(UpsertRosterEntry::class)->handle($manager->playerId, $alliance->allianceId, ['name' => 'Independent Update'], $other->rosterEntryId);
            } finally {
                DB::setDefaultConnection($primary);
            }
        });
        try {
            app(MarkRosterEntryLeft::class)->handle($owner->playerId, $alliance->allianceId, $entry->rosterEntryId);
            self::assertTrue($updated);
            self::assertSame('Independent Update', AllianceRosterEntry::query()->findOrFail($other->rosterEntryId)->observed_name);
            self::assertSame(RosterState::Left, AllianceRosterEntry::query()->findOrFail($entry->rosterEntryId)->state);
        } finally {
            DB::setDefaultConnection($primary);
            DB::purge('roster_writer');
        }
    }

    #[DataProvider('mutations')]
    public function test_changed_player_routing_is_rejected_before_the_entry_is_modified(bool $update): void
    {
        [$owner, , $alliance, $entry] = $this->fixture();
        $replacement = app(ScenarioFactory::class)->unclaimedPlayer(59271);
        $primary = $this->competitor();
        $changed = false;
        $beforeEvents = DB::table('audit_events')->count();
        DB::listen(static function (QueryExecuted $query) use ($primary, $entry, $replacement, &$changed): void {
            if ($changed || $query->connectionName !== $primary || ! str_starts_with($query->sql, 'select * from "players"') || ! str_contains($query->sql, 'for update')) {
                return;
            }
            $changed = true;
            DB::connection('roster_writer')->table('alliance_roster_entries')->where('id', $entry->rosterEntryId)->update(['player_id' => $replacement->playerId]);
        });
        try {
            try {
                $update
                    ? app(UpsertRosterEntry::class)->handle($owner->playerId, $alliance->allianceId, ['name' => 'Stale Update'], $entry->rosterEntryId)
                    : app(MarkRosterEntryLeft::class)->handle($owner->playerId, $alliance->allianceId, $entry->rosterEntryId);
                self::fail('The lock must still identify the discovered Player.');
            } catch (ModelNotFoundException) {
                self::assertTrue($changed);
            }
            $current = AllianceRosterEntry::query()->findOrFail($entry->rosterEntryId);
            self::assertSame($replacement->playerId, $current->player_id);
            self::assertSame($entry->observedName, $current->observed_name);
            self::assertSame(RosterState::Active, $current->state);
            self::assertSame($beforeEvents, DB::table('audit_events')->count());
        } finally {
            DB::setDefaultConnection($primary);
            DB::purge('roster_writer');
        }
    }

    #[DataProvider('mutations')]
    public function test_late_outbox_failure_rolls_back_entry_and_audit(bool $update): void
    {
        [$owner, , $alliance, $entry] = $this->fixture();
        $before = $this->state();
        $failed = false;
        DB::listen(static function (QueryExecuted $query) use (&$failed): void {
            if (! $failed && str_starts_with($query->sql, 'insert into "outbox_messages"')) {
                $failed = true;
                throw new RuntimeException('Injected roster outbox failure.');
            }
        });
        try {
            $update
                ? app(UpsertRosterEntry::class)->handle($owner->playerId, $alliance->allianceId, ['name' => 'Rolled Back'], $entry->rosterEntryId)
                : app(MarkRosterEntryLeft::class)->handle($owner->playerId, $alliance->allianceId, $entry->rosterEntryId);
            self::fail('All roster effects must roll back.');
        } catch (RuntimeException $exception) {
            self::assertSame('Injected roster outbox failure.', $exception->getMessage());
        }
        self::assertTrue($failed);
        self::assertSame($before, $this->state());
    }

    /** @return array{PlayerReference,PlayerReference,AllianceReference,RosterEntryReference} */
    private function fixture(): array
    {
        $factory = app(ScenarioFactory::class);
        $owner = $factory->player($factory->account()->userId, 59271);
        $manager = $factory->player($factory->account()->userId, 59271);
        $alliance = $factory->alliance($owner);
        AllianceMembership::query()->create(['alliance_id' => $alliance->allianceId, 'player_id' => $manager->playerId, 'rank' => AllianceRank::R4, 'status' => MembershipStatus::Active, 'joined_at' => now()]);
        $target = $factory->unclaimedPlayer(59271);

        return [$owner, $manager, $alliance, $factory->roster($owner, $alliance, $target)];
    }

    private function competitor(): string
    {
        $primary = DB::getDefaultConnection();
        config()->set('database.connections.roster_writer', array_replace(DB::connection()->getConfig(), ['name' => 'roster_writer']));
        DB::connection('roster_writer')->statement("SET lock_timeout = '100ms'");

        return $primary;
    }

    /** @return array<string,string> */
    private function state(): array
    {
        return [
            'entries' => DB::table('alliance_roster_entries')->orderBy('id')->get()->toJson(),
            'audit' => DB::table('audit_events')->orderBy('id')->get()->toJson(),
            'outbox' => DB::table('outbox_messages')->orderBy('id')->get()->toJson(),
        ];
    }
}
