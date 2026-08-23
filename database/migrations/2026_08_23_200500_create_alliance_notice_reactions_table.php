<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('alliance_notice_reactions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->ulid('alliance_id');
            $table->ulid('content_item_id');
            $table->foreignUlid('player_id')->constrained('players')->restrictOnDelete();
            $table->string('reaction', 16);
            $table->timestamps();

            $table->foreign('alliance_id')->references('id')->on('alliances')->cascadeOnDelete();
            $table->foreign(['content_item_id', 'alliance_id'])
                ->references(['id', 'alliance_id'])
                ->on('content_items')
                ->cascadeOnDelete();

            $table->unique(['content_item_id', 'player_id']);
            $table->index(['alliance_id', 'content_item_id', 'reaction']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('alliance_notice_reactions');
    }
};
