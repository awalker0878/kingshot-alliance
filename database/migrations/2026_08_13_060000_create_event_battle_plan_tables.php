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
        Schema::create('event_objectives', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('occurrence_id')->constrained('event_occurrences')->cascadeOnDelete();
            $table->ulid('parent_id')->nullable();
            $table->string('objective_type', 64)->default('custom');
            $table->string('name', 160);
            $table->text('description')->nullable();
            $table->unsignedTinyInteger('priority')->default(50);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->string('status', 24)->default('planned');
            $table->unsignedInteger('sort_order')->default(0);
            $table->json('metadata')->nullable();
            $table->foreignUlid('created_by_player_id')->nullable()->constrained('players')->restrictOnDelete();
            $table->foreignUlid('updated_by_player_id')->nullable()->constrained('players')->nullOnDelete();
            $table->timestamps();

            $table->unique(['id', 'occurrence_id'], 'event_objectives_id_occurrence_unique');
            $table->foreign(['parent_id', 'occurrence_id'], 'event_objectives_parent_occurrence_foreign')
                ->references(['id', 'occurrence_id'])->on('event_objectives')->cascadeOnDelete();
            $table->index(['occurrence_id', 'status', 'priority']);
            $table->index(['occurrence_id', 'sort_order']);
        });

        Schema::create('event_objective_assignments', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->ulid('objective_id');
            $table->foreignUlid('occurrence_id')->constrained('event_occurrences')->cascadeOnDelete();
            $table->ulid('roster_id')->nullable();
            $table->foreignUlid('player_id')->nullable()->constrained('players')->restrictOnDelete();
            $table->foreignUlid('assigned_by_player_id')->nullable()->constrained('players')->restrictOnDelete();
            $table->timestamp('assigned_at');
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->foreign(['objective_id', 'occurrence_id'], 'event_objective_assignments_objective_context_foreign')
                ->references(['id', 'occurrence_id'])->on('event_objectives')->cascadeOnDelete();
            $table->foreign(['roster_id', 'occurrence_id'], 'event_objective_assignments_roster_context_foreign')
                ->references(['id', 'occurrence_id'])->on('event_rosters')->cascadeOnDelete();
            $table->index(['objective_id', 'occurrence_id']);
            $table->index(['roster_id', 'occurrence_id']);
            $table->index(['player_id', 'occurrence_id']);
        });

        $this->createGuards();
    }

    private function createGuards(): void
    {
        $driver = DB::connection()->getDriverName();
        if (in_array($driver, ['pgsql', 'sqlite'], true)) {
            DB::statement('CREATE UNIQUE INDEX event_objective_assignments_roster_unique ON event_objective_assignments (objective_id, roster_id) WHERE roster_id IS NOT NULL');
            DB::statement('CREATE UNIQUE INDEX event_objective_assignments_player_unique ON event_objective_assignments (objective_id, player_id) WHERE player_id IS NOT NULL');
        }

        if ($driver === 'pgsql') {
            DB::statement("ALTER TABLE event_objectives ADD CONSTRAINT event_objectives_status_check CHECK (status IN ('planned','active','completed','failed','cancelled'))");
            DB::statement('ALTER TABLE event_objectives ADD CONSTRAINT event_objectives_parent_self_check CHECK (parent_id IS NULL OR parent_id <> id)');
            DB::statement('ALTER TABLE event_objectives ADD CONSTRAINT event_objectives_priority_check CHECK (priority BETWEEN 0 AND 100)');
            DB::statement('ALTER TABLE event_objectives ADD CONSTRAINT event_objectives_time_check CHECK (ends_at IS NULL OR starts_at IS NULL OR ends_at >= starts_at)');
            DB::statement('ALTER TABLE event_objective_assignments ADD CONSTRAINT event_objective_assignments_target_check CHECK ((roster_id IS NOT NULL)::int + (player_id IS NOT NULL)::int = 1)');
            return;
        }

        if ($driver === 'sqlite') {
            DB::statement("CREATE TRIGGER event_objectives_status_insert BEFORE INSERT ON event_objectives WHEN NEW.status NOT IN ('planned','active','completed','failed','cancelled') BEGIN SELECT RAISE(ABORT, 'invalid objective status'); END");
            DB::statement("CREATE TRIGGER event_objectives_parent_self_insert BEFORE INSERT ON event_objectives WHEN NEW.parent_id IS NOT NULL AND NEW.parent_id = NEW.id BEGIN SELECT RAISE(ABORT, 'objective cannot parent itself'); END");
            DB::statement("CREATE TRIGGER event_objectives_parent_self_update BEFORE UPDATE OF parent_id ON event_objectives WHEN NEW.parent_id IS NOT NULL AND NEW.parent_id = NEW.id BEGIN SELECT RAISE(ABORT, 'objective cannot parent itself'); END");
            DB::statement("CREATE TRIGGER event_objectives_status_update BEFORE UPDATE OF status ON event_objectives WHEN NEW.status NOT IN ('planned','active','completed','failed','cancelled') BEGIN SELECT RAISE(ABORT, 'invalid objective status'); END");
            DB::statement("CREATE TRIGGER event_objectives_priority_insert BEFORE INSERT ON event_objectives WHEN NEW.priority < 0 OR NEW.priority > 100 BEGIN SELECT RAISE(ABORT, 'invalid objective priority'); END");
            DB::statement("CREATE TRIGGER event_objectives_priority_update BEFORE UPDATE OF priority ON event_objectives WHEN NEW.priority < 0 OR NEW.priority > 100 BEGIN SELECT RAISE(ABORT, 'invalid objective priority'); END");
            DB::statement("CREATE TRIGGER event_objectives_time_insert BEFORE INSERT ON event_objectives WHEN NEW.ends_at IS NOT NULL AND NEW.starts_at IS NOT NULL AND NEW.ends_at < NEW.starts_at BEGIN SELECT RAISE(ABORT, 'invalid objective time window'); END");
            DB::statement("CREATE TRIGGER event_objectives_time_update BEFORE UPDATE OF starts_at, ends_at ON event_objectives WHEN NEW.ends_at IS NOT NULL AND NEW.starts_at IS NOT NULL AND NEW.ends_at < NEW.starts_at BEGIN SELECT RAISE(ABORT, 'invalid objective time window'); END");
            DB::statement("CREATE TRIGGER event_objective_assignments_target_insert BEFORE INSERT ON event_objective_assignments WHEN ((NEW.roster_id IS NOT NULL) + (NEW.player_id IS NOT NULL)) <> 1 BEGIN SELECT RAISE(ABORT, 'objective assignment requires exactly one target'); END");
            DB::statement("CREATE TRIGGER event_objective_assignments_target_update BEFORE UPDATE OF roster_id, player_id ON event_objective_assignments WHEN ((NEW.roster_id IS NOT NULL) + (NEW.player_id IS NOT NULL)) <> 1 BEGIN SELECT RAISE(ABORT, 'objective assignment requires exactly one target'); END");
        }
    }

    public function down(): void
    {
        $driver = DB::connection()->getDriverName();
        if (in_array($driver, ['pgsql', 'sqlite'], true)) {
            DB::statement('DROP INDEX IF EXISTS event_objective_assignments_roster_unique');
            DB::statement('DROP INDEX IF EXISTS event_objective_assignments_player_unique');
        }
        if ($driver === 'sqlite') {
            foreach ([
                'event_objectives_status_insert', 'event_objectives_status_update',
                'event_objectives_parent_self_insert', 'event_objectives_parent_self_update',
                'event_objectives_priority_insert', 'event_objectives_priority_update',
                'event_objectives_time_insert', 'event_objectives_time_update',
                'event_objective_assignments_target_insert', 'event_objective_assignments_target_update',
            ] as $trigger) {
                DB::statement('DROP TRIGGER IF EXISTS '.$trigger);
            }
        }
        Schema::dropIfExists('event_objective_assignments');
        Schema::dropIfExists('event_objectives');
    }
};
