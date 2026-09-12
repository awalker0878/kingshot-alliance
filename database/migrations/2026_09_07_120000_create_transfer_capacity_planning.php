<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transfer_kingdom_capacity_observations', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('alliance_id')->constrained('alliances')->cascadeOnDelete();
            $table->foreignUlid('transfer_window_id')->constrained('transfer_windows')->cascadeOnDelete();
            $table->foreignUlid('kingdom_id')->constrained('kingdoms')->restrictOnDelete();
            $table->unsignedSmallInteger('ordinary_invites_used')->nullable();
            $table->unsignedSmallInteger('transfer_opens_used')->nullable();
            $table->unsignedSmallInteger('special_invites_available')->nullable();
            $table->string('source_type', 32);
            $table->string('source_reference', 2048);
            $table->timestampTz('observed_at');
            $table->string('evidence_id', 64)->nullable();
            $table->boolean('is_correction')->default(false);
            $table->char('fingerprint', 64)->unique();
            $table->foreignUlid('recorded_by_player_id')->nullable()->constrained('players')->nullOnDelete();
            $table->timestamps();
            $table->index(['alliance_id', 'transfer_window_id', 'kingdom_id', 'observed_at', 'id'], 'transfer_capacity_current_fact_idx');
            $table->index(['alliance_id', 'transfer_window_id', 'id'], 'transfer_capacity_catalogue_cursor');
        });

        Schema::create('transfer_capacity_reservations', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('alliance_id')->constrained('alliances')->cascadeOnDelete();
            $table->foreignUlid('transfer_window_id')->constrained('transfer_windows')->cascadeOnDelete();
            $table->foreignUlid('transfer_plan_id')->constrained('transfer_plans')->cascadeOnDelete();
            $table->foreignUlid('transfer_participant_id')->constrained('transfer_participants')->cascadeOnDelete();
            $table->foreignUlid('target_kingdom_id')->constrained('kingdoms')->restrictOnDelete();
            $table->string('bucket', 32);
            $table->string('state', 24)->default('planned');
            $table->timestampTz('reserved_at')->nullable();
            $table->timestampTz('released_at')->nullable();
            $table->text('notes')->nullable();
            $table->foreignUlid('created_by_player_id')->constrained('players')->restrictOnDelete();
            $table->timestamps();
            $table->unique('transfer_participant_id');
            $table->index(['alliance_id', 'transfer_window_id', 'target_kingdom_id', 'bucket', 'state'], 'transfer_capacity_reservation_projection_idx');
        });

        Schema::create('transfer_invitation_allocations', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('alliance_id')->constrained('alliances')->cascadeOnDelete();
            $table->foreignUlid('transfer_window_id')->constrained('transfer_windows')->cascadeOnDelete();
            $table->foreignUlid('transfer_plan_id')->constrained('transfer_plans')->cascadeOnDelete();
            $table->foreignUlid('transfer_participant_id')->constrained('transfer_participants')->cascadeOnDelete();
            $table->foreignUlid('target_kingdom_id')->constrained('kingdoms')->restrictOnDelete();
            $table->string('kind', 24);
            $table->string('state', 24)->default('requested');
            $table->text('notes')->nullable();
            $table->foreignUlid('created_by_player_id')->constrained('players')->restrictOnDelete();
            $table->timestamps();
            $table->unique('transfer_participant_id');
            $table->index(['alliance_id', 'transfer_window_id', 'target_kingdom_id', 'kind', 'state'], 'transfer_invitation_allocation_projection_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transfer_invitation_allocations');
        Schema::dropIfExists('transfer_capacity_reservations');
        Schema::dropIfExists('transfer_kingdom_capacity_observations');
    }
};
