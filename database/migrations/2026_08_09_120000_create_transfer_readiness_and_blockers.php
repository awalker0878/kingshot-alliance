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
        Schema::table('transfer_participants', function (Blueprint $table): void {
            $table->string('readiness_state', 24)->default('not_started');
            $table->index(['transfer_plan_id', 'readiness_state', 'withdrawn_at']);
        });

        DB::table('transfer_participants')
            ->whereNotNull('withdrawn_at')
            ->update(['readiness_state' => 'withdrawn']);

        Schema::create('transfer_readiness_transitions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('alliance_id')->constrained('alliances')->cascadeOnDelete();
            $table->foreignUlid('transfer_plan_id')->constrained('transfer_plans')->cascadeOnDelete();
            $table->foreignUlid('transfer_participant_id')->constrained('transfer_participants')->cascadeOnDelete();
            $table->string('from_state', 24)->nullable();
            $table->string('to_state', 24);
            $table->foreignUlid('actor_player_id')->nullable()->constrained('players')->nullOnDelete();
            $table->timestampTz('created_at')->useCurrent();

            $table->index(['transfer_participant_id', 'created_at']);
            $table->index(['alliance_id', 'transfer_plan_id', 'to_state']);
        });

        Schema::create('transfer_blockers', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('alliance_id')->constrained('alliances')->cascadeOnDelete();
            $table->foreignUlid('transfer_plan_id')->constrained('transfer_plans')->cascadeOnDelete();
            $table->foreignUlid('transfer_participant_id')->constrained('transfer_participants')->cascadeOnDelete();
            $table->string('state', 24)->default('active');
            $table->string('summary', 255);
            $table->text('details')->nullable();
            $table->foreignUlid('created_by_player_id')->nullable()->constrained('players')->nullOnDelete();
            $table->foreignUlid('resolved_by_player_id')->nullable()->constrained('players')->nullOnDelete();
            $table->timestampTz('resolved_at')->nullable();
            $table->timestamps();

            $table->index(['transfer_plan_id', 'transfer_participant_id', 'state']);
            $table->index(['alliance_id', 'transfer_plan_id', 'state']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transfer_blockers');
        Schema::dropIfExists('transfer_readiness_transitions');

        Schema::table('transfer_participants', function (Blueprint $table): void {
            $table->dropIndex(['transfer_plan_id', 'readiness_state', 'withdrawn_at']);
            $table->dropColumn('readiness_state');
        });
    }
};
