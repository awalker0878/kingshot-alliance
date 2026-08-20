<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('media_assets', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->ulid('alliance_id');
            $table->string('original_name', 255);
            $table->string('disk', 32);
            $table->string('path', 500);
            $table->string('mime_type', 100);
            $table->unsignedBigInteger('size_bytes');
            $table->char('sha256', 64);
            $table->string('scan_status', 32)->index();
            $table->string('lifecycle_status', 32)->index();
            $table->foreignUlid('uploaded_by_player_id')->constrained('players')->restrictOnDelete();
            $table->timestamp('scanned_at')->nullable();
            $table->timestamps();

            $table->foreign('alliance_id')->references('id')->on('alliances')->cascadeOnDelete();
            $table->unique(['id', 'alliance_id']);
            $table->unique(['disk', 'path']);
            $table->index(['alliance_id', 'lifecycle_status', 'created_at']);
        });

        Schema::create('alliance_profiles', function (Blueprint $table): void {
            $table->ulid('alliance_id')->primary();
            $table->text('description')->nullable();
            $table->string('primary_color', 7)->nullable();
            $table->timestamps();

            $table->foreign('alliance_id')->references('id')->on('alliances')->cascadeOnDelete();
        });

        Schema::create('alliance_branding_media', function (Blueprint $table): void {
            $table->ulid('alliance_id');
            $table->string('slot', 16);
            $table->ulid('media_id');
            $table->timestamps();

            $table->primary(['alliance_id', 'slot']);
            $table->foreign('alliance_id')->references('id')->on('alliances')->cascadeOnDelete();
            $table->foreign(['media_id', 'alliance_id'])
                ->references(['id', 'alliance_id'])
                ->on('media_assets')
                ->restrictOnDelete();
        });

        Schema::create('content_categories', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->ulid('alliance_id');
            $table->string('name', 100);
            $table->string('slug', 100);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('alliance_id')->references('id')->on('alliances')->cascadeOnDelete();
            $table->unique(['id', 'alliance_id']);
            $table->unique(['alliance_id', 'slug']);
            $table->index(['alliance_id', 'sort_order', 'name']);
        });

        Schema::create('content_items', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->ulid('alliance_id');
            $table->ulid('category_id')->nullable();
            $table->string('type', 32)->index();
            $table->string('visibility', 32)->index();
            $table->string('status', 32)->index();
            $table->string('title', 180);
            $table->string('slug', 180);
            $table->string('summary', 500)->nullable();
            $table->text('body');
            $table->string('locale', 16)->default('en')->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->unsignedInteger('current_revision_number')->default(1);
            $table->timestamp('scheduled_for')->nullable()->index();
            $table->timestamp('published_at')->nullable()->index();
            $table->timestamp('archived_at')->nullable();
            $table->boolean('notify_members')->default(false);
            $table->timestamp('broadcasted_at')->nullable();
            $table->string('source_label', 180)->nullable();
            $table->string('source_url', 2048)->nullable();
            $table->string('game_version', 64)->nullable();
            $table->date('reviewed_at')->nullable();
            $table->json('context_links')->nullable();
            $table->foreignUlid('created_by_player_id')->constrained('players')->restrictOnDelete();
            $table->foreignUlid('updated_by_player_id')->constrained('players')->restrictOnDelete();
            $table->timestamps();

            $table->foreign('alliance_id')->references('id')->on('alliances')->cascadeOnDelete();
            $table->foreign(['category_id', 'alliance_id'])
                ->references(['id', 'alliance_id'])
                ->on('content_categories')
                ->restrictOnDelete();
            $table->unique(['id', 'alliance_id']);
            $table->unique(['alliance_id', 'slug']);
            $table->index(['alliance_id', 'status', 'visibility', 'published_at']);
            $table->index(['alliance_id', 'type', 'sort_order']);
            $table->index(
                ['type', 'status', 'notify_members', 'broadcasted_at'],
                'content_broadcast_queue_index',
            );
        });

        Schema::create('content_revisions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->ulid('alliance_id');
            $table->ulid('content_item_id');
            $table->unsignedInteger('revision_number');
            $table->ulid('category_id')->nullable();
            $table->string('type', 32);
            $table->string('visibility', 32);
            $table->string('title', 180);
            $table->string('summary', 500)->nullable();
            $table->text('body');
            $table->string('locale', 16);
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('notify_members')->default(false);
            $table->string('source_label', 180)->nullable();
            $table->string('source_url', 2048)->nullable();
            $table->string('game_version', 64)->nullable();
            $table->date('reviewed_at')->nullable();
            $table->json('context_links')->nullable();
            $table->foreignUlid('created_by_player_id')->constrained('players')->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->foreign('alliance_id')->references('id')->on('alliances')->cascadeOnDelete();
            $table->foreign(['content_item_id', 'alliance_id'])
                ->references(['id', 'alliance_id'])
                ->on('content_items')
                ->cascadeOnDelete();
            $table->foreign(['category_id', 'alliance_id'])
                ->references(['id', 'alliance_id'])
                ->on('content_categories')
                ->restrictOnDelete();
            $table->unique(['content_item_id', 'revision_number']);
            $table->index(['alliance_id', 'content_item_id', 'revision_number']);
        });

        Schema::create('announcement_broadcast_schedules', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->ulid('alliance_id');
            $table->ulid('content_item_id');
            $table->foreignUlid('created_by_player_id')->constrained('players')->restrictOnDelete();
            $table->string('timezone', 64);
            $table->json('weekdays');
            $table->string('local_time', 5);
            $table->string('status', 16)->index();
            $table->timestampTz('next_run_at')->nullable()->index();
            $table->timestampTz('last_run_at')->nullable();
            $table->timestampTz('ends_at')->nullable();
            $table->timestampTz('cancelled_at')->nullable();
            $table->timestampsTz();

            $table->foreign(['content_item_id', 'alliance_id'])
                ->references(['id', 'alliance_id'])
                ->on('content_items')
                ->cascadeOnDelete();
            $table->unique(['content_item_id']);
            $table->index(['alliance_id', 'status', 'next_run_at']);
        });

        Schema::create('announcement_broadcast_runs', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->ulid('alliance_id');
            $table->ulid('content_item_id');
            $table->foreignUlid('schedule_id')->nullable()->constrained('announcement_broadcast_schedules')->cascadeOnDelete();
            $table->timestampTz('scheduled_for');
            $table->string('status', 24)->index();
            $table->unsignedInteger('recipient_count')->default(0);
            $table->unsignedInteger('delivery_count')->default(0);
            $table->string('idempotency_key', 191)->unique();
            $table->timestampTz('queued_at')->nullable();
            $table->timestampTz('cancelled_at')->nullable();
            $table->timestampsTz();

            $table->foreign(['content_item_id', 'alliance_id'])
                ->references(['id', 'alliance_id'])
                ->on('content_items')
                ->cascadeOnDelete();
            $table->index(['alliance_id', 'scheduled_for']);
            $table->index(['content_item_id', 'scheduled_for']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('announcement_broadcast_runs');
        Schema::dropIfExists('announcement_broadcast_schedules');
        Schema::dropIfExists('content_revisions');
        Schema::dropIfExists('content_items');
        Schema::dropIfExists('content_categories');
        Schema::dropIfExists('alliance_branding_media');
        Schema::dropIfExists('alliance_profiles');
        Schema::dropIfExists('media_assets');
    }
};
