<?php

declare(strict_types=1);

namespace Tests\Shared\Testing\Integration;

use Illuminate\Foundation\Testing\DatabaseTruncation;
use Illuminate\Foundation\Testing\RefreshDatabaseState;
use Illuminate\Support\Facades\DB;
use LogicException;
use Tests\Support\MigrationReferenceData;
use Tests\Support\ScenarioFactory;
use Tests\TestCase;

final class MigrationReferenceDataIsolationTest extends TestCase
{
    use DatabaseTruncation;

    public function test_committed_setup_retains_real_migration_data_and_alliance_plan_foreign_keys(): void
    {
        foreach (MigrationReferenceData::TABLES as $table) {
            self::assertGreaterThan(0, DB::table($table)->count(), $table);
        }
        $factory = new ScenarioFactory;
        $owner = $factory->player($factory->account()->userId);
        $alliance = $factory->alliance($owner);

        self::assertSame('standard', DB::table('alliance_plan_assignments')
            ->where('alliance_id', $alliance->allianceId)->value('plan_code'));
    }

    public function test_reset_restores_exact_reference_rows_and_discards_committed_fixtures(): void
    {
        $expected = $this->referenceRows();
        $factory = new ScenarioFactory;
        $owner = $factory->player($factory->account()->userId);
        $alliance = $factory->alliance($owner);
        DB::table('platform_plans')->where('code', 'standard')->update(['name' => 'Mutated by test']);
        DB::table('event_types')->update(['is_active' => false]);
        DB::table('event_metric_definitions')->delete();

        $this->resetCommittedDatabase();

        self::assertSame($expected, $this->referenceRows());
        self::assertFalse(DB::table('alliance_plan_assignments')->where('alliance_id', $alliance->allianceId)->exists());
        self::assertSame(0, DB::table('users')->count());
        self::assertSame(0, DB::table('players')->count());
        self::assertSame(0, DB::table('alliances')->count());
        self::assertTrue(RefreshDatabaseState::$migrated);
    }

    public function test_repeated_resets_preserve_entitlement_identity_sequence_and_remove_additions(): void
    {
        $expected = $this->referenceRows();
        $seededMaximum = (int) DB::table('platform_plan_entitlements')->max('id');
        $record = $expected['platform_plan_entitlements'][0];
        unset($record['id']);
        $record['entitlement_key'] = 'test.reference-sequence';

        for ($attempt = 0; $attempt < 2; $attempt++) {
            $this->resetCommittedDatabase();
            self::assertSame($expected, $this->referenceRows());
            $id = DB::table('platform_plan_entitlements')->insertGetId($record);
            self::assertGreaterThan($seededMaximum, $id);
        }
    }

    public function test_restoration_rejects_nonempty_tables_without_changing_rows_or_dispatcher(): void
    {
        $expected = $this->referenceRows();
        $dispatcher = DB::connection()->getEventDispatcher();

        try {
            MigrationReferenceData::restore(DB::connection());
            self::fail('Restoration must not merge fixture mutations into the baseline.');
        } catch (LogicException $exception) {
            self::assertStringContainsString('requires an empty table', $exception->getMessage());
        }

        self::assertSame($expected, $this->referenceRows());
        self::assertSame($dispatcher, DB::connection()->getEventDispatcher());
        self::assertSame(0, DB::connection()->transactionLevel());
    }

    public function test_restored_baseline_supports_a_subsequent_rollback_boundary(): void
    {
        $expected = $this->referenceRows();
        $this->resetCommittedDatabase();
        DB::beginTransaction();

        try {
            self::assertSame($expected, $this->referenceRows());
            $factory = new ScenarioFactory;
            $owner = $factory->player($factory->account()->userId);
            $alliance = $factory->alliance($owner);
            self::assertTrue(DB::table('alliance_plan_assignments')->where('alliance_id', $alliance->allianceId)->exists());
        } finally {
            DB::rollBack();
        }

        self::assertSame($expected, $this->referenceRows());
        self::assertSame(0, DB::table('users')->count());
        self::assertSame(0, DB::table('alliances')->count());
    }

    public function test_restoration_rejects_an_open_test_transaction(): void
    {
        DB::beginTransaction();

        try {
            MigrationReferenceData::restore(DB::connection());
            self::fail('A committed reference reset must not run inside an outer transaction.');
        } catch (LogicException $exception) {
            self::assertStringContainsString('outside a test transaction', $exception->getMessage());
        } finally {
            DB::rollBack();
        }
    }

    /** @return array<string, list<array<string, mixed>>> */
    private function referenceRows(): array
    {
        $rows = [];
        foreach (MigrationReferenceData::TABLES as $table) {
            $rows[$table] = DB::table($table)->orderBy($table === 'platform_plans' ? 'code' : 'id')
                ->get()->map(static fn (object $row): array => (array) $row)->all();
        }

        return $rows;
    }
}
