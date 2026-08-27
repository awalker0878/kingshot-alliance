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
        $this->dropEvidenceScopeConstraint();

        Schema::table('game_evidence', function (Blueprint $table): void {
            $table->foreignUlid('kingdom_id')
                ->nullable()
                ->after('alliance_id')
                ->constrained('kingdoms')
                ->restrictOnDelete();
            $table->string('map_dataset_id', 120)->nullable()->after('transfer_participant_id');
            $table->char('map_dataset_checksum', 64)->nullable()->after('map_dataset_id');
            $table->unique(
                ['alliance_id', 'kingdom_id', 'expected_kind', 'sha256'],
                'game_evidence_territory_exact_unique',
            );
            $table->index(
                ['alliance_id', 'kingdom_id', 'lifecycle_status', 'created_at'],
                'game_evidence_territory_status_idx',
            );
            $table->index(
                ['alliance_id', 'kingdom_id', 'perceptual_hash'],
                'game_evidence_territory_visual_idx',
            );
        });

        $this->addEvidenceScopeConstraint(true);

        Schema::create('evidence_spatial_reviews', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('evidence_id')->constrained('game_evidence')->cascadeOnDelete();
            $table->foreignUlid('alliance_id')->constrained('alliances')->restrictOnDelete();
            $table->foreignUlid('kingdom_id')->constrained('kingdoms')->restrictOnDelete();
            $table->string('schema_version', 96);
            $table->unsignedInteger('revision_number');
            $table->string('status', 32);
            $table->timestampTz('captured_at');
            $table->string('coverage_kind', 40);
            $table->string('completeness', 24);
            $table->json('coverage_bounds')->nullable();
            $table->string('map_dataset_id', 120);
            $table->char('map_dataset_checksum', 64);
            $table->json('payload');
            $table->char('semantic_fingerprint', 64);
            $table->ulid('semantic_duplicate_review_id')->nullable();
            $table->text('duplicate_resolution')->nullable();
            $table->foreignUlid('reviewed_by_player_id')->constrained('players')->restrictOnDelete();
            $table->foreignUlid('resolved_by_player_id')->nullable()->constrained('players')->restrictOnDelete();
            $table->timestamp('reviewed_at');
            $table->timestamp('resolved_at')->nullable();
            $table->timestamps();

            $table->unique(['evidence_id', 'revision_number'], 'evidence_spatial_review_revision_unique');
            $table->index(
                ['alliance_id', 'kingdom_id', 'semantic_fingerprint', 'status'],
                'evidence_spatial_review_semantic_idx',
            );
        });

        Schema::create('evidence_spatial_commit_attempts', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('evidence_id')->constrained('game_evidence')->cascadeOnDelete();
            $table->foreignUlid('spatial_review_id')->constrained('evidence_spatial_reviews')->restrictOnDelete();
            $table->foreignUlid('alliance_id')->constrained('alliances')->restrictOnDelete();
            $table->foreignUlid('kingdom_id')->constrained('kingdoms')->restrictOnDelete();
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
            $table->index(['spatial_review_id', 'idempotency_key', 'status'], 'evidence_spatial_commit_review_idx');
            $table->index(['alliance_id', 'kingdom_id', 'status', 'created_at'], 'evidence_spatial_commit_scope_idx');
        });

        Schema::create('spatial_observations', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('alliance_id')->constrained('alliances')->cascadeOnDelete();
            $table->foreignUlid('kingdom_id')->constrained('kingdoms')->restrictOnDelete();
            $table->timestampTz('captured_at');
            $table->string('coverage_kind', 40);
            $table->string('completeness', 24);
            $table->json('coverage_bounds')->nullable();
            $table->string('map_dataset_id', 120);
            $table->char('map_dataset_checksum', 64);
            $table->string('source', 32)->default('screenshot_evidence');
            $table->string('source_evidence_id', 64)->nullable();
            $table->string('source_review_id', 64)->nullable();
            $table->char('destination_idempotency_key', 64)->unique();
            $table->foreignUlid('accepted_by_player_id')->constrained('players')->restrictOnDelete();
            $table->timestamp('accepted_at');
            $table->foreignUlid('corrects_observation_id')->nullable()->constrained('spatial_observations')->restrictOnDelete();
            $table->timestamp('invalidated_at')->nullable();
            $table->foreignUlid('invalidated_by_player_id')->nullable()->constrained('players')->restrictOnDelete();
            $table->text('invalidation_reason')->nullable();
            $table->timestamps();
            $table->index(['alliance_id', 'kingdom_id', 'captured_at'], 'spatial_observation_scope_time_idx');
            $table->index(['alliance_id', 'kingdom_id', 'invalidated_at', 'captured_at'], 'spatial_observation_current_idx');
        });

        Schema::create('spatial_observed_objects', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('spatial_observation_id')->constrained('spatial_observations')->cascadeOnDelete();
            $table->string('object_key', 120);
            $table->string('object_type', 40);
            $table->integer('coordinate_x');
            $table->integer('coordinate_y');
            $table->foreignUlid('player_id')->nullable()->constrained('players')->restrictOnDelete();
            $table->string('plan_local_identity', 191)->nullable();
            $table->string('observed_label', 191)->nullable();
            $table->string('identity_state', 32);
            $table->decimal('confidence', 5, 4)->nullable();
            $table->json('source_metadata')->nullable();
            $table->timestamps();
            $table->unique(['spatial_observation_id', 'object_key'], 'spatial_observed_object_key_unique');
            $table->index(['spatial_observation_id', 'object_type'], 'spatial_observed_object_type_idx');
            $table->index(['player_id', 'spatial_observation_id'], 'spatial_observed_object_player_idx');
        });

        Schema::create('spatial_observation_evidence_receipts', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('alliance_id')->constrained('alliances')->cascadeOnDelete();
            $table->foreignUlid('kingdom_id')->constrained('kingdoms')->restrictOnDelete();
            $table->foreignUlid('observation_id')->constrained('spatial_observations')->restrictOnDelete();
            $table->string('evidence_id', 64);
            $table->string('review_id', 64);
            $table->string('schema_version', 96);
            $table->string('map_dataset_id', 120);
            $table->char('map_dataset_checksum', 64);
            $table->char('idempotency_key', 64)->unique();
            $table->foreignUlid('accepted_by_player_id')->constrained('players')->restrictOnDelete();
            $table->timestamp('accepted_at');
            $table->timestamps();
            $table->index(['alliance_id', 'kingdom_id', 'created_at'], 'spatial_receipt_scope_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('spatial_observation_evidence_receipts');
        Schema::dropIfExists('spatial_observed_objects');
        Schema::dropIfExists('spatial_observations');
        Schema::dropIfExists('evidence_spatial_commit_attempts');
        Schema::dropIfExists('evidence_spatial_reviews');

        $this->dropEvidenceScopeConstraint();
        Schema::table('game_evidence', function (Blueprint $table): void {
            $table->dropUnique('game_evidence_territory_exact_unique');
            $table->dropIndex('game_evidence_territory_status_idx');
            $table->dropIndex('game_evidence_territory_visual_idx');
            $table->dropColumn(['map_dataset_id', 'map_dataset_checksum']);
            $table->dropConstrainedForeignId('kingdom_id');
        });
        $this->addEvidenceScopeConstraint(false);
    }

    private function addEvidenceScopeConstraint(bool $includeTerritory): void
    {
        $bear = '(occurrence_id IS NOT NULL AND roster_entry_id IS NULL AND transfer_plan_id IS NULL AND transfer_participant_id IS NULL'.($includeTerritory ? ' AND kingdom_id IS NULL AND map_dataset_id IS NULL AND map_dataset_checksum IS NULL' : '').')';
        $transfer = '(occurrence_id IS NULL AND roster_entry_id IS NULL AND transfer_plan_id IS NOT NULL AND transfer_participant_id IS NOT NULL'.($includeTerritory ? ' AND kingdom_id IS NULL AND map_dataset_id IS NULL AND map_dataset_checksum IS NULL' : '').')';
        $governor = '(occurrence_id IS NULL AND roster_entry_id IS NOT NULL AND transfer_plan_id IS NULL AND transfer_participant_id IS NULL'.($includeTerritory ? ' AND kingdom_id IS NULL AND map_dataset_id IS NULL AND map_dataset_checksum IS NULL' : '').')';
        $valid = "({$bear} OR {$transfer} OR {$governor})";
        $updateColumns = 'occurrence_id, roster_entry_id, transfer_plan_id, transfer_participant_id';
        if ($includeTerritory) {
            $territory = '(occurrence_id IS NULL AND roster_entry_id IS NULL AND transfer_plan_id IS NULL AND transfer_participant_id IS NULL AND kingdom_id IS NOT NULL AND map_dataset_id IS NOT NULL AND map_dataset_checksum IS NOT NULL)';
            $valid = "({$bear} OR {$transfer} OR {$governor} OR {$territory})";
            $updateColumns .= ', kingdom_id, map_dataset_id, map_dataset_checksum';
        }

        $driver = DB::getDriverName();
        if ($driver === 'sqlite') {
            DB::statement("CREATE TRIGGER game_evidence_scope_insert BEFORE INSERT ON game_evidence WHEN NOT {$valid} BEGIN SELECT RAISE(ABORT, 'invalid game evidence scope'); END");
            DB::statement("CREATE TRIGGER game_evidence_scope_update BEFORE UPDATE OF {$updateColumns} ON game_evidence WHEN NOT {$valid} BEGIN SELECT RAISE(ABORT, 'invalid game evidence scope'); END");

            return;
        }

        DB::statement("ALTER TABLE game_evidence ADD CONSTRAINT game_evidence_scope_check CHECK ({$valid})");
    }

    private function dropEvidenceScopeConstraint(): void
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
