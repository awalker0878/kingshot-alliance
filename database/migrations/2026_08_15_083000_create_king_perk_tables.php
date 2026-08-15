<?php

declare(strict_types=1);

use App\Domain\Events\Enums\EventCapability;
use App\Domain\Events\Enums\EventScope;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

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
            $table->string('status', 24)->default('scheduled')->index();
            $table->foreignUlid('assigned_by_player_id')->constrained('players')->restrictOnDelete();
            $table->timestampTz('confirmed_at')->nullable();
            $table->timestampTz('completed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->index(['plan_id', 'appointment_type', 'starts_at']);
            $table->index(['plan_id', 'assigned_player_id', 'starts_at']);
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

        $scopeId = DB::table('event_type_scopes')
            ->join('event_types', 'event_types.id', '=', 'event_type_scopes.event_type_id')
            ->where('event_types.slug', 'kingdom-of-power')
            ->where('event_type_scopes.scope', EventScope::Kingdom->value)
            ->value('event_type_scopes.id');

        if ($scopeId !== null) {
            DB::table('event_type_capabilities')->updateOrInsert(
                [
                    'event_type_scope_id' => $scopeId,
                    'capability' => EventCapability::KingPerks->value,
                ],
                [
                    'id' => (string) Str::ulid(),
                    'configuration' => json_encode([
                        'appointment_duration_source' => 'king_perks_catalogue',
                        'canonical_timezone' => 'UTC',
                    ], JSON_THROW_ON_ERROR),
                ],
            );
        }
    }

    public function down(): void
    {
        $scopeId = DB::table('event_type_scopes')
            ->join('event_types', 'event_types.id', '=', 'event_type_scopes.event_type_id')
            ->where('event_types.slug', 'kingdom-of-power')
            ->where('event_type_scopes.scope', EventScope::Kingdom->value)
            ->value('event_type_scopes.id');

        if ($scopeId !== null) {
            DB::table('event_type_capabilities')
                ->where('event_type_scope_id', $scopeId)
                ->where('capability', EventCapability::KingPerks->value)
                ->delete();
        }

        Schema::dropIfExists('king_skill_plans');
        Schema::dropIfExists('king_perk_position_blocks');
        Schema::dropIfExists('king_perk_appointments');
        Schema::dropIfExists('king_perk_plans');
    }
};
