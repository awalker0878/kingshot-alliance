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
        Schema::create('event_templates', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->ulid('event_type_scope_id');
            $table->ulid('event_type_id');
            $table->string('scope', 16);
            $table->foreignUlid('alliance_id')->nullable()->constrained('alliances')->cascadeOnDelete();
            $table->foreignUlid('kingdom_id')->nullable()->constrained('kingdoms')->cascadeOnDelete();
            $table->foreignUlid('player_id')->nullable()->constrained('players')->cascadeOnDelete();
            $table->string('name', 120);
            $table->text('instructions')->nullable();
            $table->string('timezone', 64);
            $table->string('schedule_source', 32);
            $table->string('recurrence_policy', 32);
            $table->unsignedInteger('minimum_repeat_interval_minutes')->nullable();
            $table->unsignedInteger('duration_minutes');
            $table->unsignedInteger('capacity')->nullable();
            $table->unsignedInteger('registration_opens_minutes_before')->nullable();
            $table->unsignedInteger('registration_closes_minutes_before')->nullable();
            $table->string('recurrence_frequency', 24)->default('none');
            $table->unsignedInteger('recurrence_interval')->default(1);
            $table->json('settings')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->foreignUlid('created_by_player_id')->constrained('players')->restrictOnDelete();
            $table->foreignUlid('updated_by_player_id')->constrained('players')->restrictOnDelete();
            $table->timestamps();

            $table->foreign(['event_type_scope_id', 'event_type_id', 'scope'])
                ->references(['id', 'event_type_id', 'scope'])
                ->on('event_type_scopes')
                ->restrictOnDelete();
            $table->index(['scope', 'alliance_id', 'is_active']);
            $table->index(['scope', 'kingdom_id', 'is_active']);
            $table->index(['scope', 'player_id', 'is_active']);
        });

        Schema::create('events', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->ulid('event_type_scope_id');
            $table->ulid('event_type_id');
            $table->string('scope', 16);
            $table->foreignUlid('alliance_id')->nullable()->constrained('alliances')->restrictOnDelete();
            $table->foreignUlid('kingdom_id')->nullable()->constrained('kingdoms')->restrictOnDelete();
            $table->foreignUlid('player_id')->nullable()->constrained('players')->restrictOnDelete();
            $table->foreignUlid('template_id')->nullable()->constrained('event_templates')->nullOnDelete();
            $table->string('target_display_name', 180);
            $table->string('target_secondary_label', 180)->nullable();
            $table->string('title', 160)->nullable();
            $table->text('instructions')->nullable();
            $table->string('timezone', 64);
            $table->string('schedule_source', 32);
            $table->string('recurrence_policy', 32);
            $table->unsignedInteger('minimum_repeat_interval_minutes')->nullable();
            $table->timestamp('starts_at');
            $table->unsignedInteger('duration_minutes');
            $table->unsignedInteger('capacity')->nullable();
            $table->unsignedInteger('registration_opens_minutes_before')->nullable();
            $table->unsignedInteger('registration_closes_minutes_before')->nullable();
            $table->string('recurrence_frequency', 24)->default('none');
            $table->unsignedInteger('recurrence_interval')->default(1);
            $table->timestamp('recurrence_until')->nullable();
            $table->json('settings')->nullable();
            $table->string('status', 24)->default('draft')->index();
            $table->foreignUlid('created_by_player_id')->constrained('players')->restrictOnDelete();
            $table->foreignUlid('updated_by_player_id')->constrained('players')->restrictOnDelete();
            $table->timestamps();

            $table->foreign(['event_type_scope_id', 'event_type_id', 'scope'])
                ->references(['id', 'event_type_id', 'scope'])
                ->on('event_type_scopes')
                ->restrictOnDelete();
            $table->index(['scope', 'alliance_id', 'status', 'starts_at']);
            $table->index(['scope', 'kingdom_id', 'status', 'starts_at']);
            $table->index(['scope', 'player_id', 'status', 'starts_at']);
            $table->index(['scope', 'event_type_id', 'player_id', 'id'], 'events_player_type_history_idx');
            $table->index(['scope', 'event_type_id', 'alliance_id', 'id'], 'events_alliance_type_history_idx');
            $table->index(['scope', 'event_type_id', 'kingdom_id', 'id'], 'events_kingdom_type_history_idx');
        });

        Schema::create('event_occurrences', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('event_id')->constrained('events')->restrictOnDelete();
            $table->timestamp('starts_at')->index();
            $table->timestamp('ends_at');
            $table->string('status', 24)->default('scheduled')->index();
            $table->json('settings')->nullable();
            $table->timestamps();

            $table->unique(['event_id', 'starts_at']);
            $table->index(['status', 'starts_at']);
        });

        $this->createTargetGuards('event_templates');
        $this->createTargetGuards('events');
        $this->createHistoricalTargetImmutabilityGuard();
    }

    private function createTargetGuards(string $table): void
    {
        $expression = "((scope = 'player' AND player_id IS NOT NULL AND alliance_id IS NULL AND kingdom_id IS NULL) OR (scope = 'alliance' AND alliance_id IS NOT NULL AND kingdom_id IS NULL AND player_id IS NULL) OR (scope = 'kingdom' AND kingdom_id IS NOT NULL AND alliance_id IS NULL AND player_id IS NULL))";
        $driver = DB::connection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::statement("ALTER TABLE {$table} ADD CONSTRAINT {$table}_scope_target_check CHECK ({$expression})");

            return;
        }

        if ($driver === 'sqlite') {
            DB::statement("CREATE TRIGGER {$table}_scope_target_insert BEFORE INSERT ON {$table} WHEN NOT {$expression} BEGIN SELECT RAISE(ABORT, 'invalid event scope target'); END");
            DB::statement("CREATE TRIGGER {$table}_scope_target_update BEFORE UPDATE OF scope, alliance_id, kingdom_id, player_id ON {$table} WHEN NOT {$expression} BEGIN SELECT RAISE(ABORT, 'invalid event scope target'); END");
        }
    }

    private function createHistoricalTargetImmutabilityGuard(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('DROP FUNCTION IF EXISTS events_historical_target_immutable_guard()');
            DB::statement(<<<'SQL'
CREATE FUNCTION events_historical_target_immutable_guard() RETURNS trigger AS $$
BEGIN
    IF NEW.scope IS DISTINCT FROM OLD.scope
        OR NEW.alliance_id IS DISTINCT FROM OLD.alliance_id
        OR NEW.kingdom_id IS DISTINCT FROM OLD.kingdom_id
        OR NEW.player_id IS DISTINCT FROM OLD.player_id THEN
        RAISE EXCEPTION 'event historical target is immutable';
    END IF;

    RETURN NEW;
END;
$$ LANGUAGE plpgsql
SQL);
            DB::statement(<<<'SQL'
CREATE TRIGGER events_historical_target_immutable
BEFORE UPDATE OF scope, alliance_id, kingdom_id, player_id ON events
FOR EACH ROW EXECUTE FUNCTION events_historical_target_immutable_guard()
SQL);

            return;
        }

        if ($driver === 'sqlite') {
            DB::statement(<<<'SQL'
CREATE TRIGGER events_historical_target_immutable
BEFORE UPDATE OF scope, alliance_id, kingdom_id, player_id ON events
WHEN NEW.scope IS NOT OLD.scope
    OR NEW.alliance_id IS NOT OLD.alliance_id
    OR NEW.kingdom_id IS NOT OLD.kingdom_id
    OR NEW.player_id IS NOT OLD.player_id
BEGIN
    SELECT RAISE(ABORT, 'event historical target is immutable');
END
SQL);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('event_occurrences');
        Schema::dropIfExists('events');

        if (DB::connection()->getDriverName() === 'pgsql') {
            DB::statement('DROP FUNCTION IF EXISTS events_historical_target_immutable_guard()');
        }

        Schema::dropIfExists('event_templates');
    }
};
