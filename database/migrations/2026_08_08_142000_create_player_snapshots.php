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
            $table->string('source_subscription_id', 26)->nullable();
            $table->string('source_batch_id', 26)->nullable();
            $table->string('source_adapter_key', 80)->nullable();
            $table->string('source_adapter_version', 40)->nullable();
            $table->string('source_record_id', 191)->nullable();
            $table->char('source_identity_hash', 64)->nullable();
            $table->char('source_payload_hash', 64)->nullable();
            $table->string('idempotency_key', 64);
            $table->timestamps();

            $table->unique(['alliance_id', 'idempotency_key']);
            $table->unique(
                ['alliance_id', 'source_identity_hash'],
                'player_snapshots_alliance_source_identity_unique',
            );
            $table->index(['alliance_id', 'roster_entry_id', 'captured_at']);
            $table->index(['alliance_id', 'player_id', 'captured_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('player_snapshots');
    }
};
