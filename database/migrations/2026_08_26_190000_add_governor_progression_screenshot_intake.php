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
        $this->dropScopeConstraint();

        Schema::table('game_evidence', function (Blueprint $table): void {
            $table->foreignUlid('roster_entry_id')
                ->nullable()
                ->after('occurrence_id')
                ->constrained('alliance_roster_entries')
                ->restrictOnDelete();
            $table->unique(
                ['alliance_id', 'roster_entry_id', 'expected_kind', 'sha256'],
                'game_evidence_governor_exact_unique',
            );
            $table->index(
                ['alliance_id', 'roster_entry_id', 'lifecycle_status', 'created_at'],
                'game_evidence_governor_status_idx',
            );
            $table->index(
                ['alliance_id', 'roster_entry_id', 'perceptual_hash'],
                'game_evidence_governor_visual_idx',
            );
        });

        $this->addScopeConstraint(true);

        Schema::create('evidence_progression_normalization_attempts', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('evidence_id')->constrained('game_evidence')->cascadeOnDelete();
            $table->foreignUlid('extraction_attempt_id')->constrained('evidence_extraction_attempts')->restrictOnDelete();
            $table->string('status', 32);
            $table->string('normalizer_key', 96);
            $table->string('normalizer_version', 40);
            $table->string('progression_dataset_id', 120);
            $table->char('progression_dataset_checksum', 64);
            $table->json('normalized_payload');
            $table->json('warnings')->nullable();
            $table->string('failure_code', 64)->nullable();
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['evidence_id', 'status', 'created_at'], 'evidence_progression_normalization_status_idx');
            $table->index(['progression_dataset_id', 'progression_dataset_checksum'], 'evidence_progression_normalization_dataset_idx');
        });

        Schema::create('evidence_governor_progression_reviews', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('evidence_id')->constrained('game_evidence')->cascadeOnDelete();
            $table->foreignUlid('normalization_attempt_id')->constrained('evidence_progression_normalization_attempts')->restrictOnDelete();
            $table->foreignUlid('alliance_id')->constrained('alliances')->restrictOnDelete();
            $table->foreignUlid('roster_entry_id')->constrained('alliance_roster_entries')->restrictOnDelete();
            $table->foreignUlid('player_id')->constrained('players')->restrictOnDelete();
            $table->string('evidence_kind', 64);
            $table->string('schema_version', 96);
            $table->string('progression_dataset_id', 120);
            $table->char('progression_dataset_checksum', 64);
            $table->unsignedInteger('revision_number');
            $table->string('status', 32);
            $table->timestampTz('captured_at');
            $table->json('payload');
            $table->char('semantic_fingerprint', 64);
            $table->ulid('semantic_duplicate_review_id')->nullable();
            $table->text('duplicate_resolution')->nullable();
            $table->foreignUlid('reviewed_by_player_id')->constrained('players')->restrictOnDelete();
            $table->foreignUlid('resolved_by_player_id')->nullable()->constrained('players')->restrictOnDelete();
            $table->timestamp('reviewed_at');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->unique(['evidence_id', 'revision_number'], 'evidence_governor_review_revision_unique');
            $table->index(
                ['alliance_id', 'roster_entry_id', 'semantic_fingerprint', 'status'],
                'evidence_governor_review_semantic_idx',
            );
        });

        Schema::create('evidence_governor_progression_commit_attempts', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('evidence_id')->constrained('game_evidence')->cascadeOnDelete();
            $table->foreignUlid('governor_review_id')->constrained('evidence_governor_progression_reviews')->restrictOnDelete();
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
            $table->index(['governor_review_id', 'idempotency_key', 'status'], 'evidence_governor_commit_review_key_idx');
            $table->index(['alliance_id', 'status', 'created_at'], 'evidence_governor_commit_status_idx');
        });

        Schema::create('governor_progression_observations', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('alliance_id')->constrained('alliances')->cascadeOnDelete();
            $table->foreignUlid('roster_entry_id')->constrained('alliance_roster_entries')->cascadeOnDelete();
            $table->foreignUlid('player_id')->constrained('players')->restrictOnDelete();
            $table->string('kind', 64);
            $table->json('payload');
            $table->timestampTz('captured_at');
            $table->string('progression_dataset_id', 120);
            $table->char('progression_dataset_checksum', 64);
            $table->string('source', 32)->default('screenshot_evidence');
            $table->string('evidence_id', 64);
            $table->string('evidence_review_id', 64);
            $table->char('destination_idempotency_key', 64)->unique();
            $table->foreignUlid('accepted_by_player_id')->constrained('players')->restrictOnDelete();
            $table->timestamp('accepted_at');
            $table->timestamps();
            $table->index(['alliance_id', 'roster_entry_id', 'captured_at'], 'governor_progression_observation_roster_idx');
            $table->index(['alliance_id', 'player_id', 'captured_at'], 'governor_progression_observation_player_idx');
            $table->index(['alliance_id', 'roster_entry_id', 'kind', 'captured_at'], 'governor_progression_observation_kind_idx');
        });

        Schema::create('governor_progression_evidence_receipts', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('alliance_id')->constrained('alliances')->cascadeOnDelete();
            $table->foreignUlid('roster_entry_id')->constrained('alliance_roster_entries')->cascadeOnDelete();
            $table->foreignUlid('player_id')->constrained('players')->restrictOnDelete();
            $table->foreignUlid('observation_id')->constrained('governor_progression_observations')->restrictOnDelete();
            $table->string('evidence_id', 64);
            $table->string('review_id', 64);
            $table->string('evidence_kind', 64);
            $table->string('schema_version', 96);
            $table->string('progression_dataset_id', 120);
            $table->char('progression_dataset_checksum', 64);
            $table->char('idempotency_key', 64)->unique();
            $table->foreignUlid('accepted_by_player_id')->constrained('players')->restrictOnDelete();
            $table->timestamp('accepted_at');
            $table->timestamps();
            $table->index(['alliance_id', 'roster_entry_id', 'created_at'], 'governor_progression_receipt_scope_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('governor_progression_evidence_receipts');
        Schema::dropIfExists('governor_progression_observations');
        Schema::dropIfExists('evidence_governor_progression_commit_attempts');
        Schema::dropIfExists('evidence_governor_progression_reviews');
        Schema::dropIfExists('evidence_progression_normalization_attempts');

        $this->dropScopeConstraint();
        Schema::table('game_evidence', function (Blueprint $table): void {
            $table->dropUnique('game_evidence_governor_exact_unique');
            $table->dropIndex('game_evidence_governor_status_idx');
            $table->dropIndex('game_evidence_governor_visual_idx');
            $table->dropConstrainedForeignId('roster_entry_id');
        });
        $this->addScopeConstraint(false);
    }

    private function addScopeConstraint(bool $includeGovernor): void
    {
        if ($includeGovernor) {
            $bear = '(occurrence_id IS NOT NULL AND roster_entry_id IS NULL AND transfer_plan_id IS NULL AND transfer_participant_id IS NULL)';
            $transfer = '(occurrence_id IS NULL AND roster_entry_id IS NULL AND transfer_plan_id IS NOT NULL AND transfer_participant_id IS NOT NULL)';
            $governor = '(occurrence_id IS NULL AND roster_entry_id IS NOT NULL AND transfer_plan_id IS NULL AND transfer_participant_id IS NULL)';
            $valid = "({$bear} OR {$transfer} OR {$governor})";
            $updateColumns = 'occurrence_id, roster_entry_id, transfer_plan_id, transfer_participant_id';
        } else {
            $bear = '(occurrence_id IS NOT NULL AND transfer_plan_id IS NULL AND transfer_participant_id IS NULL)';
            $transfer = '(occurrence_id IS NULL AND transfer_plan_id IS NOT NULL AND transfer_participant_id IS NOT NULL)';
            $valid = "({$bear} OR {$transfer})";
            $updateColumns = 'occurrence_id, transfer_plan_id, transfer_participant_id';
        }

        $driver = DB::getDriverName();
        if ($driver === 'sqlite') {
            DB::statement("CREATE TRIGGER game_evidence_scope_insert BEFORE INSERT ON game_evidence WHEN NOT {$valid} BEGIN SELECT RAISE(ABORT, 'invalid game evidence scope'); END");
            DB::statement("CREATE TRIGGER game_evidence_scope_update BEFORE UPDATE OF {$updateColumns} ON game_evidence WHEN NOT {$valid} BEGIN SELECT RAISE(ABORT, 'invalid game evidence scope'); END");

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
