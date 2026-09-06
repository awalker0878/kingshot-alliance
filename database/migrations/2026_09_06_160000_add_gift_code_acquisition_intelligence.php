<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gift_code_source_smoke_checks', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('gift_code_source_id')->constrained('gift_code_sources')->cascadeOnDelete();
            $table->string('adapter_key', 120)->nullable()->index();
            $table->string('status', 24)->index();
            $table->json('readiness')->nullable();
            $table->unsignedInteger('observation_count')->default(0);
            $table->string('retrieval_version', 120)->nullable();
            $table->string('provider_request_id', 255)->nullable();
            $table->unsignedInteger('duration_ms')->default(0);
            $table->string('push_status', 32)->nullable()->index();
            $table->string('failure_code', 120)->nullable()->index();
            $table->text('failure_message')->nullable();
            $table->foreignId('checked_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('checked_at')->index();
            $table->timestampsTz();

            $table->index(['gift_code_source_id', 'checked_at']);
            $table->index(['gift_code_source_id', 'status', 'checked_at']);
        });

        Schema::create('gift_code_observation_clusters', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('gift_code_id')->constrained('gift_codes')->cascadeOnDelete()->unique();
            $table->foreignUlid('earliest_source_id')->nullable()->constrained('gift_code_sources')->nullOnDelete();
            $table->unsignedInteger('observation_count')->default(0);
            $table->unsignedInteger('distinct_source_count')->default(0);
            $table->unsignedInteger('independent_source_count')->default(0);
            $table->unsignedInteger('official_source_count')->default(0);
            $table->timestampTz('first_seen_at')->nullable()->index();
            $table->timestampTz('earliest_qualified_publication_at')->nullable()->index();
            $table->unsignedInteger('time_to_code_seconds')->nullable();
            $table->string('correlation_confidence', 24)->default('low')->index();
            $table->json('correlation_signals')->nullable();
            $table->unsignedBigInteger('revision')->default(0);
            $table->timestampTz('derived_at')->index();
            $table->timestampsTz();
        });

        Schema::create('gift_code_source_performance_projections', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('gift_code_source_id')->constrained('gift_code_sources')->cascadeOnDelete()->unique();
            $table->unsignedBigInteger('observations')->default(0);
            $table->unsignedBigInteger('unique_codes_discovered')->default(0);
            $table->unsignedBigInteger('first_discoveries')->default(0);
            $table->unsignedBigInteger('qualified_observations')->default(0);
            $table->unsignedBigInteger('confirmed_correct')->default(0);
            $table->unsignedBigInteger('confirmed_incorrect')->default(0);
            $table->unsignedBigInteger('conflicting_observations')->default(0);
            $table->unsignedInteger('median_discovery_latency_seconds')->nullable();
            $table->unsignedInteger('median_confirmation_latency_seconds')->nullable();
            $table->unsignedInteger('median_time_to_code_seconds')->nullable();
            $table->unsignedInteger('p95_time_to_code_seconds')->nullable();
            $table->decimal('useful_observation_ratio', 8, 6)->default(0);
            $table->decimal('quarantine_ratio', 8, 6)->default(0);
            $table->decimal('duplicate_ratio', 8, 6)->default(0);
            $table->unsignedInteger('latency_sample_count')->default(0);
            $table->timestampTz('last_productive_observation_at')->nullable()->index();
            $table->unsignedBigInteger('revision')->default(0);
            $table->timestampTz('derived_at')->index();
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gift_code_source_performance_projections');
        Schema::dropIfExists('gift_code_observation_clusters');
        Schema::dropIfExists('gift_code_source_smoke_checks');
    }
};
