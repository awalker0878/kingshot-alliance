<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('territory_workspace_views', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('player_id')->constrained('players')->cascadeOnDelete();
            $table->foreignUlid('kingdom_id')->constrained('kingdoms')->cascadeOnDelete();
            $table->string('map_dataset_id', 120);
            $table->char('map_dataset_checksum', 64);
            $table->unsignedBigInteger('revision')->default(0);
            $table->json('views');
            $table->timestamps();
            $table->unique(['player_id', 'kingdom_id', 'map_dataset_id'], 'territory_workspace_view_scope_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('territory_workspace_views');
    }
};
