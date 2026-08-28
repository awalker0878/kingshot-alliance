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
        Schema::create('king_perk_plans', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('event_id')->constrained('events')->cascadeOnDelete();
            $table->foreignUlid('occurrence_id')->constrained('event_occurrences')->cascadeOnDelete();
            $table->foreignUlid('kingdom_id')->constrained('kingdoms')->cascadeOnDelete();
            $table->string('status', 24)->default('draft')->index();
            $table->timestampTz('window_starts_at');
            $table->timestampTz('window_ends_at');
            $table->foreignUlid('created_by_player_id')->constrained('players')->restrictOnDelete();
            $table->foreignUlid('published_by_player_id')->nullable()->constrained('players')->nullOnDelete();
            $table->timestampTz('published_at')->nullable();
            $table->timestamps();

            $table->unique('occurrence_id');
            $table->index(['kingdom_id', 'window_starts_at', 'window_ends_at']);
        });

        Schema::create('king_perk_appointments', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('plan_id')->constrained('king_perk_plans')->cascadeOnDelete();
            $table->string('appointment_type', 48);
            $table->foreignUlid('assigned_player_id')->constrained('players')->restrictOnDelete();
            $table->timestampTz('starts_at');
            $table->timestampTz('ends_at');
            $table->timestampTz('player_cooldown_ends_at');
            $table->string('status', 24)->default('scheduled')->index();
            $table->foreignUlid('assigned_by_player_id')->constrained('players')->restrictOnDelete();
            $table->timestampTz('confirmed_at')->nullable();
            $table->timestampTz('actual_started_at')->nullable();
            $table->timestampTz('actual_ended_at')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['plan_id', 'appointment_type', 'starts_at']);
            $table->index(['plan_id', 'assigned_player_id', 'starts_at']);
            $table->index(['plan_id', 'assigned_player_id', 'player_cooldown_ends_at'], 'king_perk_player_cooldown_idx');
        });

        Schema::create('king_perk_position_blocks', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('plan_id')->constrained('king_perk_plans')->cascadeOnDelete();
            $table->string('appointment_type', 48);
            $table->timestampTz('starts_at');
            $table->timestampTz('ends_at');
            $table->string('reason', 48);
            $table->foreignUlid('source_appointment_id')->nullable()->constrained('king_perk_appointments')->nullOnDelete();
            $table->foreignUlid('recorded_by_player_id')->constrained('players')->restrictOnDelete();
            $table->timestamps();

            $table->index(['plan_id', 'appointment_type', 'starts_at', 'ends_at'], 'king_perk_blocks_window_idx');
        });

        Schema::create('king_perk_requests', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('plan_id')->constrained('king_perk_plans')->cascadeOnDelete();
            $table->foreignUlid('player_id')->constrained('players')->restrictOnDelete();
            $table->string('push_category', 32);
            $table->string('preferred_appointment_type', 48)->nullable();
            $table->timestampTz('availability_starts_at');
            $table->timestampTz('availability_ends_at');
            $table->unsignedInteger('planned_speedup_minutes')->nullable();
            $table->unsignedBigInteger('planned_resource_amount')->nullable();
            $table->text('notes')->nullable();
            $table->string('status', 24)->default('submitted')->index();
            $table->foreignUlid('scheduled_appointment_id')->nullable()->constrained('king_perk_appointments')->nullOnDelete();
            $table->foreignUlid('reviewed_by_player_id')->nullable()->constrained('players')->nullOnDelete();
            $table->timestampTz('reviewed_at')->nullable();
            $table->timestamps();

            $table->index(['plan_id', 'status', 'push_category'], 'king_perk_requests_queue_idx');
            $table->index(['plan_id', 'player_id', 'availability_starts_at'], 'king_perk_requests_player_idx');
        });

        Schema::create('king_skill_plans', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('plan_id')->constrained('king_perk_plans')->cascadeOnDelete();
            $table->string('skill_key', 48);
            $table->timestampTz('planned_activation_at');
            $table->unsignedInteger('effect_duration_minutes');
            $table->timestampTz('planned_ends_at');
            $table->string('status', 32)->default('planned')->index();
            $table->foreignUlid('planned_by_player_id')->constrained('players')->restrictOnDelete();
            $table->foreignUlid('scheduled_by_player_id')->nullable()->constrained('players')->nullOnDelete();
            $table->foreignUlid('activated_by_player_id')->nullable()->constrained('players')->nullOnDelete();
            $table->timestampTz('scheduled_in_game_at')->nullable();
            $table->timestampTz('activated_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['plan_id', 'skill_key', 'planned_activation_at'], 'king_skill_plan_time_unique');
            $table->index(['plan_id', 'planned_activation_at', 'planned_ends_at']);
        });

        $this->createTemporalGuards();
    }

    private function createTemporalGuards(): void
    {
        $driver = DB::connection()->getDriverName();
        $active = "('scheduled','confirmed','active','completed')";

        if ($driver === 'pgsql') {
            DB::statement('CREATE EXTENSION IF NOT EXISTS btree_gist');
            DB::statement('ALTER TABLE king_perk_appointments ADD CONSTRAINT king_perk_appointments_time_check CHECK (ends_at > starts_at AND player_cooldown_ends_at >= ends_at)');
            DB::statement('ALTER TABLE king_perk_requests ADD CONSTRAINT king_perk_requests_time_check CHECK (availability_ends_at > availability_starts_at)');
            DB::statement("ALTER TABLE king_perk_appointments ADD CONSTRAINT king_perk_position_no_overlap EXCLUDE USING gist (plan_id WITH =, appointment_type WITH =, tstzrange(starts_at, ends_at, '[)') WITH &&) WHERE (status IN {$active})");
            DB::statement("ALTER TABLE king_perk_appointments ADD CONSTRAINT king_perk_player_no_overlap EXCLUDE USING gist (plan_id WITH =, assigned_player_id WITH =, tstzrange(starts_at, player_cooldown_ends_at, '[)') WITH &&) WHERE (status IN {$active})");

            return;
        }

        if ($driver === 'sqlite') {
            DB::statement("CREATE TRIGGER king_perk_appointments_time_insert BEFORE INSERT ON king_perk_appointments WHEN NEW.ends_at <= NEW.starts_at OR NEW.player_cooldown_ends_at IS NULL OR NEW.player_cooldown_ends_at < NEW.ends_at BEGIN SELECT RAISE(ABORT, 'invalid king perk appointment window'); END");
            DB::statement("CREATE TRIGGER king_perk_appointments_time_update BEFORE UPDATE OF starts_at, ends_at, player_cooldown_ends_at ON king_perk_appointments WHEN NEW.ends_at <= NEW.starts_at OR NEW.player_cooldown_ends_at IS NULL OR NEW.player_cooldown_ends_at < NEW.ends_at BEGIN SELECT RAISE(ABORT, 'invalid king perk appointment window'); END");
            DB::statement("CREATE TRIGGER king_perk_requests_time_insert BEFORE INSERT ON king_perk_requests WHEN NEW.availability_ends_at <= NEW.availability_starts_at BEGIN SELECT RAISE(ABORT, 'invalid king perk availability window'); END");
            DB::statement("CREATE TRIGGER king_perk_requests_time_update BEFORE UPDATE OF availability_starts_at, availability_ends_at ON king_perk_requests WHEN NEW.availability_ends_at <= NEW.availability_starts_at BEGIN SELECT RAISE(ABORT, 'invalid king perk availability window'); END");
            DB::statement("CREATE TRIGGER king_perk_position_overlap_insert BEFORE INSERT ON king_perk_appointments WHEN NEW.status IN {$active} AND EXISTS (SELECT 1 FROM king_perk_appointments a WHERE a.plan_id = NEW.plan_id AND a.appointment_type = NEW.appointment_type AND a.status IN {$active} AND a.starts_at < NEW.ends_at AND a.ends_at > NEW.starts_at) BEGIN SELECT RAISE(ABORT, 'overlapping king perk position'); END");
            DB::statement("CREATE TRIGGER king_perk_position_overlap_update BEFORE UPDATE OF plan_id, appointment_type, starts_at, ends_at, status ON king_perk_appointments WHEN NEW.status IN {$active} AND EXISTS (SELECT 1 FROM king_perk_appointments a WHERE a.id <> NEW.id AND a.plan_id = NEW.plan_id AND a.appointment_type = NEW.appointment_type AND a.status IN {$active} AND a.starts_at < NEW.ends_at AND a.ends_at > NEW.starts_at) BEGIN SELECT RAISE(ABORT, 'overlapping king perk position'); END");
            DB::statement("CREATE TRIGGER king_perk_player_overlap_insert BEFORE INSERT ON king_perk_appointments WHEN NEW.status IN {$active} AND EXISTS (SELECT 1 FROM king_perk_appointments a WHERE a.plan_id = NEW.plan_id AND a.assigned_player_id = NEW.assigned_player_id AND a.status IN {$active} AND a.starts_at < NEW.player_cooldown_ends_at AND a.player_cooldown_ends_at > NEW.starts_at) BEGIN SELECT RAISE(ABORT, 'overlapping king perk player cooldown'); END");
            DB::statement("CREATE TRIGGER king_perk_player_overlap_update BEFORE UPDATE OF plan_id, assigned_player_id, starts_at, ends_at, player_cooldown_ends_at, status ON king_perk_appointments WHEN NEW.status IN {$active} AND EXISTS (SELECT 1 FROM king_perk_appointments a WHERE a.id <> NEW.id AND a.plan_id = NEW.plan_id AND a.assigned_player_id = NEW.assigned_player_id AND a.status IN {$active} AND a.starts_at < NEW.player_cooldown_ends_at AND a.player_cooldown_ends_at > NEW.starts_at) BEGIN SELECT RAISE(ABORT, 'overlapping king perk player cooldown'); END");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('king_skill_plans');
        Schema::dropIfExists('king_perk_requests');
        Schema::dropIfExists('king_perk_position_blocks');
        Schema::dropIfExists('king_perk_appointments');
        Schema::dropIfExists('king_perk_plans');
    }
};
