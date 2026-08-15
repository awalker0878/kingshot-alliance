<?php

declare(strict_types=1);

namespace Tests\Feature\Events;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class EventHistoryPerformanceContractTest extends TestCase
{
    use RefreshDatabase;

    public function test_history_query_path_indexes_exist(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            self::markTestSkipped('Index contract is asserted against the production PostgreSQL engine.');
        }

        $indexes = DB::table('pg_indexes')
            ->where('schemaname', 'public')
            ->whereIn('indexname', [
                'event_player_contexts_player_occurrence_idx',
                'event_player_results_player_occurrence_idx',
                'event_player_metrics_definition_result_idx',
                'event_alliance_results_alliance_occurrence_idx',
                'event_alliance_metrics_definition_result_idx',
                'event_metrics_definition_result_idx',
                'events_player_type_history_idx',
                'events_alliance_type_history_idx',
                'events_kingdom_type_history_idx',
            ])
            ->pluck('indexname')
            ->all();

        self::assertEqualsCanonicalizing([
            'event_player_contexts_player_occurrence_idx',
            'event_player_results_player_occurrence_idx',
            'event_player_metrics_definition_result_idx',
            'event_alliance_results_alliance_occurrence_idx',
            'event_alliance_metrics_definition_result_idx',
            'event_metrics_definition_result_idx',
            'events_player_type_history_idx',
            'events_alliance_type_history_idx',
            'events_kingdom_type_history_idx',
        ], $indexes);
    }
}
