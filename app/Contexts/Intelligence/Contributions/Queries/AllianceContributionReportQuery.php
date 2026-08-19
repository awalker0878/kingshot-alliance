<?php

declare(strict_types=1);

namespace App\Contexts\Intelligence\Contributions\Queries;

use App\Contexts\Alliance\Lifecycle\Queries\AllianceReferenceQuery;
use App\Contexts\Operations\Results\Queries\AllianceEventReportQuery;

final readonly class AllianceContributionReportQuery
{
    public function __construct(
        private ContributionReportingQuery $contributions,
        private AllianceReferenceQuery $alliances,
        private AllianceEventReportQuery $eventResults,
    ) {}

    /** @return list<array<string,scalar|null>> */
    public function rows(string $allianceId): array
    {
        $alliance = $this->alliances->require($allianceId);
        $rows = [];

        foreach ($this->contributions->reportRows($allianceId) as $record) {
            $rows[] = array_replace($this->baseRow($alliance->allianceId, $alliance->name, $alliance->kingdomId), [
                'record_kind' => 'contribution',
                'record_id' => (string) $record['record_id'],
                'player_id' => (string) $record['player_id'],
                'player' => (string) $record['player'],
                'historical_alliance_id' => $alliance->allianceId,
                'historical_alliance_name' => $alliance->name,
                'category' => (string) $record['category'],
                'unit' => (string) $record['unit'],
                'value' => (float) $record['value'],
                'period_start' => (string) $record['period_start'],
                'period_end' => (string) $record['period_end'],
                'status' => (string) $record['status'],
                'source' => (string) $record['source'],
                'data_class' => (string) $record['data_class'],
                'evidence' => $record['evidence'],
                'calculation_key' => $record['calculation_key'],
                'calculation_version' => $record['calculation_version'],
                'correction_of_record_id' => $record['correction_of_record_id'],
                'recorded_at' => (string) $record['recorded_at'],
                'approved_at' => $record['approved_at'],
                'reversed_at' => $record['reversed_at'],
                'reversal_reason' => $record['reversal_reason'],
                'correction_reason' => $record['correction_reason'],
            ]);
        }

        array_push($rows, ...$this->eventResults->rows($alliance->allianceId, $alliance->name, $alliance->kingdomId));
        usort($rows, static function (array $left, array $right): int {
            $date = strcmp((string) $left['recorded_at'], (string) $right['recorded_at']);

            return $date !== 0 ? $date : strcmp((string) $left['record_id'], (string) $right['record_id']);
        });

        return $rows;
    }

    /** @return array<string,scalar|null> */
    private function baseRow(string $allianceId, string $allianceName, string $kingdomId): array
    {
        return [
            'record_kind' => null, 'record_id' => null, 'player_id' => null, 'player' => null,
            'event_id' => null, 'occurrence_id' => null, 'event_scope' => null, 'event_type' => null,
            'event_started_at' => null, 'historical_alliance_id' => $allianceId,
            'historical_alliance_name' => $allianceName, 'historical_kingdom_id' => $kingdomId,
            'event_outcome' => null, 'event_rank' => null, 'event_score' => null,
            'metric_key' => null, 'metric_label' => null, 'metric_dimension' => null,
            'metric_unit' => null, 'metric_value' => null, 'category' => null, 'unit' => null,
            'value' => null, 'period_start' => null, 'period_end' => null, 'status' => null,
            'source' => null, 'data_class' => null, 'evidence' => null, 'calculation_key' => null,
            'calculation_version' => null, 'correction_of_record_id' => null, 'recorded_at' => null,
            'approved_at' => null, 'reversed_at' => null, 'reversal_reason' => null,
            'correction_reason' => null,
        ];
    }
}
