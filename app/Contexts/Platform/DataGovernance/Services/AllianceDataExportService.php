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
use LogicException;

final readonly class AllianceDataExportService
{
    private const SCHEMA_VERSION = 'v3.1';

    public function __construct(
        private AuditRecorder $audit,
        private PlatformWriteState $platformWriteState,
        private PlatformAuthorization $mutations,
        private AllianceReferenceQuery $alliances,
        private AllianceExportTableWriter $tableWriter,
    ) {}

    /** @return array{buffer:AllianceExportBuffer,filename:string,rowCount:int,sha256:string,tableCounts:array<string,int>} */
    public function generate(AccountIdentity $actor, string $allianceId): array
    {
        if (DB::transactionLevel() !== 0) {
            throw new LogicException('Alliance export requires its own repeatable-read transaction.');
        }

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
            ];
            $buffer = new AllianceExportBuffer;
            $metadata = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR);
            $buffer->write(substr($metadata, 0, -1).',"tables":{');
            $tableCounts = [];
            $rowCount = 1;
            foreach ($tables as $index => $table) {
                $buffer->write(($index > 0 ? ',' : '').json_encode($table, JSON_THROW_ON_ERROR).':');
                $tableCounts[$table] = $this->tableWriter->write($buffer, $table, $alliance->allianceId);
                $rowCount += $tableCounts[$table];
            }
            $buffer->write('}}');
            $sha256 = $buffer->sha256();
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
                'buffer' => $buffer,
                'filename' => 'alliance-'.$alliance->allianceId.'-'.$generatedAt->format('Ymd-His').'.json',
                'rowCount' => $rowCount,
                'sha256' => $sha256,
                'tableCounts' => $tableCounts,
            ];
        });
    }
}
