<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_deliveries', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('notification_type', 96)->index();
            $table->unsignedBigInteger('recipient_user_id')->index();
            $table->string('player_id', 26)->nullable()->index();
            $table->string('channel', 32);
            $table->string('subject_type', 64)->nullable();
            $table->string('subject_id', 64)->nullable();
            $table->timestampTz('due_at')->index();
            $table->string('status', 16)->default('pending')->index();
            $table->unsignedInteger('attempt_count')->default(0);
            $table->unsignedInteger('max_attempts')->default(5);
            $table->string('idempotency_key', 64)->unique();
            $table->timestampTz('queued_at')->nullable();
            $table->timestampTz('sent_at')->nullable();
            $table->timestampTz('failed_at')->nullable();
            $table->timestampTz('next_attempt_at')->nullable()->index();
            $table->text('last_error')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->index(['status', 'due_at']);
            $table->index(['recipient_user_id', 'status']);
            $table->index(['subject_type', 'subject_id']);
        });

        Schema::create('notification_preferences', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->unsignedBigInteger('recipient_user_id')->index();
            $table->string('player_id', 26)->nullable()->index();
            $table->string('notification_type', 96);
            $table->string('channel', 32);
            $table->boolean('enabled')->default(true);
            $table->json('settings')->nullable();
            $table->timestamps();

            $table->unique(
                ['recipient_user_id', 'player_id', 'notification_type', 'channel'],
                'notification_preferences_recipient_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_preferences');
        Schema::dropIfExists('notification_deliveries');
    }
};
