<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_templates', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->ulid('alliance_id');
            $table->string('name', 120);
            $table->text('instructions')->nullable();
            $table->string('timezone', 64);
            $table->unsignedInteger('duration_minutes');
            $table->unsignedInteger('capacity')->nullable();
            $table->unsignedInteger('registration_opens_minutes_before')->nullable();
            $table->unsignedInteger('registration_closes_minutes_before')->default(0);
            $table->string('recurrence_frequency', 16)->default('none');
            $table->unsignedSmallInteger('recurrence_interval')->default(1);
            $table->json('recurrence_weekdays')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->foreign('alliance_id')->references('id')->on('alliances')->cascadeOnDelete();
            $table->unique(['id', 'alliance_id']);
            $table->unique(['alliance_id', 'name']);
        });

        Schema::create('events', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->ulid('alliance_id');
            $table->ulid('template_id')->nullable();
            $table->string('title', 160);
            $table->text('instructions')->nullable();
            $table->string('timezone', 64);
            $table->unsignedInteger('duration_minutes');
            $table->unsignedInteger('capacity')->nullable();
            $table->unsignedInteger('registration_opens_minutes_before')->nullable();
            $table->unsignedInteger('registration_closes_minutes_before')->default(0);
            $table->string('recurrence_frequency', 16)->default('none')->index();
            $table->unsignedSmallInteger('recurrence_interval')->default(1);
            $table->json('recurrence_weekdays')->nullable();
            $table->timestamp('recurrence_until')->nullable();
            $table->string('status', 24)->default('draft')->index();
            $table->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->foreign('alliance_id')->references('id')->on('alliances')->cascadeOnDelete();
            $table->foreign(['template_id', 'alliance_id'])
                ->references(['id', 'alliance_id'])
                ->on('event_templates')
                ->restrictOnDelete();
            $table->unique(['id', 'alliance_id']);
            $table->index(['alliance_id', 'status', 'created_at']);
        });

        Schema::create('event_occurrences', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->ulid('alliance_id');
            $table->ulid('event_id');
            $table->timestamp('starts_at')->index();
            $table->timestamp('ends_at');
            $table->timestamp('registration_opens_at')->nullable();
            $table->timestamp('registration_closes_at')->nullable();
            $table->unsignedInteger('capacity')->nullable();
            $table->string('status', 24)->default('scheduled')->index();
            $table->timestamps();

            $table->foreign('alliance_id')->references('id')->on('alliances')->cascadeOnDelete();
            $table->foreign(['event_id', 'alliance_id'])
                ->references(['id', 'alliance_id'])
                ->on('events')
                ->cascadeOnDelete();
            $table->unique(['id', 'alliance_id']);
            $table->unique(['event_id', 'starts_at']);
            $table->index(['alliance_id', 'starts_at', 'status']);
        });

        Schema::create('event_registrations', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->ulid('alliance_id');
            $table->ulid('occurrence_id');
            $table->ulid('membership_id');
            $table->string('status', 24)->index();
            $table->unsignedInteger('waitlist_position')->nullable();
            $table->timestamp('registered_at');
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamp('attendance_recorded_at')->nullable();
            $table->foreignId('attendance_recorded_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->foreign('alliance_id')->references('id')->on('alliances')->cascadeOnDelete();
            $table->foreign(['occurrence_id', 'alliance_id'])
                ->references(['id', 'alliance_id'])
                ->on('event_occurrences')
                ->cascadeOnDelete();
            $table->foreign(['membership_id', 'alliance_id'])
                ->references(['id', 'alliance_id'])
                ->on('alliance_memberships')
                ->cascadeOnDelete();
            $table->unique(['id', 'alliance_id']);
            $table->unique(['occurrence_id', 'membership_id']);
            $table->index(['alliance_id', 'occurrence_id', 'status']);
        });

        Schema::create('event_reminder_rules', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->ulid('alliance_id');
            $table->ulid('event_id');
            $table->unsignedInteger('minutes_before_start');
            $table->string('channel', 24)->default('in_app');
            $table->boolean('is_enabled')->default(true)->index();
            $table->timestamps();

            $table->foreign('alliance_id')->references('id')->on('alliances')->cascadeOnDelete();
            $table->foreign(['event_id', 'alliance_id'])
                ->references(['id', 'alliance_id'])
                ->on('events')
                ->cascadeOnDelete();
            $table->unique(['id', 'alliance_id']);
            $table->unique(['event_id', 'minutes_before_start', 'channel']);
        });

        Schema::create('event_reminder_deliveries', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->ulid('alliance_id');
            $table->ulid('occurrence_id');
            $table->ulid('rule_id');
            $table->ulid('membership_id');
            $table->timestamp('due_at')->index();
            $table->string('status', 24)->default('pending')->index();
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->char('idempotency_key', 64)->unique();
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->foreign('alliance_id')->references('id')->on('alliances')->cascadeOnDelete();
            $table->foreign(['occurrence_id', 'alliance_id'])
                ->references(['id', 'alliance_id'])
                ->on('event_occurrences')
                ->cascadeOnDelete();
            $table->foreign(['rule_id', 'alliance_id'])
                ->references(['id', 'alliance_id'])
                ->on('event_reminder_rules')
                ->cascadeOnDelete();
            $table->foreign(['membership_id', 'alliance_id'])
                ->references(['id', 'alliance_id'])
                ->on('alliance_memberships')
                ->cascadeOnDelete();
            $table->index(['alliance_id', 'status', 'due_at']);
        });

        Schema::create('rally_guidance_rules', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->ulid('alliance_id');
            $table->string('name', 120);
            $table->text('lead_requirements')->nullable();
            $table->text('joiner_guidance')->nullable();
            $table->unsignedSmallInteger('infantry_percent');
            $table->unsignedSmallInteger('cavalry_percent');
            $table->unsignedSmallInteger('archer_percent');
            $table->json('hero_recommendations')->nullable();
            $table->text('notes')->nullable();
            $table->date('effective_from');
            $table->date('effective_until')->nullable();
            $table->string('source', 255)->nullable();
            $table->text('rationale')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->foreignId('created_by_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('updated_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->foreign('alliance_id')->references('id')->on('alliances')->cascadeOnDelete();
            $table->unique(['id', 'alliance_id']);
            $table->index(['alliance_id', 'is_active', 'effective_from']);
        });

        Schema::create('member_formations', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->ulid('alliance_id');
            $table->ulid('membership_id');
            $table->string('name', 100);
            $table->json('heroes')->nullable();
            $table->unsignedSmallInteger('infantry_percent');
            $table->unsignedSmallInteger('cavalry_percent');
            $table->unsignedSmallInteger('archer_percent');
            $table->text('notes')->nullable();
            $table->boolean('is_default')->default(false)->index();
            $table->timestamps();

            $table->foreign('alliance_id')->references('id')->on('alliances')->cascadeOnDelete();
            $table->foreign(['membership_id', 'alliance_id'])
                ->references(['id', 'alliance_id'])
                ->on('alliance_memberships')
                ->cascadeOnDelete();
            $table->unique(['id', 'alliance_id']);
            $table->unique(['membership_id', 'name']);
        });

        Schema::create('event_recommended_formations', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->ulid('alliance_id');
            $table->ulid('occurrence_id');
            $table->ulid('guidance_rule_id')->nullable();
            $table->string('name', 100);
            $table->string('assignment_role', 24)->default('joiner');
            $table->json('heroes')->nullable();
            $table->unsignedSmallInteger('infantry_percent');
            $table->unsignedSmallInteger('cavalry_percent');
            $table->unsignedSmallInteger('archer_percent');
            $table->text('notes')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('alliance_id')->references('id')->on('alliances')->cascadeOnDelete();
            $table->foreign(['occurrence_id', 'alliance_id'])
                ->references(['id', 'alliance_id'])
                ->on('event_occurrences')
                ->cascadeOnDelete();
            $table->foreign(['guidance_rule_id', 'alliance_id'])
                ->references(['id', 'alliance_id'])
                ->on('rally_guidance_rules')
                ->restrictOnDelete();
            $table->unique(['id', 'alliance_id']);
            $table->index(['alliance_id', 'occurrence_id', 'sort_order']);
        });

        Schema::create('rally_groups', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->ulid('alliance_id');
            $table->ulid('occurrence_id');
            $table->ulid('recommended_formation_id')->nullable();
            $table->string('name', 100);
            $table->unsignedInteger('max_joiners')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->foreign('alliance_id')->references('id')->on('alliances')->cascadeOnDelete();
            $table->foreign(['occurrence_id', 'alliance_id'])
                ->references(['id', 'alliance_id'])
                ->on('event_occurrences')
                ->cascadeOnDelete();
            $table->foreign(['recommended_formation_id', 'alliance_id'])
                ->references(['id', 'alliance_id'])
                ->on('event_recommended_formations')
                ->restrictOnDelete();
            $table->unique(['id', 'alliance_id']);
            $table->index(['alliance_id', 'occurrence_id', 'sort_order']);
        });

        Schema::create('rally_assignments', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->ulid('alliance_id');
            $table->ulid('rally_group_id');
            $table->ulid('membership_id');
            $table->string('role', 24)->index();
            $table->unsignedInteger('slot_number')->nullable();
            $table->string('status', 24)->default('assigned')->index();
            $table->timestamp('participation_recorded_at')->nullable();
            $table->foreignId('assigned_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamps();

            $table->foreign('alliance_id')->references('id')->on('alliances')->cascadeOnDelete();
            $table->foreign(['rally_group_id', 'alliance_id'])
                ->references(['id', 'alliance_id'])
                ->on('rally_groups')
                ->cascadeOnDelete();
            $table->foreign(['membership_id', 'alliance_id'])
                ->references(['id', 'alliance_id'])
                ->on('alliance_memberships')
                ->cascadeOnDelete();
            $table->unique(['id', 'alliance_id']);
            $table->unique(['rally_group_id', 'membership_id']);
            $table->index(['alliance_id', 'rally_group_id', 'role', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('rally_assignments');
        Schema::dropIfExists('rally_groups');
        Schema::dropIfExists('event_recommended_formations');
        Schema::dropIfExists('member_formations');
        Schema::dropIfExists('rally_guidance_rules');
        Schema::dropIfExists('event_reminder_deliveries');
        Schema::dropIfExists('event_reminder_rules');
        Schema::dropIfExists('event_registrations');
        Schema::dropIfExists('event_occurrences');
        Schema::dropIfExists('events');
        Schema::dropIfExists('event_templates');
    }
};
