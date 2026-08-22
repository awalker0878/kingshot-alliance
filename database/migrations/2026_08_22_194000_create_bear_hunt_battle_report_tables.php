<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('bear_hunt_battle_reports', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('occurrence_id')->constrained('event_occurrences')->restrictOnDelete();
            $table->ulid('source_evidence_id');
            $table->ulid('source_commit_attempt_id');
            $table->char('idempotency_key', 64)->unique();
            $table->char('report_fingerprint', 64);
            $table->string('report_timestamp_text', 64)->nullable();
            $table->string('status', 24);
            $table->foreignUlid('recorded_by_player_id')->constrained('players')->restrictOnDelete();
            $table->timestamp('recorded_at');
            $table->foreignUlid('removed_by_player_id')->nullable()->constrained('players')->restrictOnDelete();
            $table->timestamp('removed_at')->nullable();
            $table->string('removal_reason', 1000)->nullable();
            $table->timestamps();
            $table->unique(['occurrence_id', 'report_fingerprint'], 'bear_hunt_report_fingerprint_unique');
            $table->index(['occurrence_id', 'status', 'recorded_at'], 'bear_hunt_report_occurrence_status_idx');
            $table->index(['source_evidence_id', 'source_commit_attempt_id'], 'bear_hunt_report_source_idx');
        });

        Schema::create('bear_hunt_battle_report_entries', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('report_id')->constrained('bear_hunt_battle_reports')->cascadeOnDelete();
            $table->foreignUlid('player_id')->constrained('players')->restrictOnDelete();
            $table->unsignedInteger('reported_rank')->nullable();
            $table->unsignedBigInteger('damage_points');
            $table->timestamps();
            $table->unique(['report_id', 'player_id']);
            $table->index(['player_id', 'report_id']);
        });

        Schema::create('bear_hunt_result_baselines', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('occurrence_id')->constrained('event_occurrences')->restrictOnDelete();
            $table->foreignUlid('player_id')->constrained('players')->restrictOnDelete();
            $table->ulid('source_event_player_result_id')->nullable();
            $table->unsignedBigInteger('baseline_score')->nullable();
            $table->unsignedInteger('baseline_rank')->nullable();
            $table->timestamp('captured_at');
            $table->timestamps();
            $table->unique(['occurrence_id', 'player_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('bear_hunt_result_baselines');
        Schema::dropIfExists('bear_hunt_battle_report_entries');
        Schema::dropIfExists('bear_hunt_battle_reports');
    }
};
