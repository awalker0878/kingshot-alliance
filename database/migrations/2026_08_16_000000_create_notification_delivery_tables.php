<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_messages', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('notification_type', 96)->index();
            $table->unsignedBigInteger('recipient_user_id')->index();
            $table->string('player_id', 26)->nullable()->index();
            $table->string('subject_type', 64)->nullable();
            $table->string('subject_id', 64)->nullable();
            $table->string('title', 240);
            $table->text('body')->nullable();
            $table->string('action_url', 2048)->nullable();
            $table->string('urgency', 16)->default('normal')->index();
            $table->timestampTz('available_at')->index();
            $table->string('idempotency_key', 64)->unique();
            $table->timestampTz('read_at')->nullable();
            $table->timestampTz('archived_at')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(
                ['recipient_user_id', 'player_id', 'archived_at', 'created_at'],
                'notification_message_inbox_scope_index',
            );
            $table->index(['subject_type', 'subject_id']);
        });

        Schema::create('notification_preferences', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->unsignedBigInteger('recipient_user_id')->index();
            $table->string('player_id', 26)->nullable()->index();
            $table->string('scope_key', 32);
            $table->string('notification_type', 96);
            $table->string('channel', 32);
            $table->boolean('enabled')->default(true);
            $table->json('settings')->nullable();
            $table->timestamps();

            $table->unique(
                ['recipient_user_id', 'scope_key', 'notification_type', 'channel'],
                'notification_preferences_recipient_unique',
            );
        });

        Schema::create('notification_routing_policies', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->unsignedBigInteger('recipient_user_id')->index();
            $table->string('player_id', 26)->nullable()->index();
            $table->string('scope_key', 32);
            $table->string('timezone', 64)->default('UTC');
            $table->boolean('quiet_hours_enabled')->default(false);
            $table->char('quiet_hours_start', 5)->nullable();
            $table->char('quiet_hours_end', 5)->nullable();
            $table->boolean('allow_urgent_during_quiet_hours')->default(false);
            $table->timestampTz('muted_until')->nullable()->index();
            $table->string('digest_cadence', 16)->default('immediate');
            $table->json('settings')->nullable();
            $table->timestamps();

            $table->unique(
                ['recipient_user_id', 'scope_key'],
                'notification_routing_policy_recipient_unique',
            );
        });

        Schema::create('notification_endpoints', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->unsignedBigInteger('recipient_user_id')->index();
            $table->string('player_id', 26)->nullable()->index();
            $table->string('channel', 32)->index();
            $table->string('label', 100);
            $table->text('configuration');
            $table->boolean('enabled')->default(true)->index();
            $table->string('health_status', 24)->default('never_tested')->index();
            $table->timestampTz('last_verified_at')->nullable();
            $table->timestampTz('last_successful_delivery_at')->nullable();
            $table->timestampTz('last_failed_delivery_at')->nullable();
            $table->unsignedInteger('consecutive_failures')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->index(
                ['recipient_user_id', 'player_id', 'channel', 'enabled'],
                'notification_endpoint_route_index',
            );
        });

        Schema::create('notification_deliveries', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('notification_message_id')
                ->constrained('notification_messages')
                ->cascadeOnDelete();
            $table->string('channel', 32)->index();
            $table->foreignUlid('notification_endpoint_id')
                ->nullable()
                ->constrained('notification_endpoints')
                ->nullOnDelete();
            $table->string('route_target_label', 100)->nullable();
            $table->string('digest_cadence', 16)->default('immediate')->index();
            $table->timestampTz('due_at')->index();
            $table->string('status', 16)->default('queued')->index();
            $table->unsignedInteger('attempt_count')->default(0);
            $table->unsignedInteger('max_attempts')->default(5);
            $table->string('idempotency_key', 64)->unique();
            $table->timestampTz('queued_at')->nullable();
            $table->timestampTz('sent_at')->nullable();
            $table->timestampTz('failed_at')->nullable();
            $table->timestampTz('next_attempt_at')->nullable()->index();
            $table->string('routing_reason', 96)->nullable();
            $table->string('provider_reference', 255)->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->index(['status', 'due_at']);
            $table->index(['notification_message_id', 'status']);
            $table->index(['notification_endpoint_id', 'status']);
        });

        Schema::create('notification_digest_dispatches', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->unsignedBigInteger('recipient_user_id')->index();
            $table->string('player_id', 26)->nullable()->index();
            $table->string('channel', 32)->index();
            $table->foreignUlid('notification_endpoint_id')
                ->nullable()
                ->constrained('notification_endpoints')
                ->nullOnDelete();
            $table->string('cadence', 16);
            $table->string('window_key', 64);
            $table->string('status', 16)->default('queued')->index();
            $table->timestampTz('due_at')->index();
            $table->timestampTz('sent_at')->nullable();
            $table->timestampTz('failed_at')->nullable();
            $table->timestampTz('next_attempt_at')->nullable()->index();
            $table->unsignedInteger('attempt_count')->default(0);
            $table->unsignedInteger('max_attempts')->default(5);
            $table->string('idempotency_key', 64)->unique();
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->index(['status', 'due_at']);
        });

        Schema::create('notification_digest_members', function (Blueprint $table): void {
            $table->foreignUlid('notification_digest_dispatch_id')
                ->constrained('notification_digest_dispatches')
                ->cascadeOnDelete();
            $table->foreignUlid('notification_delivery_id')
                ->constrained('notification_deliveries')
                ->cascadeOnDelete();
            $table->timestamps();

            $table->primary(
                ['notification_digest_dispatch_id', 'notification_delivery_id'],
                'notification_digest_members_primary',
            );
            $table->unique('notification_delivery_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_digest_members');
        Schema::dropIfExists('notification_digest_dispatches');
        Schema::dropIfExists('notification_deliveries');
        Schema::dropIfExists('notification_endpoints');
        Schema::dropIfExists('notification_routing_policies');
        Schema::dropIfExists('notification_preferences');
        Schema::dropIfExists('notification_messages');
    }
};
