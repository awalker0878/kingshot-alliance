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
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('observed_name', 160);
            $table->string('observed_tag', 32)->nullable();
            $table->bigInteger('power')->nullable();
            $table->unsignedInteger('member_count')->nullable();
            $table->timestampTz('captured_at');
            $table->string('source', 24)->default('manual');
            $table->char('idempotency_key', 64);
            $table->ulid('corrects_observation_id')->nullable();
            $table->timestampTz('invalidated_at')->nullable();
            $table->foreignId('invalidated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->text('invalidation_reason')->nullable();
            $table->timestamps();

            $table->unique(['alliance_id', 'idempotency_key']);
            $table->index(['alliance_id', 'tracked_kingdom_alliance_id', 'captured_at']);
            $table->index(['alliance_id', 'tracked_kingdom_alliance_id', 'invalidated_at', 'captured_at']);
            $table->index(['kingdom_alliance_id', 'invalidated_at', 'captured_at']);
            $table->index(['alliance_id', 'corrects_observation_id']);
        });

        Schema::table('kingdom_alliance_observations', function (Blueprint $table): void {
            $table->foreign('corrects_observation_id')
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
