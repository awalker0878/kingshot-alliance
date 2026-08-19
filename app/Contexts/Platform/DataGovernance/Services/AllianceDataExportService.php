<?php

declare(strict_types=1);

namespace App\Contexts\Platform\DataGovernance\Services;

use App\Contexts\Accounts\Identity\ValueObjects\AccountIdentity;
use App\Contexts\Alliance\Lifecycle\Queries\AllianceReferenceQuery;
use App\Contexts\Platform\Administration\Services\PlatformAuthorization;
use App\Contexts\Platform\Administration\Services\PlatformWriteState;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

final readonly class AllianceDataExportService
{
    private const SCHEMA_VERSION = 'v3.1';

    private const REDACTED_COLUMNS = [
        'secret_hash',
        'signing_secret',
        'token_hash',
        'two_factor_secret',
        'two_factor_recovery_codes',
    ];

    public function __construct(
        private AuditRecorder $audit,
        private PlatformWriteState $platformWriteState,
        private PlatformAuthorization $mutations,
        private AllianceReferenceQuery $alliances,
    ) {}

    /** @return array{contents:string,filename:string,rowCount:int,sha256:string,tableCounts:array<string,int>} */
    public function generate(AccountIdentity $actor, string $allianceId): array
    {
        return DB::transaction(function () use ($actor, $allianceId): array {
            if (DB::connection()->getDriverName() === 'pgsql') {
                DB::statement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
            }

            $context = $this->mutations->authorizeContext($this->platformWriteState->lock($actor));
            $alliance = $this->alliances->lockCurrent($allianceId);
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
                    ->where('alliance_id', $alliance->allianceId)
                    ->get()
                    ->map(fn (object $row): array => $this->sanitizeRow((array) $row))
                    ->all();
                $tablePayloads[$table] = $rows;
                $tableCounts[$table] = count($rows);
                $rowCount += count($rows);
            }

            $generatedAt = now();
            $payload = [
                'schema_version' => self::SCHEMA_VERSION,
                'generated_at' => $generatedAt->toIso8601String(),
                'alliance' => [
                    'id' => $alliance->allianceId,
                    'name' => $alliance->name,
                    'slug' => $alliance->slug,
                    'kingdom_id' => $alliance->kingdomId,
                    'language' => $alliance->language,
                    'timezone' => $alliance->timezone,
                    'status' => $alliance->status,
                ],
                'tables' => $tablePayloads,
            ];
            $contents = json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            if (strlen($contents) > 104857600) {
                throw new RuntimeException('Alliance export exceeded the 100 MiB synchronous export safety limit.');
            }

            $sha256 = hash('sha256', $contents);
            $exportId = (string) Str::ulid();
            DB::table('alliance_data_exports')->insert([
                'id' => $exportId,
                'alliance_id' => $alliance->allianceId,
                'requested_by_user_id' => $context->actor->userId,
                'schema_version' => self::SCHEMA_VERSION,
                'format' => 'json',
                'row_count' => $rowCount,
                'sha256' => $sha256,
                'generated_at' => $generatedAt,
                'created_at' => $generatedAt,
                'updated_at' => $generatedAt,
            ]);

            $this->audit->record(
                'platform.alliance.exported',
                $context->actor,
                null,
                $alliance->allianceId,
                [
                    'export_id' => $exportId,
                    'schema_version' => self::SCHEMA_VERSION,
                    'row_count' => $rowCount,
                    'sha256' => $sha256,
                    'table_counts' => $tableCounts,
                ],
            );

            return [
                'contents' => $contents,
                'filename' => 'alliance-'.$alliance->allianceId.'-'.$generatedAt->format('Ymd-His').'.json',
                'rowCount' => $rowCount,
                'sha256' => $sha256,
                'tableCounts' => $tableCounts,
            ];
        });
    }

    /** @param array<string, mixed> $row @return array<string, mixed> */
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
