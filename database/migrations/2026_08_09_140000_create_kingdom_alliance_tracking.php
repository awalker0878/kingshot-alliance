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
            $table->timestamps();

            $table->unique(['kingdom_id', 'game_alliance_id']);
            $table->index(['kingdom_id', 'status', 'current_name']);
            $table->index(['kingdom_id', 'current_tag']);
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
        Schema::dropIfExists('kingdom_alliances');
    }
};
