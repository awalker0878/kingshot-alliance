<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_responses', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('occurrence_id')->constrained('event_occurrences')->cascadeOnDelete();
            $table->foreignUlid('player_id')->constrained('players')->restrictOnDelete();
            $table->string('response', 16);
            $table->string('preferred_role', 64)->nullable();
            $table->string('preferred_team', 64)->nullable();
            $table->timestamp('available_from')->nullable();
            $table->timestamp('available_until')->nullable();
            $table->text('note')->nullable();
            $table->string('source', 16)->default('self');
            $table->foreignUlid('responded_by_player_id')->constrained('players')->restrictOnDelete();
            $table->timestamp('responded_at');
            $table->timestamps();

            $table->unique(['occurrence_id', 'player_id']);
            $table->index(['occurrence_id', 'response']);
            $table->index(['player_id', 'responded_at']);
        });

        Schema::create('event_registrations', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('occurrence_id')->constrained('event_occurrences')->cascadeOnDelete();
            $table->foreignUlid('player_id')->constrained('players')->restrictOnDelete();
            $table->string('status', 16);
            $table->unsignedInteger('waitlist_position')->nullable();
            $table->foreignUlid('registered_by_player_id')->constrained('players')->restrictOnDelete();
            $table->timestamp('registered_at');
            $table->foreignUlid('cancelled_by_player_id')->nullable()->constrained('players')->restrictOnDelete();
            $table->timestamp('cancelled_at')->nullable();
            $table->timestamps();

            $table->unique(['occurrence_id', 'player_id']);
            $table->index(['occurrence_id', 'status', 'waitlist_position']);
            $table->index(['player_id', 'registered_at']);
        });

        Schema::create('event_attendance', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('occurrence_id')->constrained('event_occurrences')->cascadeOnDelete();
            $table->foreignUlid('player_id')->constrained('players')->restrictOnDelete();
            $table->string('status', 16)->default('unknown');
            $table->text('notes')->nullable();
            $table->foreignUlid('recorded_by_player_id')->constrained('players')->restrictOnDelete();
            $table->timestamp('recorded_at');
            $table->timestamps();

            $table->unique(['occurrence_id', 'player_id']);
            $table->index(['occurrence_id', 'status']);
            $table->index(['player_id', 'recorded_at']);
        });

        Schema::create('event_reminder_rules', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('event_id')->constrained('events')->cascadeOnDelete();
            $table->string('trigger_type', 32)->default('before_start');
            $table->unsignedInteger('minutes_before');
            $table->string('audience', 32);
            $table->string('channel', 24)->default('in_app');
            $table->boolean('is_enabled')->default(true)->index();
            $table->foreignUlid('created_by_player_id')->constrained('players')->restrictOnDelete();
            $table->foreignUlid('updated_by_player_id')->constrained('players')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['event_id', 'trigger_type', 'minutes_before', 'audience', 'channel'], 'event_reminder_rules_definition_unique');
            $table->index(['event_id', 'is_enabled']);
        });

        Schema::create('event_reminder_deliveries', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('occurrence_id')->constrained('event_occurrences')->cascadeOnDelete();
            $table->foreignUlid('rule_id')->constrained('event_reminder_rules')->cascadeOnDelete();
            $table->foreignId('recipient_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignUlid('player_id')->constrained('players')->restrictOnDelete();
            $table->timestamp('due_at')->index();
            $table->string('status', 16)->default('pending')->index();
            $table->unsignedInteger('attempts')->default(0);
            $table->string('idempotency_key', 64)->unique();
            $table->timestamp('queued_at')->nullable();
            $table->timestamp('sent_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->unique(['rule_id', 'occurrence_id', 'player_id'], 'event_reminder_delivery_player_unique');
            $table->index(['status', 'due_at']);
            $table->index(['recipient_user_id', 'status']);
        });

        $this->createRegistrationStatusGuard();
    }

    private function createRegistrationStatusGuard(): void
    {
        $expression = "((status = 'waitlisted' AND waitlist_position IS NOT NULL) OR (status IN ('registered', 'cancelled') AND waitlist_position IS NULL))";
        $driver = DB::connection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::statement("ALTER TABLE event_registrations ADD CONSTRAINT event_registrations_status_position_check CHECK ({$expression})");

            return;
        }

        if ($driver === 'sqlite') {
            DB::statement("CREATE TRIGGER event_registrations_status_position_insert BEFORE INSERT ON event_registrations WHEN NOT {$expression} BEGIN SELECT RAISE(ABORT, 'invalid event registration status'); END");
            DB::statement("CREATE TRIGGER event_registrations_status_position_update BEFORE UPDATE OF status, waitlist_position ON event_registrations WHEN NOT {$expression} BEGIN SELECT RAISE(ABORT, 'invalid event registration status'); END");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('event_reminder_deliveries');
        Schema::dropIfExists('event_reminder_rules');
        Schema::dropIfExists('event_attendance');
        Schema::dropIfExists('event_registrations');
        Schema::dropIfExists('event_responses');
    }
};
