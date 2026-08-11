<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kingdom_ingestion_subscriptions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('alliance_id')->constrained('alliances')->cascadeOnDelete();
            $table->foreignUlid('kingdom_id')->constrained('kingdoms')->restrictOnDelete();
            $table->string('adapter_key', 80);
            $table->string('adapter_version', 40);
            $table->string('state', 24)->default('active');
            $table->string('source_cursor', 255)->nullable();
            $table->timestampTz('last_succeeded_at')->nullable();
            $table->timestampTz('last_failed_at')->nullable();
            $table->timestampTz('blocked_at')->nullable();
            $table->string('blocked_reason', 160)->nullable();
            $table->timestamps();

            $table->unique(['alliance_id', 'kingdom_id', 'adapter_key']);
            $table->index(['alliance_id', 'state', 'created_at']);
            $table->index(['alliance_id', 'kingdom_id', 'state']);
        });

        Schema::create('kingdom_ingestion_batches', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('subscription_id')->constrained('kingdom_ingestion_subscriptions')->cascadeOnDelete();
            $table->foreignUlid('alliance_id')->constrained('alliances')->cascadeOnDelete();
            $table->foreignUlid('kingdom_id')->constrained('kingdoms')->restrictOnDelete();
            $table->string('adapter_key', 80);
            $table->string('adapter_version', 40);
            $table->string('source_cursor', 255)->nullable();
            $table->string('source_window_id', 191)->nullable();
            $table->string('state', 24)->default('pending');
            $table->unsignedInteger('records_received')->default(0);
            $table->unsignedInteger('records_staged')->default(0);
            $table->unsignedInteger('records_quarantined')->default(0);
            $table->unsignedInteger('records_rejected')->default(0);
            $table->timestampTz('started_at');
            $table->timestampTz('completed_at')->nullable();
            $table->string('failure_code', 80)->nullable();
            $table->timestamps();

            $table->unique(['subscription_id', 'source_window_id']);
            $table->index(['alliance_id', 'state', 'started_at']);
            $table->index(['subscription_id', 'state', 'started_at']);
        });

        Schema::create('kingdom_ingestion_candidates', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('subscription_id')->constrained('kingdom_ingestion_subscriptions')->cascadeOnDelete();
            $table->foreignUlid('batch_id')->constrained('kingdom_ingestion_batches')->cascadeOnDelete();
            $table->foreignUlid('alliance_id')->constrained('alliances')->cascadeOnDelete();
            $table->foreignUlid('kingdom_id')->constrained('kingdoms')->restrictOnDelete();
            $table->string('target_kind', 40);
            $table->string('stable_game_id', 100)->nullable();
            $table->string('source_record_id', 191)->nullable();
            $table->timestampTz('captured_at');
            $table->json('normalized_payload');
            $table->char('payload_hash', 64);
            $table->char('identity_hash', 64);
            $table->string('state', 24)->default('pending');
            $table->string('quarantine_code', 80)->nullable();
            $table->string('rejection_code', 80)->nullable();
            $table->timestamps();

            $table->unique(['subscription_id', 'identity_hash']);
            $table->index(['alliance_id', 'state', 'created_at']);
            $table->index(['batch_id', 'state', 'created_at']);
            $table->index(['alliance_id', 'target_kind', 'state']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kingdom_ingestion_candidates');
        Schema::dropIfExists('kingdom_ingestion_batches');
        Schema::dropIfExists('kingdom_ingestion_subscriptions');
    }
};
