<?php

declare(strict_types=1);

namespace App\Domain\Contributions\Services;

use App\Contexts\Alliance\Access\Services\AllianceMutationAuthority;
use App\Contexts\Alliance\Core\Models\Alliance;
use App\Contexts\GameWorld\Models\Player;
use App\Domain\Authorization\Enums\PermissionKey;
use App\Domain\Contributions\Models\ContributionReportRun;
use App\Domain\Contributions\Queries\AllianceContributionReportQuery;
use App\Shared\Audit\Services\AuditRecorder;
use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;
use RuntimeException;
use Throwable;

final class ContributionReportExporter
{
    public const REPORT_VERSION = 'event-history.v2';

    public function __construct(
        private readonly AllianceMutationAuthority $authority,
        private readonly AllianceContributionReportQuery $reports,
        private readonly AuditRecorder $audit,
    ) {}

    /** @return array{content: string, mime: string, filename: string, run: ContributionReportRun} */
    public function export(Alliance $alliance, Player $actor, string $format): array
    {
        if (! in_array($format, ['csv', 'spreadsheet'], true)) {
            throw new InvalidArgumentException('Unsupported contribution report format.');
        }

        $connection = DB::connection();
        if ($connection->transactionLevel() > 0) {
            return $this->exportWithinTransaction($alliance, $actor, $format);
        }

        $connection->beginTransaction();

        try {
            $this->setRepeatableReadBeforeFirstQuery($connection);
            $export = $this->exportWithinTransaction($alliance, $actor, $format);
            $connection->commit();

            return $export;
        } catch (Throwable $exception) {
            $connection->rollBack();

            throw $exception;
        }
    }

    /** @return array{content: string, mime: string, filename: string, run: ContributionReportRun} */
    private function exportWithinTransaction(Alliance $alliance, Player $actor, string $format): array
    {
        $context = $this->authority->require($actor, $alliance, PermissionKey::ContributionManage);
        $rows = $this->reports->rows($context->alliance);
        $content = $format === 'csv'
            ? $this->csv($context->alliance, $rows)
            : $this->spreadsheet($context->alliance, $rows);
        $checksum = hash('sha256', $content);
        $idempotencyKey = hash('sha256', $context->alliance->id.'|'.$context->actor->id.'|'.$format.'|'.Str::ulid());

        $run = ContributionReportRun::query()->create([
            'alliance_id' => $context->alliance->id,
            'requested_by_player_id' => $context->actor->id,
            'format' => $format,
            'status' => 'completed',
            'report_version' => self::REPORT_VERSION,
            'filters' => [],
            'row_count' => count($rows),
            'checksum' => $checksum,
            'idempotency_key' => $idempotencyKey,
            'completed_at' => now(),
        ]);

        $this->audit->record('contribution.report.exported', $context->actor, $run, $context->alliance, [
            'format' => $format,
            'report_version' => self::REPORT_VERSION,
            'row_count' => count($rows),
            'checksum' => $checksum,
        ]);

        return [
            'content' => $content,
            'mime' => $format === 'csv' ? 'text/csv; charset=UTF-8' : 'application/vnd.ms-excel; charset=UTF-8',
            'filename' => sprintf('%s-contributions.%s', $context->alliance->slug, $format === 'csv' ? 'csv' : 'xls'),
            'run' => $run,
        ];
    }

    private function setRepeatableReadBeforeFirstQuery(Connection $connection): void
    {
        if ($connection->getDriverName() === 'pgsql') {
            $connection->statement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
        }
    }

    /** @param list<array<string, scalar|null>> $rows */
    private function csv(Alliance $alliance, array $rows): string
    {
        $handle = fopen('php://temp', 'w+');
        if ($handle === false) {
            throw new RuntimeException('Unable to allocate CSV export buffer.');
        }

        $headers = $this->headers();
        fputcsv($handle, $headers);
        foreach ($rows as $row) {
            $normalized = ['report_version' => self::REPORT_VERSION, 'alliance_id' => $alliance->id] + $row;
            fputcsv($handle, array_map(static fn (string $key): string => (string) ($normalized[$key] ?? ''), $headers));
        }

        rewind($handle);
        $content = stream_get_contents($handle);
        fclose($handle);
        if ($content === false) {
            throw new RuntimeException('Unable to read CSV export buffer.');
        }

        return $content;
    }

    /** @param list<array<string, scalar|null>> $rows */
    private function spreadsheet(Alliance $alliance, array $rows): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>';
        $xml .= '<?mso-application progid="Excel.Sheet"?>';
        $xml .= '<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet">';
        $xml .= '<Worksheet ss:Name="Contributions"><Table>';
        $headers = $this->headers();
        $xml .= $this->spreadsheetRow($headers);

        foreach ($rows as $row) {
            $normalized = ['report_version' => self::REPORT_VERSION, 'alliance_id' => $alliance->id] + $row;
            $xml .= $this->spreadsheetRow(array_map(
                static fn (string $key): string => (string) ($normalized[$key] ?? ''),
                $headers,
            ));
        }

        return $xml.'</Table></Worksheet></Workbook>';
    }

    /** @param list<string> $values */
    private function spreadsheetRow(array $values): string
    {
        $cells = '';
        foreach ($values as $value) {
            $cells .= '<Cell><Data ss:Type="String">'.htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8').'</Data></Cell>';
        }

        return '<Row>'.$cells.'</Row>';
    }

    /** @return list<string> */
    private function headers(): array
    {
        return [
            'report_version',
            'alliance_id',
            'record_kind',
            'record_id',
            'event_id',
            'occurrence_id',
            'event_scope',
            'event_type',
            'event_started_at',
            'historical_alliance_id',
            'historical_alliance_name',
            'historical_kingdom_id',
            'player_id',
            'player',
            'event_outcome',
            'event_rank',
            'event_score',
            'metric_key',
            'metric_label',
            'metric_dimension',
            'metric_unit',
            'metric_value',
            'category',
            'unit',
            'value',
            'period_start',
            'period_end',
            'status',
            'source',
            'data_class',
            'evidence',
            'calculation_key',
            'calculation_version',
            'correction_of_record_id',
            'recorded_at',
            'approved_at',
            'reversed_at',
            'reversal_reason',
            'correction_reason',
        ];
    }
}
