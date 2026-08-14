<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('players', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUlid('current_kingdom_id')->constrained('kingdoms')->restrictOnDelete();
            $table->string('game_player_id', 100)->nullable()->unique();
            $table->string('current_name', 160);
            $table->timestamps();

            $table->index(['user_id', 'current_name']);
            $table->index(['current_kingdom_id', 'current_name']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('players');
    }
};
