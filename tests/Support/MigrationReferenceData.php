<?php

declare(strict_types=1);

namespace Tests\Support;

use Illuminate\Database\Connection;
use LogicException;

/**
 * Process-local copies of reference rows created by the real, complete migration
 * chain. Never learn a baseline from a database that has already run fixtures.
 */
final class MigrationReferenceData
{
    /**
     * Tables populated by the migration chain, in foreign-key insertion order.
     * Event metrics currently have no seeded rows: they remain ordinary mutable
     * data, covered by truncation and the unclassified-populated-table guard.
     *
     * @var list<string>
     */
    public const array TABLES = [
        'platform_plans',
        'platform_plan_entitlements',
        'event_types',
        'event_type_scopes',
        'event_type_workflow_dimensions',
        // Mutable sweep position is restored to its exact migrated singleton after each committed test.
        'gift_code_source_alert_sweep',
    ];

    /** @var array<string, array<string, list<array<string, mixed>>>> */
    private static array $snapshots = [];

    public static function captured(Connection $connection): bool
    {
        return isset(self::$snapshots[self::key($connection)]);
    }

    /** Call only immediately after this worker has performed migrate:fresh. */
    public static function captureFresh(Connection $connection): void
    {
        self::assertCommittedPostgres($connection);
        $rows = [];
        foreach (self::TABLES as $table) {
            $rows[$table] = array_values($connection->table($table)->get()
                ->map(static fn (object $row): array => (array) $row)->all());
            if ($rows[$table] === []) {
                throw new LogicException('Fresh migration reference table is empty: '.$table);
            }
        }

        // Fail closed when a future migration introduces another populated
        // table: its reset contract must be reviewed rather than silently lost.
        $schema = $connection->getSchemaBuilder();
        foreach ($schema->getTables($schema->getCurrentSchemaListing()) as $table) {
            if (in_array($table['name'], [...self::TABLES, 'migrations'], true)) {
                continue;
            }
            if ($connection->table($table['schema_qualified_name'])->exists()) {
                throw new LogicException('Unclassified migration reference table: '.$table['schema_qualified_name']);
            }
        }

        self::$snapshots[self::key($connection)] = $rows;
    }

    /** Restore only after truncation, with constraints enabled and no test transaction. */
    public static function restore(Connection $connection): void
    {
        self::assertCommittedPostgres($connection);
        $rows = self::$snapshots[self::key($connection)]
            ?? throw new LogicException('No fresh-migration reference snapshot exists for this test database.');
        $dispatcher = $connection->getEventDispatcher();
        $connection->unsetEventDispatcher();

        try {
            $connection->transaction(static function () use ($connection, $rows): void {
                foreach (self::TABLES as $table) {
                    if ($connection->table($table)->exists()) {
                        throw new LogicException('Reference restoration requires an empty table: '.$table);
                    }
                }
                foreach ($rows as $table => $records) {
                    foreach (array_chunk($records, 250) as $chunk) {
                        $connection->table($table)->insert($chunk);
                    }
                }

                // Explicit IDs preserve the migrated rows exactly. PostgreSQL
                // TRUNCATE RESTART IDENTITY also resets this sequence; advance
                // it so a subsequent normal insert cannot reuse a seeded ID.
                $table = $connection->getTablePrefix().'platform_plan_entitlements';
                $sequence = $connection->selectOne('select pg_get_serial_sequence(?, ?) as name', [$table, 'id']);
                if (! is_string($sequence?->name) || $sequence->name === '') {
                    throw new LogicException('The plan entitlement identity sequence is missing.');
                }
                $entitlementIds = array_column($rows['platform_plan_entitlements'], 'id');
                if ($entitlementIds === []) {
                    throw new LogicException('The migrated plan entitlement identities are missing.');
                }
                $connection->selectOne('select setval(?::regclass, ?, true)', [
                    $sequence->name,
                    max($entitlementIds),
                ]);
            });
        } finally {
            if ($dispatcher !== null) {
                $connection->setEventDispatcher($dispatcher);
            }
        }
    }

    private static function assertCommittedPostgres(Connection $connection): void
    {
        if ($connection->getDriverName() !== 'pgsql' || $connection->transactionLevel() !== 0) {
            throw new LogicException('Migration reference snapshots require PostgreSQL outside a test transaction.');
        }
    }

    private static function key(Connection $connection): string
    {
        return json_encode([
            $connection->getDriverName(),
            $connection->getConfig('host'),
            $connection->getConfig('port'),
            $connection->getDatabaseName(),
            $connection->getConfig('search_path'),
            $connection->getTablePrefix(),
        ], JSON_THROW_ON_ERROR);
    }
}
