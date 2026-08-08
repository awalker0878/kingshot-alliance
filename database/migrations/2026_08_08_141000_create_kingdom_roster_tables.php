<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kingdom_players', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('kingdom_id')->constrained('kingdoms')->restrictOnDelete();
            $table->string('game_player_id', 100)->nullable();
            $table->string('current_name', 160);
            $table->timestamps();

            $table->unique(['kingdom_id', 'game_player_id']);
            $table->index(['kingdom_id', 'current_name']);
        });

        Schema::create('alliance_roster_entries', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('alliance_id')->constrained('alliances')->cascadeOnDelete();
            $table->foreignUlid('kingdom_player_id')->constrained('kingdom_players')->restrictOnDelete();
            $table->foreignUlid('membership_id')->nullable()->constrained('alliance_memberships')->nullOnDelete();
            $table->string('observed_name', 160);
            $table->string('game_role', 64)->nullable();
            $table->string('state', 24)->default('active')->index();
            $table->date('joined_at')->nullable();
            $table->timestampTz('left_at')->nullable();
            $table->text('manager_notes')->nullable();
            $table->timestampTz('last_observed_at')->nullable();
            $table->string('source', 24)->default('manual');
            $table->timestamps();

            $table->unique(['alliance_id', 'kingdom_player_id']);
            $table->unique(['alliance_id', 'membership_id']);
            $table->index(['alliance_id', 'state', 'observed_name']);
            $table->index(['alliance_id', 'last_observed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alliance_roster_entries');
        Schema::dropIfExists('kingdom_players');
    }
};
