<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('event_player_contexts', function (Blueprint $table): void {
            $table->index(['player_id', 'occurrence_id'], 'event_player_contexts_player_occurrence_idx');
        });

        Schema::table('event_player_results', function (Blueprint $table): void {
            $table->index(['player_id', 'occurrence_id'], 'event_player_results_player_occurrence_idx');
        });

        Schema::table('event_player_result_metrics', function (Blueprint $table): void {
            $table->index(['metric_definition_id', 'event_player_result_id'], 'event_player_metrics_definition_result_idx');
        });

        Schema::table('event_alliance_results', function (Blueprint $table): void {
            $table->index(['alliance_id', 'occurrence_id'], 'event_alliance_results_alliance_occurrence_idx');
        });

        Schema::table('event_alliance_result_metrics', function (Blueprint $table): void {
            $table->index(['metric_definition_id', 'event_alliance_result_id'], 'event_alliance_metrics_definition_result_idx');
        });

        Schema::table('event_result_metrics', function (Blueprint $table): void {
            $table->index(['metric_definition_id', 'event_result_id'], 'event_metrics_definition_result_idx');
        });

        Schema::table('events', function (Blueprint $table): void {
            $table->index(['scope', 'event_type_id', 'player_id', 'id'], 'events_player_type_history_idx');
            $table->index(['scope', 'event_type_id', 'alliance_id', 'id'], 'events_alliance_type_history_idx');
            $table->index(['scope', 'event_type_id', 'kingdom_id', 'id'], 'events_kingdom_type_history_idx');
        });
    }

    public function down(): void
    {
        Schema::table('events', function (Blueprint $table): void {
            $table->dropIndex('events_kingdom_type_history_idx');
            $table->dropIndex('events_alliance_type_history_idx');
            $table->dropIndex('events_player_type_history_idx');
        });

        Schema::table('event_result_metrics', function (Blueprint $table): void {
            $table->dropIndex('event_metrics_definition_result_idx');
        });

        Schema::table('event_alliance_result_metrics', function (Blueprint $table): void {
            $table->dropIndex('event_alliance_metrics_definition_result_idx');
        });

        Schema::table('event_alliance_results', function (Blueprint $table): void {
            $table->dropIndex('event_alliance_results_alliance_occurrence_idx');
        });

        Schema::table('event_player_result_metrics', function (Blueprint $table): void {
            $table->dropIndex('event_player_metrics_definition_result_idx');
        });

        Schema::table('event_player_results', function (Blueprint $table): void {
            $table->dropIndex('event_player_results_player_occurrence_idx');
        });

        Schema::table('event_player_contexts', function (Blueprint $table): void {
            $table->dropIndex('event_player_contexts_player_occurrence_idx');
        });
    }
};
