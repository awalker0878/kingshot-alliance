<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('territory_plan_annotations', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('territory_plan_id')->constrained('territory_plans')->cascadeOnDelete();
            $table->string('plan_key', 120);
            $table->string('kind', 20);
            $table->string('alliance_key', 120)->nullable();
            $table->string('text', 500)->nullable();
            $table->integer('coordinate_x');
            $table->integer('coordinate_y');
            $table->integer('target_x')->nullable();
            $table->integer('target_y')->nullable();
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->foreignUlid('created_by_player_id')->constrained('players')->restrictOnDelete();
            $table->foreignUlid('updated_by_player_id')->constrained('players')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['territory_plan_id', 'plan_key']);
            $table->index(['territory_plan_id', 'kind']);
        });

        Schema::create('territory_hive_templates', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('kingdom_id')->constrained('kingdoms')->cascadeOnDelete();
            $table->string('name', 160);
            $table->string('map_dataset_id', 120);
            $table->char('map_dataset_checksum', 64);
            $table->string('style', 24);
            $table->unsignedSmallInteger('city_count');
            $table->unsignedSmallInteger('spacing');
            $table->json('planning_preferences');
            $table->foreignUlid('created_by_player_id')->constrained('players')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['kingdom_id', 'name']);
            $table->index(['kingdom_id', 'map_dataset_id']);
        });

        Schema::create('territory_renditions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('territory_plan_id')->constrained('territory_plans')->cascadeOnDelete();
            $table->foreignUlid('territory_plan_revision_id')->constrained('territory_plan_revisions')->restrictOnDelete();
            $table->string('scope', 24);
            $table->string('media_type', 40);
            $table->char('content_checksum', 64);
            $table->unsignedInteger('content_bytes');
            $table->longText('content_base64');
            $table->json('metadata');
            $table->foreignUlid('created_by_player_id')->constrained('players')->restrictOnDelete();
            $table->timestamp('created_at');

            $table->unique(['territory_plan_revision_id', 'scope', 'media_type', 'content_checksum'], 'territory_rendition_identity_unique');
            $table->index(['territory_plan_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('territory_renditions');
        Schema::dropIfExists('territory_hive_templates');
        Schema::dropIfExists('territory_plan_annotations');
    }
};
