<?php

declare(strict_types=1);

namespace App\Domain\Platform\Services;

use App\Domain\Alliances\Models\Alliance;
use App\Domain\Audit\Services\AuditRecorder;
use App\Domain\Identity\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

final readonly class AllianceDataExportService
{
    private const SCHEMA_VERSION = 'phase6.v1';

    /** @var list<string> */
    private const REDACTED_COLUMNS = [
        'secret_hash',
        'signing_secret',
        'token_hash',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    public function __construct(
        private AuditRecorder $audit,
        private PlatformAdministratorAuthorization $authorization,
    ) {}

    /** @return array{contents: string, filename: string, rowCount: int, sha256: string, tableCounts: array<string, int>} */
    public function generate(User $actor, Alliance $alliance): array
    {
        $this->authorization->authorize($actor);

        $tables = DB::table('information_schema.columns')
            ->where('table_schema', 'public')
            ->where('column_name', 'alliance_id')
            ->orderBy('table_name')
            ->pluck('table_name')
            ->filter('is_string')
            ->values();

        $tablePayloads = [];
        $tableCounts = [];
        $rowCount = 1;

        foreach ($tables as $table) {
            $rows = DB::table($table)
                ->where('alliance_id', $alliance->id)
                ->get()
                ->map(fn (object $row): array => $this->sanitizeRow((array) $row))
                ->all();
            $tablePayloads[$table] = $rows;
            $tableCounts[$table] = count($rows);
            $rowCount += count($rows);
        }

        $payload = [
            'schema_version' => self::SCHEMA_VERSION,
            'generated_at' => now()->toIso8601String(),
            'alliance' => $this->sanitizeRow($alliance->getAttributes()),
            'tables' => $tablePayloads,
        ];
        $contents = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
        if (strlen($contents) > 104857600) {
            throw new RuntimeException('Alliance export exceeded the 100 MiB synchronous export safety limit.');
        }

        $sha256 = hash('sha256', $contents);
        DB::table('alliance_data_exports')->insert([
            'id' => (string) Str::ulid(),
            'alliance_id' => $alliance->id,
            'requested_by_user_id' => $actor->id,
            'schema_version' => self::SCHEMA_VERSION,
            'format' => 'json',
            'row_count' => $rowCount,
            'sha256' => $sha256,
            'generated_at' => now(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->audit->record('platform.alliance.exported', $actor, $alliance, $alliance, [
            'schema_version' => self::SCHEMA_VERSION,
            'row_count' => $rowCount,
            'sha256' => $sha256,
            'table_counts' => $tableCounts,
        ]);

        return [
            'contents' => $contents,
            'filename' => 'alliance-'.$alliance->id.'-'.now()->format('Ymd-His').'.json',
            'rowCount' => $rowCount,
            'sha256' => $sha256,
            'tableCounts' => $tableCounts,
        ];
    }

    /**
     * @param  array<string, mixed>  $row
     * @return array<string, mixed>
     */
    private function sanitizeRow(array $row): array
    {
        foreach (self::REDACTED_COLUMNS as $column) {
            if (array_key_exists($column, $row)) {
                $row[$column] = '[REDACTED]';
            }
        }

        return $row;
    }
}
