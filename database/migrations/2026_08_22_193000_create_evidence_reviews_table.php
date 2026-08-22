<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evidence_reviews', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('evidence_id')->constrained('game_evidence')->cascadeOnDelete();
            $table->foreignUlid('extraction_attempt_id')->constrained('evidence_extraction_attempts')->restrictOnDelete();
            $table->foreignUlid('alliance_id')->constrained('alliances')->restrictOnDelete();
            $table->foreignUlid('occurrence_id')->constrained('event_occurrences')->restrictOnDelete();
            $table->unsignedInteger('revision_number');
            $table->string('status', 32);
            $table->string('report_timestamp_text', 64)->nullable();
            $table->char('semantic_fingerprint', 64);
            $table->ulid('semantic_duplicate_review_id')->nullable();
            $table->text('duplicate_resolution')->nullable();
            $table->foreignUlid('reviewed_by_player_id')->constrained('players')->restrictOnDelete();
            $table->foreignUlid('resolved_by_player_id')->nullable()->constrained('players')->restrictOnDelete();
            $table->timestamp('reviewed_at');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();
            $table->unique(['evidence_id', 'revision_number'], 'evidence_review_revision_unique');
            $table->index(['occurrence_id', 'semantic_fingerprint', 'status'], 'evidence_review_semantic_idx');
        });

        Schema::create('evidence_review_rows', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('review_id')->constrained('evidence_reviews')->cascadeOnDelete();
            $table->unsignedInteger('row_ordinal');
            $table->ulid('source_rank_field_id')->nullable();
            $table->ulid('source_name_field_id')->nullable();
            $table->ulid('source_damage_field_id')->nullable();
            $table->foreignUlid('player_id')->nullable()->constrained('players')->restrictOnDelete();
            $table->string('player_name', 128);
            $table->unsignedInteger('reported_rank')->nullable();
            $table->unsignedBigInteger('damage_points')->nullable();
            $table->boolean('included')->default(true);
            $table->boolean('rank_corrected')->default(false);
            $table->boolean('name_corrected')->default(false);
            $table->boolean('damage_corrected')->default(false);
            $table->string('correction_reason', 500)->nullable();
            $table->timestamps();
            $table->unique(['review_id', 'row_ordinal']);
            $table->index(['review_id', 'included', 'player_id'], 'evidence_review_rows_commit_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evidence_review_rows');
        Schema::dropIfExists('evidence_reviews');
    }
};
