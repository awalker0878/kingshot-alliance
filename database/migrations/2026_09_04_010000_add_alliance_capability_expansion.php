<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('recruitment_candidates', function (Blueprint $table): void {
            $table->string('reentry_control', 32)->default('normal');
            $table->text('reentry_reason')->nullable();
            $table->timestampTz('reentry_review_at')->nullable();
            $table->foreignUlid('reentry_set_by_player_id')->nullable()->constrained('players')->restrictOnDelete();
            $table->timestamp('reentry_set_at')->nullable();
            $table->index(['alliance_id', 'reentry_control', 'reentry_review_at'], 'recruitment_candidates_reentry_idx');
        });

        Schema::create('alliance_roster_evidence', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('alliance_id')->constrained('alliances')->restrictOnDelete();
            $table->string('lifecycle_status', 32);
            $table->string('original_name', 255);
            $table->string('disk', 64);
            $table->string('path', 1024)->nullable();
            $table->string('mime_type', 96);
            $table->unsignedBigInteger('size_bytes');
            $table->unsignedInteger('width');
            $table->unsignedInteger('height');
            $table->char('sha256', 64);
            $table->char('perceptual_hash', 16)->nullable();
            $table->ulid('visual_duplicate_evidence_id')->nullable();
            $table->unsignedTinyInteger('visual_duplicate_distance')->nullable();
            $table->foreignUlid('uploaded_by_player_id')->constrained('players')->restrictOnDelete();
            $table->timestamp('scanned_at');
            $table->timestamp('binary_deleted_at')->nullable();
            $table->timestamp('redacted_at')->nullable();
            $table->string('deletion_reason', 64)->nullable();
            $table->timestamps();

            $table->unique(['alliance_id', 'sha256'], 'alliance_roster_evidence_exact_unique');
            $table->index(['alliance_id', 'lifecycle_status', 'created_at'], 'alliance_roster_evidence_status_idx');
            $table->index(['alliance_id', 'perceptual_hash'], 'alliance_roster_evidence_visual_idx');
        });

        Schema::create('evidence_alliance_roster_reviews', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('evidence_id')->constrained('alliance_roster_evidence')->cascadeOnDelete();
            $table->foreignUlid('alliance_id')->constrained('alliances')->restrictOnDelete();
            $table->string('schema_version', 96)->default('alliance-roster-v1');
            $table->unsignedInteger('revision_number');
            $table->string('status', 32);
            $table->timestampTz('captured_at');
            $table->json('payload');
            $table->char('semantic_fingerprint', 64);
            $table->ulid('semantic_duplicate_review_id')->nullable();
            $table->text('duplicate_resolution')->nullable();
            $table->foreignUlid('reviewed_by_player_id')->constrained('players')->restrictOnDelete();
            $table->timestamp('reviewed_at');
            $table->timestamps();

            $table->unique(['evidence_id', 'revision_number'], 'evidence_alliance_roster_review_revision_unique');
            $table->index(['alliance_id', 'semantic_fingerprint', 'status'], 'evidence_alliance_roster_semantic_idx');
        });

        Schema::create('alliance_roster_observation_batches', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('alliance_id')->constrained('alliances')->cascadeOnDelete();
            $table->string('source_evidence_id', 64);
            $table->string('source_review_id', 64);
            $table->string('schema_version', 96);
            $table->timestampTz('captured_at');
            $table->char('destination_idempotency_key', 64)->unique();
            $table->foreignUlid('accepted_by_player_id')->constrained('players')->restrictOnDelete();
            $table->timestamp('accepted_at');
            $table->timestamps();
            $table->index(['alliance_id', 'captured_at'], 'alliance_roster_observation_batch_time_idx');
        });

        Schema::create('alliance_roster_observations', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('batch_id')->constrained('alliance_roster_observation_batches')->cascadeOnDelete();
            $table->foreignUlid('alliance_id')->constrained('alliances')->cascadeOnDelete();
            $table->foreignUlid('roster_entry_id')->nullable()->constrained('alliance_roster_entries')->restrictOnDelete();
            $table->string('observed_name', 160);
            $table->string('game_player_id', 100)->nullable();
            $table->string('observed_rank', 8)->nullable();
            $table->unsignedBigInteger('power')->nullable();
            $table->json('source_metadata')->nullable();
            $table->timestamps();
            $table->index(['alliance_id', 'game_player_id'], 'alliance_roster_observation_game_id_idx');
            $table->index(['alliance_id', 'roster_entry_id'], 'alliance_roster_observation_entry_idx');
            $table->index(['batch_id', 'observed_name'], 'alliance_roster_observation_batch_name_idx');
        });

        Schema::create('evidence_alliance_roster_commit_attempts', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('evidence_id')->constrained('alliance_roster_evidence')->cascadeOnDelete();
            $table->foreignUlid('review_id')->constrained('evidence_alliance_roster_reviews')->restrictOnDelete();
            $table->foreignUlid('alliance_id')->constrained('alliances')->restrictOnDelete();
            $table->string('status', 32);
            $table->char('idempotency_key', 64);
            $table->string('destination_action', 96)->default('record_alliance_roster_observation_batch');
            $table->foreignUlid('destination_batch_id')->nullable()->constrained('alliance_roster_observation_batches')->restrictOnDelete();
            $table->json('destination_receipt')->nullable();
            $table->string('failure_code', 64)->nullable();
            $table->foreignUlid('started_by_player_id')->constrained('players')->restrictOnDelete();
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->unique(['review_id', 'idempotency_key'], 'evidence_alliance_roster_commit_key_unique');
            $table->index(['alliance_id', 'status', 'created_at'], 'evidence_alliance_roster_commit_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evidence_alliance_roster_commit_attempts');
        Schema::dropIfExists('alliance_roster_observations');
        Schema::dropIfExists('alliance_roster_observation_batches');
        Schema::dropIfExists('evidence_alliance_roster_reviews');
        Schema::dropIfExists('alliance_roster_evidence');

        Schema::table('recruitment_candidates', function (Blueprint $table): void {
            $table->dropIndex('recruitment_candidates_reentry_idx');
            $table->dropConstrainedForeignId('reentry_set_by_player_id');
            $table->dropColumn(['reentry_control', 'reentry_reason', 'reentry_review_at', 'reentry_set_at']);
        });
    }
};
