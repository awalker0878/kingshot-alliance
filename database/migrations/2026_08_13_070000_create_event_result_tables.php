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
        Schema::create('event_metric_definitions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('event_type_scope_id')->constrained('event_type_scopes')->restrictOnDelete();
            $table->string('key', 96);
            $table->string('subject', 24);
            $table->string('label_key', 180);
            $table->string('unit', 32)->nullable();
            $table->string('value_type', 24);
            $table->string('aggregation', 24);
            $table->string('dimension_kind', 32)->nullable();
            $table->boolean('is_primary')->default(false);
            $table->boolean('is_contribution_metric')->default(true);
            $table->boolean('higher_is_better')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['event_type_scope_id', 'subject', 'key']);
            $table->index(['event_type_scope_id', 'subject', 'is_contribution_metric', 'sort_order']);
        });

        Schema::create('event_player_contexts', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('occurrence_id')->constrained('event_occurrences')->restrictOnDelete();
            $table->foreignUlid('player_id')->constrained('players')->restrictOnDelete();
            $table->string('player_name_snapshot', 160);
            $table->foreignUlid('represented_alliance_id')->nullable()->constrained('alliances')->restrictOnDelete();
            $table->string('represented_alliance_name_snapshot', 160)->nullable();
            $table->string('represented_alliance_tag_snapshot', 32)->nullable();
            $table->foreignUlid('kingdom_id_at_event')->constrained('kingdoms')->restrictOnDelete();
            $table->timestamp('context_frozen_at');
            $table->timestamps();

            $table->unique(['occurrence_id', 'player_id']);
            $table->index(['player_id', 'context_frozen_at']);
            $table->index(['represented_alliance_id', 'occurrence_id']);
            $table->index(['kingdom_id_at_event', 'occurrence_id']);
        });

        Schema::create('event_results', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('occurrence_id')->unique()->constrained('event_occurrences')->restrictOnDelete();
            $table->string('outcome', 80)->nullable();
            $table->unsignedBigInteger('score')->nullable();
            $table->unsignedBigInteger('opponent_score')->nullable();
            $table->unsignedInteger('rank')->nullable();
            $table->text('notes')->nullable();
            $table->foreignUlid('recorded_by_player_id')->nullable()->constrained('players')->restrictOnDelete();
            $table->timestamp('recorded_at');
            $table->timestamps();
        });

        Schema::create('event_alliance_results', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('occurrence_id')->constrained('event_occurrences')->restrictOnDelete();
            $table->foreignUlid('alliance_id')->constrained('alliances')->restrictOnDelete();
            $table->string('alliance_name_snapshot', 160);
            $table->string('alliance_tag_snapshot', 32)->nullable();
            $table->string('outcome', 80)->nullable();
            $table->unsignedBigInteger('score')->nullable();
            $table->unsignedInteger('rank')->nullable();
            $table->text('notes')->nullable();
            $table->foreignUlid('recorded_by_player_id')->nullable()->constrained('players')->restrictOnDelete();
            $table->timestamp('recorded_at');
            $table->timestamps();

            $table->unique(['occurrence_id', 'alliance_id']);
            $table->index(['alliance_id', 'recorded_at']);
        });

        Schema::create('event_player_results', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('occurrence_id')->constrained('event_occurrences')->restrictOnDelete();
            $table->foreignUlid('player_id')->constrained('players')->restrictOnDelete();
            $table->string('outcome', 80)->nullable();
            $table->unsignedBigInteger('score')->nullable();
            $table->unsignedInteger('rank')->nullable();
            $table->text('notes')->nullable();
            $table->foreignUlid('recorded_by_player_id')->nullable()->constrained('players')->restrictOnDelete();
            $table->timestamp('recorded_at');
            $table->timestamps();

            $table->unique(['occurrence_id', 'player_id']);
            $table->index(['player_id', 'recorded_at']);
        });

        Schema::create('event_result_metrics', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('event_result_id')->constrained('event_results')->cascadeOnDelete();
            $table->foreignUlid('metric_definition_id')->constrained('event_metric_definitions')->restrictOnDelete();
            $table->string('dimension_key', 96)->default('');
            $table->decimal('value', 30, 4);
            $table->string('source', 24);
            $table->foreignUlid('recorded_by_player_id')->nullable()->constrained('players')->restrictOnDelete();
            $table->timestamp('recorded_at');
            $table->timestamps();

            $table->unique(['event_result_id', 'metric_definition_id', 'dimension_key']);
            $table->index(['metric_definition_id', 'recorded_at']);
        });

        Schema::create('event_alliance_result_metrics', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('event_alliance_result_id')->constrained('event_alliance_results')->cascadeOnDelete();
            $table->foreignUlid('metric_definition_id')->constrained('event_metric_definitions')->restrictOnDelete();
            $table->string('dimension_key', 96)->default('');
            $table->decimal('value', 30, 4);
            $table->string('source', 24);
            $table->foreignUlid('recorded_by_player_id')->nullable()->constrained('players')->restrictOnDelete();
            $table->timestamp('recorded_at');
            $table->timestamps();

            $table->unique(['event_alliance_result_id', 'metric_definition_id', 'dimension_key']);
            $table->index(['metric_definition_id', 'recorded_at']);
        });

        Schema::create('event_player_result_metrics', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('event_player_result_id')->constrained('event_player_results')->cascadeOnDelete();
            $table->foreignUlid('metric_definition_id')->constrained('event_metric_definitions')->restrictOnDelete();
            $table->string('dimension_key', 96)->default('');
            $table->decimal('value', 30, 4);
            $table->string('source', 24);
            $table->foreignUlid('recorded_by_player_id')->nullable()->constrained('players')->restrictOnDelete();
            $table->timestamp('recorded_at');
            $table->timestamps();

            $table->unique(['event_player_result_id', 'metric_definition_id', 'dimension_key']);
            $table->index(['metric_definition_id', 'recorded_at']);
        });

        $this->createAllianceKingdomHistoryGuards();
    }

    private function createAllianceKingdomHistoryGuards(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('DROP FUNCTION IF EXISTS event_alliance_results_validate_kingdom()');
            DB::statement(<<<'SQL'
CREATE FUNCTION event_alliance_results_validate_kingdom() RETURNS trigger AS $$
BEGIN
    IF NOT EXISTS (
        SELECT 1
        FROM event_occurrences o
        JOIN events e ON e.id = o.event_id
        JOIN alliances a ON a.id = NEW.alliance_id
        WHERE o.id = NEW.occurrence_id
          AND e.scope = 'kingdom'
          AND e.kingdom_id = a.kingdom_id
    ) THEN
        RAISE EXCEPTION 'kingdom event alliance result must belong to the event kingdom';
    END IF;

    RETURN NEW;
END;
$$ LANGUAGE plpgsql
SQL);
            DB::statement(<<<'SQL'
CREATE TRIGGER event_alliance_results_kingdom_insert
BEFORE INSERT ON event_alliance_results
FOR EACH ROW EXECUTE FUNCTION event_alliance_results_validate_kingdom()
SQL);
            DB::statement(<<<'SQL'
CREATE TRIGGER event_alliance_results_kingdom_update
BEFORE UPDATE OF occurrence_id, alliance_id ON event_alliance_results
FOR EACH ROW EXECUTE FUNCTION event_alliance_results_validate_kingdom()
SQL);
            DB::statement('DROP FUNCTION IF EXISTS event_player_contexts_validate_alliance_kingdom()');
            DB::statement(<<<'SQL'
CREATE FUNCTION event_player_contexts_validate_alliance_kingdom() RETURNS trigger AS $$
BEGIN
    IF NEW.represented_alliance_id IS NOT NULL AND NOT EXISTS (
        SELECT 1
        FROM alliances a
        WHERE a.id = NEW.represented_alliance_id
          AND a.kingdom_id = NEW.kingdom_id_at_event
    ) THEN
        RAISE EXCEPTION 'represented alliance must belong to kingdom at event';
    END IF;

    RETURN NEW;
END;
$$ LANGUAGE plpgsql
SQL);
            DB::statement(<<<'SQL'
CREATE TRIGGER event_player_contexts_alliance_kingdom_insert
BEFORE INSERT ON event_player_contexts
FOR EACH ROW EXECUTE FUNCTION event_player_contexts_validate_alliance_kingdom()
SQL);
            DB::statement(<<<'SQL'
CREATE TRIGGER event_player_contexts_alliance_kingdom_update
BEFORE UPDATE OF represented_alliance_id, kingdom_id_at_event ON event_player_contexts
FOR EACH ROW EXECUTE FUNCTION event_player_contexts_validate_alliance_kingdom()
SQL);

            return;
        }

        if ($driver === 'sqlite') {
            DB::statement(<<<'SQL'
CREATE TRIGGER event_alliance_results_kingdom_insert
BEFORE INSERT ON event_alliance_results
WHEN NOT EXISTS (
    SELECT 1
    FROM event_occurrences o
    JOIN events e ON e.id = o.event_id
    JOIN alliances a ON a.id = NEW.alliance_id
    WHERE o.id = NEW.occurrence_id
      AND e.scope = 'kingdom'
      AND e.kingdom_id = a.kingdom_id
)
BEGIN
    SELECT RAISE(ABORT, 'kingdom event alliance result must belong to the event kingdom');
END
SQL);
            DB::statement(<<<'SQL'
CREATE TRIGGER event_alliance_results_kingdom_update
BEFORE UPDATE OF occurrence_id, alliance_id ON event_alliance_results
WHEN NOT EXISTS (
    SELECT 1
    FROM event_occurrences o
    JOIN events e ON e.id = o.event_id
    JOIN alliances a ON a.id = NEW.alliance_id
    WHERE o.id = NEW.occurrence_id
      AND e.scope = 'kingdom'
      AND e.kingdom_id = a.kingdom_id
)
BEGIN
    SELECT RAISE(ABORT, 'kingdom event alliance result must belong to the event kingdom');
END
SQL);
            DB::statement(<<<'SQL'
CREATE TRIGGER event_player_contexts_alliance_kingdom_insert
BEFORE INSERT ON event_player_contexts
WHEN NEW.represented_alliance_id IS NOT NULL
 AND NOT EXISTS (
    SELECT 1
    FROM alliances a
    WHERE a.id = NEW.represented_alliance_id
      AND a.kingdom_id = NEW.kingdom_id_at_event
 )
BEGIN
    SELECT RAISE(ABORT, 'represented alliance must belong to kingdom at event');
END
SQL);
            DB::statement(<<<'SQL'
CREATE TRIGGER event_player_contexts_alliance_kingdom_update
BEFORE UPDATE OF represented_alliance_id, kingdom_id_at_event ON event_player_contexts
WHEN NEW.represented_alliance_id IS NOT NULL
 AND NOT EXISTS (
    SELECT 1
    FROM alliances a
    WHERE a.id = NEW.represented_alliance_id
      AND a.kingdom_id = NEW.kingdom_id_at_event
 )
BEGIN
    SELECT RAISE(ABORT, 'represented alliance must belong to kingdom at event');
END
SQL);
        }
    }

    public function down(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('DROP TRIGGER IF EXISTS event_player_contexts_alliance_kingdom_update ON event_player_contexts');
            DB::statement('DROP TRIGGER IF EXISTS event_player_contexts_alliance_kingdom_insert ON event_player_contexts');
            DB::statement('DROP FUNCTION IF EXISTS event_player_contexts_validate_alliance_kingdom()');
            DB::statement('DROP TRIGGER IF EXISTS event_alliance_results_kingdom_update ON event_alliance_results');
            DB::statement('DROP TRIGGER IF EXISTS event_alliance_results_kingdom_insert ON event_alliance_results');
            DB::statement('DROP FUNCTION IF EXISTS event_alliance_results_validate_kingdom()');
        }

        if ($driver === 'sqlite') {
            DB::statement('DROP TRIGGER IF EXISTS event_player_contexts_alliance_kingdom_update');
            DB::statement('DROP TRIGGER IF EXISTS event_player_contexts_alliance_kingdom_insert');
            DB::statement('DROP TRIGGER IF EXISTS event_alliance_results_kingdom_update');
            DB::statement('DROP TRIGGER IF EXISTS event_alliance_results_kingdom_insert');
        }

        Schema::dropIfExists('event_player_result_metrics');
        Schema::dropIfExists('event_alliance_result_metrics');
        Schema::dropIfExists('event_result_metrics');
        Schema::dropIfExists('event_player_results');
        Schema::dropIfExists('event_alliance_results');
        Schema::dropIfExists('event_results');
        Schema::dropIfExists('event_player_contexts');
        Schema::dropIfExists('event_metric_definitions');
    }
};