<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('territory_recovery_drafts', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('territory_plan_id')->constrained('territory_plans')->cascadeOnDelete();
            $table->foreignUlid('actor_player_id')->constrained('players')->cascadeOnDelete();
            $table->unsignedInteger('base_revision');
            $table->string('map_dataset_id', 120);
            $table->char('map_dataset_checksum', 64);
            $table->jsonb('document');
            $table->char('document_checksum', 64);
            $table->timestampTz('expires_at');
            $table->timestampsTz();

            $table->unique(['territory_plan_id', 'actor_player_id'], 'territory_recovery_actor_plan_unique');
            $table->index(['expires_at', 'id'], 'territory_recovery_expiry_page');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('territory_recovery_drafts');
    }
};
