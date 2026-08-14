<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('player_snapshots', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('alliance_id')->constrained('alliances')->cascadeOnDelete();
            $table->foreignUlid('roster_entry_id')->constrained('alliance_roster_entries')->cascadeOnDelete();
            $table->foreignUlid('player_id')->constrained('players')->restrictOnDelete();
            $table->foreignUlid('actor_player_id')->nullable()->constrained('players')->nullOnDelete();
            $table->string('observed_name', 160);
            $table->bigInteger('power');
            $table->string('progression_level', 64)->nullable();
            $table->string('observed_alliance_tag', 32)->nullable();
            $table->timestampTz('captured_at');
            $table->string('source', 24)->default('manual');
            $table->string('idempotency_key', 64);
            $table->timestamps();

            $table->unique(['alliance_id', 'idempotency_key']);
            $table->index(['alliance_id', 'roster_entry_id', 'captured_at']);
            $table->index(['alliance_id', 'player_id', 'captured_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('player_snapshots');
    }
};
