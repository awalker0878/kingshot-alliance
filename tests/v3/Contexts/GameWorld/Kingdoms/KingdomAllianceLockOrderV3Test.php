<?php

declare(strict_types=1);

namespace Tests\v3\Contexts\GameWorld\Kingdoms;

use App\Contexts\GameWorld\Kingdoms\Actions\ArchiveKingdom;
use App\Contexts\GameWorld\Kingdoms\Actions\ArchiveKingdomAlliance;
use App\Contexts\GameWorld\Kingdoms\Actions\ReconcileKingdomAlliances;
use App\Contexts\GameWorld\Kingdoms\Actions\ResolveKingdomAlliance;
use App\Contexts\GameWorld\Kingdoms\Actions\RestoreKingdomAlliance;
use App\Contexts\GameWorld\Kingdoms\Actions\UpdateKingdomAllianceIdentity;
use App\Contexts\GameWorld\Kingdoms\Enums\KingdomAllianceStatus;
use App\Contexts\GameWorld\Kingdoms\Enums\KingdomStatus;
use App\Contexts\GameWorld\Kingdoms\Models\Kingdom;
use App\Contexts\GameWorld\Kingdoms\Models\KingdomAlliance;
use Illuminate\Database\Events\QueryExecuted;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\v3\Support\ScenarioFactory;
use Tests\v3\TestCase;

final class KingdomAllianceLockOrderV3Test extends TestCase
{
    use DatabaseMigrations;

    /** @return iterable<string,array{string,bool}> */
    public static function commitOrders(): iterable
    {
        foreach (['update', 'restore', 'reconcile', 'archive'] as $operation) {
            yield 'Kingdom archival before '.$operation => [$operation, true];
            yield $operation.' before Kingdom archival' => [$operation, false];
        }
    }

    #[DataProvider('commitOrders')]
    public function test_child_writers_and_kingdom_archival_lock_the_parent_before_any_child(string $operation, bool $archiveFirst): void
    {
        $kingdom = app(ScenarioFactory::class)->kingdom(59269);
        $first = app(ResolveKingdomAlliance::class)->handle($kingdom->kingdomId, 'First', 'ONE', null);
        $second = app(ResolveKingdomAlliance::class)->handle($kingdom->kingdomId, 'Second', 'TWO', null);
        if ($operation === 'restore') {
            app(ArchiveKingdomAlliance::class)->handle($first->kingdomAllianceId);
        }
        $archive = static fn () => app(ArchiveKingdom::class)->handle($kingdom->kingdomId);
        $mutate = static fn () => match ($operation) {
            'update' => app(UpdateKingdomAllianceIdentity::class)->handle($first->kingdomAllianceId, $kingdom->kingdomId, 'Updated', 'NEW'),
            'restore' => app(RestoreKingdomAlliance::class)->handle($first->kingdomAllianceId),
            'reconcile' => app(ReconcileKingdomAlliances::class)->handle($first->kingdomAllianceId, $second->kingdomAllianceId, 'Confirmed duplicate'),
            'archive' => app(ArchiveKingdomAlliance::class)->handle($first->kingdomAllianceId),
        };
        $primary = $this->competitor();
        $attempted = false;
        $childLocks = [];
        DB::listen(static function (QueryExecuted $query) use ($primary, $archiveFirst, $archive, $mutate, &$attempted, &$childLocks): void {
            if (str_starts_with($query->sql, 'select') && str_contains($query->sql, '"kingdom_alliances"') && str_contains($query->sql, 'for update')) {
                $childLocks[] = $query->connectionName;
            }
            if ($attempted || $query->connectionName !== $primary || ! str_starts_with($query->sql, 'select * from "kingdoms"') || ! str_contains($query->sql, 'for update')) {
                return;
            }
            $attempted = true;
            self::assertSame([], $childLocks, 'No child may be locked before the Kingdom.');
            DB::setDefaultConnection('kingdom_writer');
            try {
                try {
                    $archiveFirst ? $mutate() : $archive();
                    self::fail('The competing writer must wait at the Kingdom.');
                } catch (QueryException $exception) {
                    self::assertSame('55P03', $exception->errorInfo[0] ?? null);
                }
                self::assertNotContains('kingdom_writer', $childLocks, 'A writer waiting for its Kingdom must not hold a child lock.');
            } finally {
                DB::setDefaultConnection($primary);
            }
        });
        try {
            $archiveFirst ? $archive() : $mutate();
            self::assertTrue($attempted);
            DB::setDefaultConnection('kingdom_writer');
            if ($archiveFirst && $operation !== 'archive') {
                try {
                    $mutate();
                    self::fail('The later writer must honor current archival state.');
                } catch (ValidationException $exception) {
                    self::assertNotEmpty($exception->errors());
                }
            } else {
                $archiveFirst ? $mutate() : $archive();
            }
            self::assertSame(KingdomStatus::Archived, Kingdom::query()->findOrFail($kingdom->kingdomId)->status);
            self::assertSame(0, KingdomAlliance::query()->where('kingdom_id', $kingdom->kingdomId)->where('status', KingdomAllianceStatus::Active)->count());
        } finally {
            DB::setDefaultConnection($primary);
            DB::purge('kingdom_writer');
        }
    }

