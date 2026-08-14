<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contribution_categories', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->ulid('alliance_id');
            $table->string('name', 120);
            $table->string('slug', 120);
            $table->text('description')->nullable();
            $table->string('unit', 40);
            $table->string('period', 24);
            $table->date('period_start')->nullable();
            $table->date('period_end')->nullable();
            $table->decimal('goal_value', 14, 2)->nullable();
            $table->boolean('evidence_required')->default(false);
            $table->boolean('allow_self_report')->default(false);
            $table->boolean('leaderboard_enabled')->default(true);
            $table->string('data_class', 24);
            $table->string('calculation_key', 80)->nullable();
            $table->string('calculation_version', 40)->nullable();
            $table->text('calculation_description')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->foreignUlid('created_by_player_id')->constrained('players')->restrictOnDelete();
            $table->foreignUlid('updated_by_player_id')->constrained('players')->restrictOnDelete();
            $table->timestamps();

            $table->foreign('alliance_id')->references('id')->on('alliances')->cascadeOnDelete();
            $table->unique(['id', 'alliance_id']);
            $table->unique(['alliance_id', 'slug']);
            $table->index(['alliance_id', 'is_active', 'period']);
        });

        Schema::create('contribution_records', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->ulid('alliance_id');
            $table->ulid('category_id');
            $table->foreignUlid('player_id')->constrained('players')->restrictOnDelete();
            $table->string('source', 32);
            $table->string('data_class', 24);
            $table->decimal('value', 14, 2);
            $table->date('period_start');
            $table->date('period_end');
            $table->string('status', 24)->index();
            $table->text('evidence')->nullable();
            $table->ulid('correction_of_record_id')->nullable();
            $table->string('calculation_key', 80)->nullable();
            $table->string('calculation_version', 40)->nullable();
            $table->json('calculation_inputs')->nullable();
            $table->timestamp('recorded_at');
            $table->foreignUlid('recorded_by_player_id')->nullable()->constrained('players')->nullOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->foreignUlid('approved_by_player_id')->nullable()->constrained('players')->nullOnDelete();
            $table->timestamp('reversed_at')->nullable();
            $table->foreignUlid('reversed_by_player_id')->nullable()->constrained('players')->nullOnDelete();
            $table->text('reversal_reason')->nullable();
            $table->text('correction_reason')->nullable();
            $table->timestamps();

            $table->unique(['id', 'alliance_id']);
            $table->foreign('alliance_id')->references('id')->on('alliances')->cascadeOnDelete();
            $table->foreign(['category_id', 'alliance_id'])
                ->references(['id', 'alliance_id'])
                ->on('contribution_categories')
                ->restrictOnDelete();
            $table->foreign(['correction_of_record_id', 'alliance_id'])
                ->references(['id', 'alliance_id'])
                ->on('contribution_records')
                ->restrictOnDelete();
            $table->index(['alliance_id', 'player_id', 'period_start', 'period_end']);
            $table->index(['alliance_id', 'category_id', 'status']);
        });

        Schema::create('contribution_data_quality_flags', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->ulid('alliance_id');
            $table->foreignUlid('player_id')->nullable()->constrained('players')->restrictOnDelete();
            $table->ulid('category_id')->nullable();
            $table->ulid('record_id')->nullable();
            $table->string('code', 64);
            $table->string('severity', 16)->default('warning');
            $table->text('message');
            $table->string('status', 16)->default('open')->index();
            $table->timestamp('detected_at');
            $table->timestamp('resolved_at')->nullable();
            $table->foreignUlid('resolved_by_player_id')->nullable()->constrained('players')->nullOnDelete();
            $table->timestamps();

            $table->foreign('alliance_id')->references('id')->on('alliances')->cascadeOnDelete();
            $table->foreign(['category_id', 'alliance_id'])
                ->references(['id', 'alliance_id'])
                ->on('contribution_categories')
                ->cascadeOnDelete();
            $table->foreign(['record_id', 'alliance_id'])
                ->references(['id', 'alliance_id'])
                ->on('contribution_records')
                ->cascadeOnDelete();
            $table->unique(['id', 'alliance_id']);
            $table->index(['alliance_id', 'status', 'severity']);
            $table->index(['alliance_id', 'player_id', 'category_id']);
        });

        Schema::create('contribution_report_schedules', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->ulid('alliance_id');
            $table->foreignUlid('recipient_player_id')->constrained('players')->restrictOnDelete();
            $table->string('name', 120);
            $table->string('cadence', 16);
            $table->string('timezone', 64);
            $table->timestamp('next_due_at')->index();
            $table->string('report_version', 40)->default('phase5.v1');
            $table->boolean('is_enabled')->default(true)->index();
            $table->timestamp('last_queued_at')->nullable();
            $table->foreignUlid('created_by_player_id')->constrained('players')->restrictOnDelete();
            $table->timestamps();

            $table->foreign('alliance_id')->references('id')->on('alliances')->cascadeOnDelete();
            $table->unique(['id', 'alliance_id']);
            $table->index(['alliance_id', 'is_enabled', 'next_due_at']);
        });

        Schema::create('contribution_report_runs', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->ulid('alliance_id');
            $table->ulid('schedule_id')->nullable();
            $table->foreignUlid('recipient_player_id')->nullable()->constrained('players')->restrictOnDelete();
            $table->foreignUlid('requested_by_player_id')->nullable()->constrained('players')->nullOnDelete();
            $table->string('format', 24);
            $table->string('status', 24)->index();
            $table->string('report_version', 40);
            $table->json('filters')->nullable();
            $table->unsignedInteger('row_count')->nullable();
            $table->char('checksum', 64)->nullable();
            $table->char('idempotency_key', 64)->unique();
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->foreign('alliance_id')->references('id')->on('alliances')->cascadeOnDelete();
            $table->foreign(['schedule_id', 'alliance_id'])
                ->references(['id', 'alliance_id'])
                ->on('contribution_report_schedules')
                ->restrictOnDelete();
            $table->unique(['id', 'alliance_id']);
            $table->index(['alliance_id', 'status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contribution_report_runs');
        Schema::dropIfExists('contribution_report_schedules');
        Schema::dropIfExists('contribution_data_quality_flags');
        Schema::dropIfExists('contribution_records');
        Schema::dropIfExists('contribution_categories');
    }
};
