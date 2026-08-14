<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_results', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('occurrence_id')->unique()->constrained('event_occurrences')->cascadeOnDelete();
            $table->string('outcome', 80)->nullable();
            $table->unsignedBigInteger('score')->nullable();
            $table->unsignedBigInteger('opponent_score')->nullable();
            $table->unsignedInteger('rank')->nullable();
            $table->json('metrics')->nullable();
            $table->text('notes')->nullable();
            $table->foreignUlid('recorded_by_player_id')->nullable()->constrained('players')->restrictOnDelete();
            $table->timestamp('recorded_at');
            $table->timestamps();
        });

        Schema::create('event_player_results', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('occurrence_id')->constrained('event_occurrences')->cascadeOnDelete();
            $table->foreignUlid('player_id')->constrained('players')->restrictOnDelete();
            $table->string('outcome', 80)->nullable();
            $table->unsignedBigInteger('score')->nullable();
            $table->unsignedInteger('rank')->nullable();
            $table->json('metrics')->nullable();
            $table->text('notes')->nullable();
            $table->foreignUlid('recorded_by_player_id')->nullable()->constrained('players')->restrictOnDelete();
            $table->timestamp('recorded_at');
            $table->timestamps();

            $table->unique(['occurrence_id', 'player_id']);
            $table->index(['player_id', 'recorded_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('event_player_results');
        Schema::dropIfExists('event_results');
    }
};
