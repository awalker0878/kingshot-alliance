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
        Schema::create('transfer_participants', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('alliance_id')->constrained('alliances')->cascadeOnDelete();
            $table->foreignUlid('transfer_plan_id')->constrained('transfer_plans')->cascadeOnDelete();
            $table->string('direction', 24)->index();
            $table->foreignUlid('roster_entry_id')->nullable()->constrained('alliance_roster_entries')->nullOnDelete();
            $table->foreignUlid('player_id')->constrained('players')->restrictOnDelete();
            $table->string('observed_name', 160);
            $table->string('game_player_id', 100)->nullable();
            $table->foreignUlid('source_kingdom_id')->nullable()->constrained('kingdoms')->restrictOnDelete();
            $table->foreignUlid('destination_kingdom_id')->nullable()->constrained('kingdoms')->restrictOnDelete();
            $table->text('manager_notes')->nullable();
            $table->timestampTz('withdrawn_at')->nullable();
            $table->timestamps();

            $table->index(['alliance_id', 'transfer_plan_id', 'direction', 'withdrawn_at']);
            $table->index(['transfer_plan_id', 'destination_kingdom_id']);
            $table->index(['transfer_plan_id', 'source_kingdom_id']);
        });

        DB::statement(
            'CREATE UNIQUE INDEX transfer_participants_one_active_player '.
            'ON transfer_participants (transfer_plan_id, player_id) '.
            'WHERE withdrawn_at IS NULL'
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('transfer_participants');
    }
};
