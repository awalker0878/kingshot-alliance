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
        Schema::create('kingdom_alliances', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('kingdom_id')->constrained('kingdoms')->restrictOnDelete();
            $table->string('game_alliance_id', 100)->nullable();
            $table->string('current_name', 160);
            $table->string('current_tag', 32)->nullable();
            $table->string('status', 24)->default('active');
            $table->ulid('canonical_kingdom_alliance_id')->nullable();
            $table->timestamps();

            $table->unique(['kingdom_id', 'game_alliance_id']);
            $table->index(['kingdom_id', 'status', 'current_name']);
            $table->index(['kingdom_id', 'current_tag']);
            $table->index('canonical_kingdom_alliance_id');
            $table->foreign('canonical_kingdom_alliance_id')
                ->references('id')
                ->on('kingdom_alliances')
                ->restrictOnDelete();
        });

        Schema::create('kingdom_alliance_identity_history', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('kingdom_alliance_id')->constrained('kingdom_alliances')->restrictOnDelete();
            $table->string('name', 160);
            $table->string('tag', 32)->nullable();
            $table->string('game_alliance_id', 100)->nullable();
            $table->timestampTz('valid_from');
            $table->timestampTz('valid_to')->nullable();
            $table->string('source_type', 40)->default('manual');
            $table->string('source_reference', 191)->nullable();
            $table->timestampTz('observed_at')->nullable();
            $table->unsignedSmallInteger('confidence_basis_points')->nullable();
            $table->text('reason')->nullable();
            $table->timestamps();

            $table->index(['kingdom_alliance_id', 'valid_from']);
            $table->index(['game_alliance_id', 'valid_from']);
            $table->index(['source_type', 'observed_at']);
        });

        DB::statement(
            'CREATE UNIQUE INDEX kingdom_alliance_identity_history_one_current ON kingdom_alliance_identity_history (kingdom_alliance_id) WHERE valid_to IS NULL'
        );

        Schema::create('kingdom_alliance_reconciliations', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('kingdom_id')->constrained('kingdoms')->restrictOnDelete();
            $table->foreignUlid('canonical_kingdom_alliance_id')->constrained('kingdom_alliances')->restrictOnDelete();
            $table->foreignUlid('duplicate_kingdom_alliance_id')->constrained('kingdom_alliances')->restrictOnDelete();
            $table->text('reason');
            $table->string('source_type', 40)->default('system_reconciliation');
            $table->string('source_reference', 191)->nullable();
            $table->unsignedSmallInteger('confidence_basis_points')->nullable();
            $table->timestampTz('reconciled_at');
            $table->timestamps();

            $table->unique('duplicate_kingdom_alliance_id');
            $table->index(['kingdom_id', 'reconciled_at']);
            $table->index(['canonical_kingdom_alliance_id', 'reconciled_at']);
        });

        Schema::create('tracked_kingdom_alliances', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('alliance_id')->constrained('alliances')->cascadeOnDelete();
            $table->foreignUlid('kingdom_alliance_id')->constrained('kingdom_alliances')->restrictOnDelete();
            $table->foreignUlid('kingdom_id')->constrained('kingdoms')->restrictOnDelete();
            $table->string('state', 24)->default('active');
            $table->text('manager_notes')->nullable();
            $table->timestampTz('archived_at')->nullable();
            $table->timestamps();

            $table->index(['alliance_id', 'state', 'created_at']);
            $table->index(['alliance_id', 'kingdom_id', 'state']);
            $table->index(['alliance_id', 'kingdom_alliance_id']);
        });

        DB::statement(
            "CREATE UNIQUE INDEX tracked_kingdom_alliances_one_active_per_reference ON tracked_kingdom_alliances (alliance_id, kingdom_alliance_id) WHERE state = 'active'"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('tracked_kingdom_alliances');
        Schema::dropIfExists('kingdom_alliance_reconciliations');
        Schema::dropIfExists('kingdom_alliance_identity_history');
        Schema::dropIfExists('kingdom_alliances');
    }
};
