<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('invitations', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('alliance_id')->constrained('alliances')->cascadeOnDelete();
            $table->foreignUlid('player_id')->constrained('players')->cascadeOnDelete();
            $table->string('email');
            $table->string('token_hash', 64)->unique();
            $table->string('status', 32)->default('pending')->index();
            $table->foreignUlid('invited_by_player_id')->constrained('players')->restrictOnDelete();
            $table->timestamp('expires_at');
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->index(['alliance_id', 'player_id', 'status']);
            $table->index(['alliance_id', 'email']);
            $table->index(['alliance_id', 'status', 'expires_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('invitations');
    }
};
