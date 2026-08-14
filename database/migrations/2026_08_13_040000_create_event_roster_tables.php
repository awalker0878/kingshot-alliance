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
        Schema::create('event_rosters', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('occurrence_id')->constrained('event_occurrences')->cascadeOnDelete();
            $table->ulid('parent_id')->nullable();
            $table->string('key', 64);
            $table->string('name_key', 180)->nullable();
            $table->string('name', 160)->nullable();
            $table->string('roster_type', 24)->default('roster');
            $table->string('assignment_group', 64)->default('primary');
            $table->unsignedInteger('capacity')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->json('settings')->nullable();
            $table->foreignUlid('created_by_player_id')->nullable()->constrained('players')->nullOnDelete();
            $table->foreignUlid('updated_by_player_id')->nullable()->constrained('players')->nullOnDelete();
            $table->timestamps();

            $table->unique(['occurrence_id', 'key']);
            $table->unique(['id', 'occurrence_id'], 'event_rosters_id_occurrence_unique');
            $table->foreign(['parent_id', 'occurrence_id'], 'event_rosters_parent_occurrence_foreign')
                ->references(['id', 'occurrence_id'])
                ->on('event_rosters')
                ->cascadeOnDelete();
            $table->index(['occurrence_id', 'assignment_group']);
            $table->index(['occurrence_id', 'sort_order']);
        });

        Schema::create('event_roster_members', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('roster_id')->constrained('event_rosters')->cascadeOnDelete();
            $table->foreignUlid('player_id')->constrained('players')->restrictOnDelete();
            $table->foreignUlid('alliance_id')->nullable()->constrained('alliances')->nullOnDelete();
            $table->string('role', 80)->nullable();
            $table->unsignedInteger('slot_number')->nullable();
            $table->string('status', 24)->default('assigned');
            $table->json('assignment_warnings')->nullable();
            $table->foreignUlid('assigned_by_player_id')->constrained('players')->restrictOnDelete();
            $table->timestamp('assigned_at');
            $table->foreignUlid('responded_by_player_id')->nullable()->constrained('players')->nullOnDelete();
            $table->timestamp('responded_at')->nullable();
            $table->foreignUlid('removed_by_player_id')->nullable()->constrained('players')->nullOnDelete();
            $table->timestamp('removed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['roster_id', 'player_id']);
            $table->index(['roster_id', 'status']);
            $table->index(['player_id', 'status']);
            $table->index(['alliance_id', 'status']);
        });

        $driver = DB::connection()->getDriverName();
        if (in_array($driver, ['pgsql', 'sqlite'], true)) {
            DB::statement("CREATE UNIQUE INDEX event_roster_members_active_slot_unique ON event_roster_members (roster_id, slot_number) WHERE slot_number IS NOT NULL AND status NOT IN ('declined', 'removed')");
        }

        $this->createContentGuards();
        $this->createStatusGuards();
    }

    private function createContentGuards(): void
    {
        $driver = DB::connection()->getDriverName();
        $nameExpression = "((name_key IS NOT NULL AND length(trim(name_key)) > 0) OR (name IS NOT NULL AND length(trim(name)) > 0))";
        $sqliteNameExpression = "((NEW.name_key IS NOT NULL AND length(trim(NEW.name_key)) > 0) OR (NEW.name IS NOT NULL AND length(trim(NEW.name)) > 0))";

        if ($driver === 'pgsql') {
            DB::statement("ALTER TABLE event_rosters ADD CONSTRAINT event_rosters_name_check CHECK ({$nameExpression})");
            DB::statement("ALTER TABLE event_rosters ADD CONSTRAINT event_rosters_type_check CHECK (roster_type IN ('roster','combatants','substitutes','team','legion'))");
            DB::statement("ALTER TABLE event_roster_members ADD CONSTRAINT event_roster_members_status_check CHECK (status IN ('assigned','confirmed','declined','removed','participated','absent'))");

            return;
        }

        if ($driver === 'sqlite') {
            DB::statement("CREATE TRIGGER event_rosters_name_insert BEFORE INSERT ON event_rosters WHEN NOT {$sqliteNameExpression} BEGIN SELECT RAISE(ABORT, 'event roster name required'); END");
            DB::statement("CREATE TRIGGER event_rosters_name_update BEFORE UPDATE OF name_key, name ON event_rosters WHEN NOT {$sqliteNameExpression} BEGIN SELECT RAISE(ABORT, 'event roster name required'); END");
            DB::statement("CREATE TRIGGER event_rosters_type_insert BEFORE INSERT ON event_rosters WHEN NEW.roster_type NOT IN ('roster','combatants','substitutes','team','legion') BEGIN SELECT RAISE(ABORT, 'invalid event roster type'); END");
            DB::statement("CREATE TRIGGER event_rosters_type_update BEFORE UPDATE OF roster_type ON event_rosters WHEN NEW.roster_type NOT IN ('roster','combatants','substitutes','team','legion') BEGIN SELECT RAISE(ABORT, 'invalid event roster type'); END");
            DB::statement("CREATE TRIGGER event_roster_members_status_insert BEFORE INSERT ON event_roster_members WHEN NEW.status NOT IN ('assigned','confirmed','declined','removed','participated','absent') BEGIN SELECT RAISE(ABORT, 'invalid event roster member status'); END");
            DB::statement("CREATE TRIGGER event_roster_members_status_update BEFORE UPDATE OF status ON event_roster_members WHEN NEW.status NOT IN ('assigned','confirmed','declined','removed','participated','absent') BEGIN SELECT RAISE(ABORT, 'invalid event roster member status'); END");
        }
    }

    private function createStatusGuards(): void
    {
        $driver = DB::connection()->getDriverName();
        $responseExpression = "((status IN ('confirmed','declined') AND responded_by_player_id IS NOT NULL AND responded_at IS NOT NULL) OR (status NOT IN ('confirmed','declined')))";
        $removedExpression = "((status = 'removed' AND removed_by_player_id IS NOT NULL AND removed_at IS NOT NULL) OR (status <> 'removed' AND removed_at IS NULL))";
        $sqliteResponseExpression = "((NEW.status IN ('confirmed','declined') AND NEW.responded_by_player_id IS NOT NULL AND NEW.responded_at IS NOT NULL) OR (NEW.status NOT IN ('confirmed','declined')))";
        $sqliteRemovedExpression = "((NEW.status = 'removed' AND NEW.removed_by_player_id IS NOT NULL AND NEW.removed_at IS NOT NULL) OR (NEW.status <> 'removed' AND NEW.removed_at IS NULL))";

        if ($driver === 'pgsql') {
            DB::statement("ALTER TABLE event_roster_members ADD CONSTRAINT event_roster_members_response_check CHECK ({$responseExpression})");
            DB::statement("ALTER TABLE event_roster_members ADD CONSTRAINT event_roster_members_removed_check CHECK ({$removedExpression})");

            return;
        }

        if ($driver === 'sqlite') {
            DB::statement("CREATE TRIGGER event_roster_members_response_insert BEFORE INSERT ON event_roster_members WHEN NOT {$sqliteResponseExpression} BEGIN SELECT RAISE(ABORT, 'invalid roster response state'); END");
            DB::statement("CREATE TRIGGER event_roster_members_response_update BEFORE UPDATE OF status, responded_by_player_id, responded_at ON event_roster_members WHEN NOT {$sqliteResponseExpression} BEGIN SELECT RAISE(ABORT, 'invalid roster response state'); END");
            DB::statement("CREATE TRIGGER event_roster_members_removed_insert BEFORE INSERT ON event_roster_members WHEN NOT {$sqliteRemovedExpression} BEGIN SELECT RAISE(ABORT, 'invalid roster removal state'); END");
            DB::statement("CREATE TRIGGER event_roster_members_removed_update BEFORE UPDATE OF status, removed_by_player_id, removed_at ON event_roster_members WHEN NOT {$sqliteRemovedExpression} BEGIN SELECT RAISE(ABORT, 'invalid roster removal state'); END");
        }
    }

    public function down(): void
    {
        $driver = DB::connection()->getDriverName();
        if (in_array($driver, ['pgsql', 'sqlite'], true)) {
            DB::statement('DROP INDEX IF EXISTS event_roster_members_active_slot_unique');
        }
        if ($driver === 'sqlite') {
            foreach ([
                'event_rosters_name_insert', 'event_rosters_name_update', 'event_rosters_type_insert', 'event_rosters_type_update',
                'event_roster_members_status_insert', 'event_roster_members_status_update',
                'event_roster_members_response_insert', 'event_roster_members_response_update',
                'event_roster_members_removed_insert', 'event_roster_members_removed_update',
            ] as $trigger) {
                DB::statement('DROP TRIGGER IF EXISTS '.$trigger);
            }
        }

        Schema::dropIfExists('event_roster_members');
        Schema::dropIfExists('event_rosters');
    }
};
