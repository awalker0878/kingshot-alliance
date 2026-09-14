<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('territory_save_receipts', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('territory_plan_id')->constrained('territory_plans')->cascadeOnDelete();
            $table->foreignUlid('actor_player_id')->constrained('players')->cascadeOnDelete();
            $table->uuid('mutation_id');
            $table->char('request_checksum', 64);
            $table->unsignedBigInteger('accepted_revision');
            $table->char('layout_checksum', 64);
            $table->json('snapshot');
            $table->json('required_layer_keys');
            $table->boolean('requires_manage');
            $table->timestamp('expires_at')->index();
            $table->timestamps();
            $table->unique(['territory_plan_id', 'actor_player_id', 'mutation_id'], 'territory_save_receipt_identity');
            $table->index(['territory_plan_id', 'actor_player_id', 'accepted_revision'], 'territory_save_receipt_history');
        });
    }

    // Production rollback disables new writes; this reversal is for disposable test schemas only.
    public function down(): void
    {
        Schema::dropIfExists('territory_save_receipts');
    }
};
