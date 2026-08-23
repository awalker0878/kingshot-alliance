<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transfer_observations', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('alliance_id')->constrained('alliances')->cascadeOnDelete();
            $table->foreignUlid('transfer_window_id')->constrained('transfer_windows')->cascadeOnDelete();
            $table->foreignUlid('transfer_plan_id')->constrained('transfer_plans')->cascadeOnDelete();
            $table->foreignUlid('transfer_participant_id')->constrained('transfer_participants')->cascadeOnDelete();
            $table->foreignUlid('target_kingdom_id')->nullable()->constrained('kingdoms')->restrictOnDelete();
            $table->string('kind', 48);
            $table->unsignedBigInteger('numeric_value')->nullable();
            $table->string('text_value', 255)->nullable();
            $table->boolean('boolean_value')->nullable();
            $table->text('details')->nullable();
            $table->string('source_type', 32);
            $table->string('source_reference', 2048);
            $table->timestampTz('observed_at');
            $table->timestampTz('valid_until')->nullable();
            $table->string('evidence_id', 64)->nullable();
            $table->char('fingerprint', 64)->unique();
            $table->foreignUlid('recorded_by_player_id')->nullable()->constrained('players')->nullOnDelete();
            $table->timestamps();
            $table->index(['alliance_id', 'transfer_window_id', 'transfer_participant_id', 'kind']);
            $table->index(['transfer_participant_id', 'target_kingdom_id', 'kind', 'observed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transfer_observations');
    }
};
