<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('territory_object_comments', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('territory_plan_id')->constrained()->cascadeOnDelete();
            $table->string('object_key', 120);
            $table->string('alliance_key', 120);
            $table->foreignUlid('author_player_id')->constrained('players');
            $table->unsignedInteger('head_revision');
            $table->json('object_snapshot');
            $table->text('body');
            $table->timestamps();
            $table->index(['territory_plan_id', 'id']);
        });
        Schema::create('territory_plan_reviews', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('territory_plan_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('reviewer_player_id')->constrained('players');
            $table->unsignedInteger('head_revision');
            $table->string('snapshot_checksum', 64);
            $table->string('decision', 32);
            $table->text('note')->nullable();
            $table->timestamps();
            $table->unique(['territory_plan_id', 'reviewer_player_id', 'head_revision'], 'territory_review_actor_revision');
        });
        Schema::create('territory_plan_access_grants', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('territory_plan_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('player_id')->constrained('players');
            $table->foreignUlid('granted_by_player_id')->constrained('players');
            $table->string('alliance_key', 120);
            $table->string('permission', 16);
            $table->timestamp('expires_at');
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
            $table->unique(['territory_plan_id', 'player_id', 'alliance_key'], 'territory_access_actor_layer');
        });
        Schema::create('territory_shares', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('territory_plan_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('territory_plan_revision_id')->constrained();
            $table->foreignUlid('recipient_player_id')->constrained('players');
            $table->foreignUlid('created_by_player_id')->constrained('players');
            $table->string('token_hash', 64)->unique();
            $table->json('alliance_keys');
            $table->timestamp('expires_at');
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();
            $table->index(['territory_plan_id', 'id']);
        });
        Schema::create('territory_activities', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('territory_plan_id')->constrained()->cascadeOnDelete();
            $table->foreignUlid('actor_player_id')->constrained('players');
            $table->string('kind', 32);
            $table->string('meaning_key', 120);
            $table->json('recipient_player_ids');
            $table->string('after_player_id', 26)->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->unique(['territory_plan_id', 'kind', 'meaning_key'], 'territory_activity_meaning');
            $table->index(['completed_at', 'id']);
        });
    }

    public function down(): void
    {
        foreach (['territory_activities', 'territory_shares', 'territory_plan_access_grants', 'territory_plan_reviews', 'territory_object_comments'] as $table) {
            Schema::dropIfExists($table);
        }
    }
};
