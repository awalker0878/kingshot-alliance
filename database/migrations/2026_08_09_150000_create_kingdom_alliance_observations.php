<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kingdom_alliance_observations', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('alliance_id')->constrained('alliances')->cascadeOnDelete();
            $table->foreignUlid('tracked_kingdom_alliance_id')->constrained('tracked_kingdom_alliances')->restrictOnDelete();
            $table->foreignUlid('kingdom_alliance_id')->constrained('kingdom_alliances')->restrictOnDelete();
            $table->foreignUlid('actor_player_id')->nullable()->constrained('players')->nullOnDelete();
            $table->string('observed_name', 160);
            $table->string('observed_tag', 32)->nullable();
            $table->bigInteger('power')->nullable();
            $table->unsignedInteger('member_count')->nullable();
            $table->timestampTz('captured_at');
            $table->string('source', 24)->default('manual');
            $table->string('source_subscription_id', 26)->nullable();
            $table->string('source_batch_id', 26)->nullable();
            $table->string('source_adapter_key', 80)->nullable();
            $table->string('source_adapter_version', 40)->nullable();
            $table->string('source_record_id', 191)->nullable();
            $table->char('source_identity_hash', 64)->nullable();
            $table->char('source_payload_hash', 64)->nullable();
            $table->char('idempotency_key', 64);
            $table->ulid('corrects_observation_id')->nullable();
            $table->timestampTz('invalidated_at')->nullable();
            $table->foreignUlid('invalidated_by_player_id')->nullable()->constrained('players')->nullOnDelete();
            $table->text('invalidation_reason')->nullable();
            $table->timestamps();

            $table->unique(['alliance_id', 'idempotency_key'], 'ka_obs_alliance_idempotency_unique');
            $table->unique(['alliance_id', 'source_identity_hash'], 'ka_obs_alliance_source_identity_unique');
            $table->index(['alliance_id', 'tracked_kingdom_alliance_id', 'captured_at'], 'ka_obs_tracking_capture_idx');
            $table->index(
                ['alliance_id', 'tracked_kingdom_alliance_id', 'invalidated_at', 'captured_at'],
                'ka_obs_tracking_acceptance_capture_idx',
            );
            $table->index(['kingdom_alliance_id', 'invalidated_at', 'captured_at'], 'ka_obs_reference_acceptance_capture_idx');
            $table->index(['alliance_id', 'corrects_observation_id'], 'ka_obs_correction_target_idx');
        });

        Schema::table('kingdom_alliance_observations', function (Blueprint $table): void {
            $table->foreign('corrects_observation_id', 'ka_obs_correction_target_fk')
                ->references('id')
                ->on('kingdom_alliance_observations')
                ->restrictOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kingdom_alliance_observations');
    }
};
