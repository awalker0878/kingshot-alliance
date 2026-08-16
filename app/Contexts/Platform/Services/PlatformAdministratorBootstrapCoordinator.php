<?php

declare(strict_types=1);

namespace App\Contexts\Platform\Services;

use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Platform-owned coordination for the one exceptional bootstrap invariant that has
 * no pre-existing authority row to lock. Production PostgreSQL uses a transaction-
 * scoped advisory lock; no generic repository-wide lock table is introduced.
 */
final class PlatformAdministratorBootstrapCoordinator
{
    private const LOCK_NAMESPACE = 1263556436;

    private const LOCK_KEY = 1;

    public function acquire(): void
    {
        if (DB::transactionLevel() < 1) {
            throw new LogicException('Platform administrator bootstrap coordination requires an existing database transaction.');
        }

        $driver = DB::connection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::select('select pg_advisory_xact_lock(?, ?)', [self::LOCK_NAMESPACE, self::LOCK_KEY]);

            return;
        }

        // SQLite is used only by isolated/local test paths; it serializes writes at
        // the database level and has no PostgreSQL advisory-lock equivalent.
        if ($driver === 'sqlite') {
            return;
        }

        throw new LogicException('Platform administrator bootstrap coordination requires PostgreSQL.');
    }
}
