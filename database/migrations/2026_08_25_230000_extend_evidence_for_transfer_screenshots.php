<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('game_evidence', function (Blueprint $table): void {
            $table->foreignUlid('occurrence_id')->nullable()->change();
            $table->foreignUlid('transfer_plan_id')->nullable()->after('occurrence_id')->constrained('transfer_plans')->restrictOnDelete();
            $table->foreignUlid('transfer_participant_id')->nullable()->after('transfer_plan_id')->constrained('transfer_participants')->restrictOnDelete();
            $table->unique(
                ['alliance_id', 'transfer_plan_id', 'transfer_participant_id', 'expected_kind', 'sha256'],
                'game_evidence_transfer_exact_unique',
            );
            $table->index(
                ['alliance_id', 'transfer_plan_id', 'transfer_participant_id', 'lifecycle_status', 'created_at'],
                'game_evidence_transfer_status_idx',
            );
            $table->index(
                ['alliance_id', 'transfer_plan_id', 'transfer_participant_id', 'perceptual_hash'],
                'game_evidence_transfer_visual_idx',
            );
        });

        $this->addScopeConstraint();

        Schema::create('evidence_transfer_reviews', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('evidence_id')->constrained('game_evidence')->cascadeOnDelete();
            $table->foreignUlid('extraction_attempt_id')->constrained('evidence_extraction_attempts')->restrictOnDelete();
            $table->foreignUlid('alliance_id')->constrained('alliances')->restrictOnDelete();
            $table->foreignUlid('transfer_plan_id')->constrained('transfer_plans')->restrictOnDelete();
            $table->foreignUlid('transfer_participant_id')->constrained('transfer_participants')->restrictOnDelete();
            $table->foreignUlid('transfer_window_id')->constrained('transfer_windows')->restrictOnDelete();
            $table->foreignUlid('target_kingdom_id')->nullable()->constrained('kingdoms')->restrictOnDelete();
            $table->string('evidence_kind', 64);
            $table->string('schema_version', 96);
            $table->unsignedInteger('revision_number');
            $table->string('status', 32);
            $table->timestampTz('observed_at');
            $table->timestampTz('valid_until')->nullable();
            $table->unsignedBigInteger('governor_power')->nullable();
            $table->unsignedBigInteger('transfer_score')->nullable();
            $table->unsignedBigInteger('transfer_passes_available')->nullable();
            $table->unsignedBigInteger('transfer_passes_required')->nullable();
            $table->string('invitation_status', 48)->nullable();
            $table->unsignedBigInteger('target_power_cap')->nullable();
            $table->string('kingdom_classification', 32)->nullable();
            $table->string('official_group_identifier', 96)->nullable();
            $table->char('semantic_fingerprint', 64);
            $table->ulid('semantic_duplicate_review_id')->nullable();
            $table->text('duplicate_resolution')->nullable();
            $table->foreignUlid('reviewed_by_player_id')->constrained('players')->restrictOnDelete();
            $table->foreignUlid('resolved_by_player_id')->nullable()->constrained('players')->restrictOnDelete();
            $table->timestamp('reviewed_at');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->unique(['evidence_id', 'revision_number'], 'evidence_transfer_review_revision_unique');
            $table->index(
                ['alliance_id', 'transfer_plan_id', 'transfer_participant_id', 'semantic_fingerprint', 'status'],
                'evidence_transfer_review_semantic_idx',
            );
        });

        Schema::create('evidence_transfer_review_kingdoms', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('review_id')->constrained('evidence_transfer_reviews')->cascadeOnDelete();
            $table->unsignedInteger('kingdom_number');
            $table->unsignedInteger('ordinal');
            $table->timestamps();
            $table->unique(['review_id', 'kingdom_number'], 'evidence_transfer_review_kingdom_unique');
            $table->unique(['review_id', 'ordinal'], 'evidence_transfer_review_ordinal_unique');
        });

        Schema::create('evidence_transfer_commit_attempts', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('evidence_id')->constrained('game_evidence')->cascadeOnDelete();
            $table->foreignUlid('transfer_review_id')->constrained('evidence_transfer_reviews')->restrictOnDelete();
            $table->foreignUlid('alliance_id')->constrained('alliances')->restrictOnDelete();
            $table->string('status', 32);
            $table->char('idempotency_key', 64);
            $table->string('destination_action', 96);
            $table->ulid('destination_receipt_id')->nullable();
            $table->json('destination_receipt')->nullable();
            $table->string('failure_code', 64)->nullable();
            $table->foreignUlid('started_by_player_id')->constrained('players')->restrictOnDelete();
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['transfer_review_id', 'idempotency_key', 'status'], 'evidence_transfer_commit_review_key_idx');
            $table->index(['alliance_id', 'status', 'created_at'], 'evidence_transfer_commit_status_idx');
        });

        Schema::create('transfer_evidence_receipts', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('alliance_id')->constrained('alliances')->cascadeOnDelete();
            $table->foreignUlid('transfer_window_id')->constrained('transfer_windows')->cascadeOnDelete();
            $table->foreignUlid('transfer_plan_id')->constrained('transfer_plans')->cascadeOnDelete();
            $table->foreignUlid('transfer_participant_id')->nullable()->constrained('transfer_participants')->cascadeOnDelete();
            $table->string('evidence_id', 64);
            $table->string('review_id', 64);
            $table->string('evidence_kind', 64);
            $table->string('schema_version', 96);
            $table->char('idempotency_key', 64)->unique();
            $table->json('destination_ids');
            $table->foreignUlid('accepted_by_player_id')->constrained('players')->restrictOnDelete();
            $table->timestamp('accepted_at');
            $table->timestamps();
            $table->index(['alliance_id', 'transfer_plan_id', 'transfer_participant_id'], 'transfer_evidence_receipt_scope_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transfer_evidence_receipts');
        Schema::dropIfExists('evidence_transfer_commit_attempts');
        Schema::dropIfExists('evidence_transfer_review_kingdoms');
        Schema::dropIfExists('evidence_transfer_reviews');
        $this->dropScopeConstraint();

        Schema::table('game_evidence', function (Blueprint $table): void {
            $table->dropUnique('game_evidence_transfer_exact_unique');
            $table->dropIndex('game_evidence_transfer_status_idx');
            $table->dropIndex('game_evidence_transfer_visual_idx');
            $table->dropConstrainedForeignId('transfer_participant_id');
            $table->dropConstrainedForeignId('transfer_plan_id');
            $table->foreignUlid('occurrence_id')->nullable(false)->change();
        });
    }

    private function addScopeConstraint(): void
    {
        $valid = '((occurrence_id IS NOT NULL AND transfer_plan_id IS NULL AND transfer_participant_id IS NULL) OR (occurrence_id IS NULL AND transfer_plan_id IS NOT NULL AND transfer_participant_id IS NOT NULL))';
        $driver = DB::getDriverName();
        if ($driver === 'sqlite') {
            DB::statement("CREATE TRIGGER game_evidence_scope_insert BEFORE INSERT ON game_evidence WHEN NOT {$valid} BEGIN SELECT RAISE(ABORT, 'invalid game evidence scope'); END");
            DB::statement("CREATE TRIGGER game_evidence_scope_update BEFORE UPDATE OF occurrence_id, transfer_plan_id, transfer_participant_id ON game_evidence WHEN NOT {$valid} BEGIN SELECT RAISE(ABORT, 'invalid game evidence scope'); END");

            return;
        }

        DB::statement("ALTER TABLE game_evidence ADD CONSTRAINT game_evidence_scope_check CHECK ({$valid})");
    }

    private function dropScopeConstraint(): void
    {
        $driver = DB::getDriverName();
        if ($driver === 'sqlite') {
            DB::statement('DROP TRIGGER IF EXISTS game_evidence_scope_insert');
            DB::statement('DROP TRIGGER IF EXISTS game_evidence_scope_update');

            return;
        }
        if ($driver === 'mysql') {
            DB::statement('ALTER TABLE game_evidence DROP CHECK game_evidence_scope_check');

            return;
        }

        DB::statement('ALTER TABLE game_evidence DROP CONSTRAINT IF EXISTS game_evidence_scope_check');
    }
};