    public function test_an_independent_kingdom_can_update_while_archival_holds_its_parent(): void
    {
        $factory = app(ScenarioFactory::class);
        $kingdom = $factory->kingdom(59269);
        $other = $factory->kingdom(59270);
        $alliance = app(ResolveKingdomAlliance::class)->handle($other->kingdomId, 'Independent', null, null);
        $primary = $this->competitor();
        $updated = false;
        DB::listen(static function (QueryExecuted $query) use ($primary, $other, $alliance, &$updated): void {
            if ($updated || $query->connectionName !== $primary || ! str_starts_with($query->sql, 'select * from "kingdoms"') || ! str_contains($query->sql, 'for update')) {
                return;
            }
            $updated = true;
            DB::setDefaultConnection('kingdom_writer');
            try {
                app(UpdateKingdomAllianceIdentity::class)->handle($alliance->kingdomAllianceId, $other->kingdomId, 'Updated Independently', null);
            } finally {
                DB::setDefaultConnection($primary);
            }
        });
        try {
            app(ArchiveKingdom::class)->handle($kingdom->kingdomId);
            self::assertTrue($updated);
            self::assertSame('Updated Independently', KingdomAlliance::query()->findOrFail($alliance->kingdomAllianceId)->current_name);
            self::assertSame(KingdomStatus::Active, Kingdom::query()->findOrFail($other->kingdomId)->status);
        } finally {
            DB::setDefaultConnection($primary);
            DB::purge('kingdom_writer');
        }
    }

    public function test_wrong_expected_kingdom_does_not_lock_a_foreign_child(): void
    {
        $factory = app(ScenarioFactory::class);
        $kingdom = $factory->kingdom(59269);
        $other = $factory->kingdom(59270);
        $alliance = app(ResolveKingdomAlliance::class)->handle($other->kingdomId, 'Foreign', null, null);
        $primary = $this->competitor();
        DB::connection('kingdom_writer')->beginTransaction();
        DB::connection('kingdom_writer')->table('kingdom_alliances')->where('id', $alliance->kingdomAllianceId)->lockForUpdate()->first();
        DB::connection()->statement("SET lock_timeout = '100ms'");
        try {
            try {
                app(UpdateKingdomAllianceIdentity::class)->handle($alliance->kingdomAllianceId, $kingdom->kingdomId, 'Rejected', null);
                self::fail('A wrong scope must be rejected without acquiring the foreign child.');
            } catch (ValidationException $exception) {
                self::assertArrayHasKey('kingdom_alliance', $exception->errors());
            }
            self::assertSame('Foreign', KingdomAlliance::query()->findOrFail($alliance->kingdomAllianceId)->current_name);
        } finally {
            DB::connection('kingdom_writer')->rollBack();
            DB::setDefaultConnection($primary);
            DB::connection()->statement('RESET lock_timeout');
            DB::purge('kingdom_writer');
        }
    }

    private function competitor(): string
    {
        $primary = DB::getDefaultConnection();
        config()->set('database.connections.kingdom_writer', array_replace(DB::connection()->getConfig(), ['name' => 'kingdom_writer']));
        DB::connection('kingdom_writer')->statement("SET lock_timeout = '100ms'");

        return $primary;
    }
}
