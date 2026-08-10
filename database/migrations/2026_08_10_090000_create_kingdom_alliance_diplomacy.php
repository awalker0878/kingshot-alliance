<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kingdom_alliance_diplomacy_relationships', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('alliance_id')->constrained('alliances')->cascadeOnDelete();
            $table->foreignUlid('tracked_kingdom_alliance_id')->constrained('tracked_kingdom_alliances')->restrictOnDelete();
            $table->foreignUlid('kingdom_alliance_id')->constrained('kingdom_alliances')->restrictOnDelete();
            $table->string('current_state', 24)->default('unknown');
            $table->timestampTz('effective_at');
            $table->timestampTz('review_at')->nullable();
            $table->timestampTz('expires_at')->nullable();
            $table->text('terms')->nullable();
            $table->text('rationale')->nullable();
            $table->foreignId('last_transition_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(
                ['alliance_id', 'tracked_kingdom_alliance_id'],
                'ka_diplomacy_alliance_tracking_unique',
            );
            $table->index(
                ['alliance_id', 'current_state', 'review_at'],
                'ka_diplomacy_state_review_idx',
            );
            $table->index(
                ['alliance_id', 'current_state', 'expires_at'],
                'ka_diplomacy_state_expiry_idx',
            );
        });

        Schema::create('kingdom_alliance_diplomacy_transitions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('alliance_id')->constrained('alliances')->cascadeOnDelete();
            $table->foreignUlid('diplomacy_relationship_id')
                ->constrained('kingdom_alliance_diplomacy_relationships')
                ->restrictOnDelete();
            $table->foreignUlid('tracked_kingdom_alliance_id')->constrained('tracked_kingdom_alliances')->restrictOnDelete();
            $table->foreignUlid('kingdom_alliance_id')->constrained('kingdom_alliances')->restrictOnDelete();
            $table->string('from_state', 24);
            $table->string('to_state', 24);
            $table->timestampTz('effective_at');
            $table->timestampTz('review_at')->nullable();
            $table->timestampTz('expires_at')->nullable();
            $table->text('terms')->nullable();
            $table->text('rationale')->nullable();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('created_at')->useCurrent();

            $table->index(
                ['alliance_id', 'tracked_kingdom_alliance_id', 'created_at'],
                'ka_diplomacy_transition_tracking_idx',
            );
            $table->index(
                ['diplomacy_relationship_id', 'created_at'],
                'ka_diplomacy_transition_relation_idx',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kingdom_alliance_diplomacy_transitions');
        Schema::dropIfExists('kingdom_alliance_diplomacy_relationships');
    }
};
