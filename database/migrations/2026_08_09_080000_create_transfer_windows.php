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
        Schema::create('transfer_windows', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('alliance_id')->constrained('alliances')->cascadeOnDelete();
            $table->string('label', 160);
            $table->timestampTz('pre_transfer_starts_at');
            $table->timestampTz('invitational_starts_at');
            $table->timestampTz('transfer_opens_at');
            $table->timestampTz('ends_at');
            $table->string('source_type', 32);
            $table->string('source_reference', 2048);
            $table->timestampTz('observed_at');
            $table->string('evidence_id', 64)->nullable();
            $table->foreignUlid('recorded_by_player_id')->nullable()->constrained('players')->nullOnDelete();
            $table->timestamps();
            $table->index(['alliance_id', 'pre_transfer_starts_at', 'ends_at']);
            $table->unique(['alliance_id', 'label']);
        });

        Schema::create('transfer_groups', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('alliance_id')->constrained('alliances')->cascadeOnDelete();
            $table->foreignUlid('transfer_window_id')->constrained('transfer_windows')->cascadeOnDelete();
            $table->string('official_label', 160);
            $table->unsignedInteger('revision')->default(1);
            $table->string('source_type', 32);
            $table->string('source_reference', 2048);
            $table->timestampTz('observed_at');
            $table->string('evidence_id', 64)->nullable();
            $table->timestampTz('superseded_at')->nullable();
            $table->foreignUlid('recorded_by_player_id')->nullable()->constrained('players')->nullOnDelete();
            $table->timestamps();
            $table->index(['alliance_id', 'transfer_window_id', 'superseded_at']);
        });

        DB::statement('CREATE UNIQUE INDEX transfer_groups_one_current_label_per_window ON transfer_groups (transfer_window_id, lower(official_label)) WHERE superseded_at IS NULL');

        Schema::create('transfer_group_kingdoms', function (Blueprint $table): void {
            $table->foreignUlid('transfer_group_id')->constrained('transfer_groups')->cascadeOnDelete();
            $table->foreignUlid('kingdom_id')->constrained('kingdoms')->restrictOnDelete();
            $table->primary(['transfer_group_id', 'kingdom_id']);
            $table->index('kingdom_id');
        });

        Schema::create('transfer_kingdom_condition_observations', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('alliance_id')->constrained('alliances')->cascadeOnDelete();
            $table->foreignUlid('transfer_window_id')->constrained('transfer_windows')->cascadeOnDelete();
            $table->foreignUlid('kingdom_id')->constrained('kingdoms')->restrictOnDelete();
            $table->unsignedBigInteger('power_cap')->nullable();
            $table->string('classification', 24)->default('unknown');
            $table->string('source_type', 32);
            $table->string('source_reference', 2048);
            $table->timestampTz('observed_at');
            $table->string('evidence_id', 64)->nullable();
            $table->boolean('is_correction')->default(false);
            $table->char('fingerprint', 64)->unique();
            $table->foreignUlid('recorded_by_player_id')->nullable()->constrained('players')->nullOnDelete();
            $table->timestamps();
            $table->index(['alliance_id', 'transfer_window_id', 'kingdom_id', 'observed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transfer_kingdom_condition_observations');
        Schema::dropIfExists('transfer_group_kingdoms');
        Schema::dropIfExists('transfer_groups');
        Schema::dropIfExists('transfer_windows');
    }
};
