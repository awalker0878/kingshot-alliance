<?php

declare(strict_types=1);

namespace App\Contexts\Platform\DataGovernance\Services;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use LogicException;

final class AllianceExportTableWriter
{
    private const REDACTED_COLUMNS = ['secret_hash', 'signing_secret', 'token_hash', 'two_factor_secret', 'two_factor_recovery_codes'];

    public function write(AllianceExportBuffer $buffer, string $table, string $allianceId): int
    {
        $columns = DB::table('information_schema.columns')->where('table_schema', 'public')
            ->where('table_name', $table)->orderBy('ordinal_position')->get(['column_name', 'udt_name']);
        $grammar = DB::connection()->getQueryGrammar();
        $projection = [];
        foreach ($columns as $column) {
            // Preserve the existing PDO export scalar contract, including JSON-as-text.
            $projection[] = new AllianceExportColumn(
                (string) $column->column_name,
                in_array($column->column_name, self::REDACTED_COLUMNS, true),
                in_array($column->udt_name, ['bool', 'int2', 'int4', 'int8', 'float4', 'float8'], true),
            );
        }
        $primary = collect(Schema::getIndexes($table))->firstWhere('primary', true);
        if ($primary === null) {
            throw new LogicException('Alliance export requires a primary key for '.$table.'.');
        }
        $order = implode(', ', array_map(static fn (string $name): string => 'export_row.'.$grammar->wrap($name), $primary['columns']));
        $query = DB::table($table)->where('alliance_id', $allianceId)->select($projection);
        $rows = 'SELECT row_to_json(export_row)::text AS payload FROM ('.$query->toSql().') export_row';
        $stats = DB::selectOne('SELECT count(*) AS count, COALESCE(sum(octet_length(payload)), 0) AS bytes FROM ('.$rows.') export_rows', $query->getBindings());
        $count = (int) $stats->count;
        // Reject the complete table before transferring any row payload to PHP.
        $buffer->assertFits((int) $stats->bytes + max(0, $count - 1) + 2);
        $buffer->write('[');
        if ($count > 0) {
            $ordered = 'SELECT row_number() OVER (ORDER BY '.$order.') AS ordinal, row_to_json(export_row)::text AS payload FROM ('.$query->toSql().') export_row';
            $chunks = 'SELECT ordinal, part, substring(payload FROM part * 16384 + 1 FOR 16384) AS chunk FROM ('.$ordered.') export_rows CROSS JOIN LATERAL generate_series(0, (char_length(payload) - 1) / 16384) AS parts(part) ORDER BY ordinal, part';
            // NO HOLD keeps the cursor inside the authorized repeatable-read transaction.
            DB::statement('DECLARE alliance_export_rows NO SCROLL CURSOR FOR '.$chunks, $query->getBindings());
            do {
                $batch = DB::select('FETCH FORWARD 25 FROM alliance_export_rows');
                foreach ($batch as $chunk) {
                    if ((int) $chunk->part === 0 && (int) $chunk->ordinal > 1) {
                        $buffer->write(',');
                    }
                    $buffer->write((string) $chunk->chunk);
                }
            } while ($batch !== []);
            DB::statement('CLOSE alliance_export_rows');
        }
        $buffer->write(']');

        return $count;
    }
}
