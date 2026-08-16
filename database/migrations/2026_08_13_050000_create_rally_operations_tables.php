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
        Schema::create('player_formations', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('player_id')->constrained('players')->cascadeOnDelete();
            $table->string('name', 120);
            $table->unsignedTinyInteger('infantry_percent');
            $table->unsignedTinyInteger('cavalry_percent');
            $table->unsignedTinyInteger('archer_percent');
            $table->json('heroes')->nullable();
            $table->text('notes')->nullable();
            $table->boolean('is_default')->default(false);
            $table->foreignUlid('created_by_player_id')->nullable()->constrained('players')->nullOnDelete();
            $table->foreignUlid('updated_by_player_id')->nullable()->constrained('players')->nullOnDelete();
            $table->timestamps();

            $table->unique(['player_id', 'name']);
        });

        Schema::create('rally_guidance_rules', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('alliance_id')->constrained('alliances')->cascadeOnDelete();
            $table->string('name', 120);
            $table->unsignedTinyInteger('infantry_percent');
            $table->unsignedTinyInteger('cavalry_percent');
            $table->unsignedTinyInteger('archer_percent');
            $table->json('hero_recommendations')->nullable();
            $table->text('lead_requirements')->nullable();
            $table->text('joiner_guidance')->nullable();
            $table->string('source', 255)->nullable();
            $table->text('rationale')->nullable();
            $table->date('effective_from')->nullable();
            $table->date('effective_until')->nullable();
            $table->boolean('is_active')->default(true);
            $table->foreignUlid('created_by_player_id')->nullable()->constrained('players')->nullOnDelete();
            $table->foreignUlid('updated_by_player_id')->nullable()->constrained('players')->nullOnDelete();
            $table->timestamps();

            $table->unique(['alliance_id', 'name']);
            $table->unique(['id', 'alliance_id'], 'rally_guidance_rules_id_alliance_unique');
            $table->index(['alliance_id', 'is_active', 'effective_from']);
        });

        Schema::create('event_recommended_formations', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('occurrence_id')->constrained('event_occurrences')->cascadeOnDelete();
            $table->foreignUlid('alliance_id')->constrained('alliances')->cascadeOnDelete();
            $table->ulid('guidance_rule_id')->nullable();
            $table->string('key', 64);
            $table->string('name', 120);
            $table->string('assignment_role', 24)->nullable();
            $table->unsignedTinyInteger('infantry_percent');
            $table->unsignedTinyInteger('cavalry_percent');
            $table->unsignedTinyInteger('archer_percent');
            $table->json('heroes')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->foreignUlid('created_by_player_id')->nullable()->constrained('players')->nullOnDelete();
            $table->foreignUlid('updated_by_player_id')->nullable()->constrained('players')->nullOnDelete();
            $table->timestamps();

            $table->unique(['occurrence_id', 'alliance_id', 'key'], 'event_recommended_formations_context_key_unique');
            $table->unique(['id', 'occurrence_id', 'alliance_id'], 'event_recommended_formations_context_unique');
            $table->foreign(['guidance_rule_id', 'alliance_id'], 'event_recommended_formations_guidance_alliance_foreign')
                ->references(['id', 'alliance_id'])->on('rally_guidance_rules')->nullOnDelete();
            $table->index(['occurrence_id', 'alliance_id', 'sort_order']);
        });

        Schema::create('rally_groups', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('occurrence_id')->constrained('event_occurrences')->cascadeOnDelete();
            $table->foreignUlid('alliance_id')->constrained('alliances')->cascadeOnDelete();
            $table->ulid('recommended_formation_id')->nullable();
            $table->string('name', 120);
            $table->unsignedInteger('max_joiners')->nullable();
            $table->text('notes')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->foreignUlid('created_by_player_id')->nullable()->constrained('players')->nullOnDelete();
            $table->foreignUlid('updated_by_player_id')->nullable()->constrained('players')->nullOnDelete();
            $table->timestamps();

            $table->unique(['id', 'occurrence_id', 'alliance_id'], 'rally_groups_context_unique');
            $table->foreign(['recommended_formation_id', 'occurrence_id', 'alliance_id'], 'rally_groups_recommended_formation_context_foreign')
                ->references(['id', 'occurrence_id', 'alliance_id'])->on('event_recommended_formations')->nullOnDelete();
            $table->index(['occurrence_id', 'alliance_id', 'sort_order']);
        });

        Schema::create('rally_assignments', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('rally_group_id')->constrained('rally_groups')->cascadeOnDelete();
            $table->foreignUlid('player_id')->constrained('players')->restrictOnDelete();
            $table->string('role', 24);
            $table->unsignedInteger('slot_number')->nullable();
            $table->string('status', 24)->default('assigned');
            $table->foreignUlid('assigned_by_player_id')->constrained('players')->restrictOnDelete();
            $table->timestamp('assigned_at');
            $table->foreignUlid('responded_by_player_id')->nullable()->constrained('players')->nullOnDelete();
            $table->timestamp('responded_at')->nullable();
            $table->foreignUlid('recorded_by_player_id')->nullable()->constrained('players')->nullOnDelete();
            $table->timestamp('recorded_at')->nullable();
            $table->foreignUlid('removed_by_player_id')->nullable()->constrained('players')->nullOnDelete();
            $table->timestamp('removed_at')->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique(['rally_group_id', 'player_id']);
            $table->index(['rally_group_id', 'role', 'status']);
            $table->index(['player_id', 'status']);
        });

        $this->createIndexesAndGuards();
    }

    private function createIndexesAndGuards(): void
    {
        $driver = DB::connection()->getDriverName();
        if (in_array($driver, ['pgsql', 'sqlite'], true)) {
            DB::statement('CREATE UNIQUE INDEX player_formations_one_default_per_player ON player_formations (player_id) WHERE is_default = true');
            DB::statement("CREATE UNIQUE INDEX rally_assignments_active_lead_unique ON rally_assignments (rally_group_id) WHERE role = 'lead' AND status NOT IN ('declined','removed')");
            DB::statement("CREATE UNIQUE INDEX rally_assignments_active_slot_unique ON rally_assignments (rally_group_id, slot_number) WHERE slot_number IS NOT NULL AND status NOT IN ('declined','removed')");
        }

        $formationCheck = '(infantry_percent BETWEEN 0 AND 100 AND cavalry_percent BETWEEN 0 AND 100 AND archer_percent BETWEEN 0 AND 100 AND infantry_percent + cavalry_percent + archer_percent = 100)';
        if ($driver === 'pgsql') {
            foreach (['player_formations', 'rally_guidance_rules', 'event_recommended_formations'] as $table) {
                DB::statement("ALTER TABLE {$table} ADD CONSTRAINT {$table}_composition_check CHECK ({$formationCheck})");
            }
            DB::statement("ALTER TABLE rally_assignments ADD CONSTRAINT rally_assignments_role_check CHECK (role IN ('lead','joiner','standby'))");
            DB::statement("ALTER TABLE rally_assignments ADD CONSTRAINT rally_assignments_status_check CHECK (status IN ('assigned','confirmed','declined','participated','absent','removed'))");
            DB::statement('ALTER TABLE rally_guidance_rules ADD CONSTRAINT rally_guidance_rules_effective_window_check CHECK (effective_until IS NULL OR effective_from IS NULL OR effective_until >= effective_from)');

            return;
        }

        if ($driver === 'sqlite') {
            $sqliteComposition = '(NEW.infantry_percent BETWEEN 0 AND 100 AND NEW.cavalry_percent BETWEEN 0 AND 100 AND NEW.archer_percent BETWEEN 0 AND 100 AND NEW.infantry_percent + NEW.cavalry_percent + NEW.archer_percent = 100)';
            foreach (['player_formations', 'rally_guidance_rules', 'event_recommended_formations'] as $table) {
                DB::statement("CREATE TRIGGER {$table}_composition_insert BEFORE INSERT ON {$table} WHEN NOT {$sqliteComposition} BEGIN SELECT RAISE(ABORT, 'invalid formation composition'); END");
                DB::statement("CREATE TRIGGER {$table}_composition_update BEFORE UPDATE OF infantry_percent, cavalry_percent, archer_percent ON {$table} WHEN NOT {$sqliteComposition} BEGIN SELECT RAISE(ABORT, 'invalid formation composition'); END");
            }
            DB::statement("CREATE TRIGGER rally_assignments_role_insert BEFORE INSERT ON rally_assignments WHEN NEW.role NOT IN ('lead','joiner','standby') BEGIN SELECT RAISE(ABORT, 'invalid rally assignment role'); END");
            DB::statement("CREATE TRIGGER rally_assignments_role_update BEFORE UPDATE OF role ON rally_assignments WHEN NEW.role NOT IN ('lead','joiner','standby') BEGIN SELECT RAISE(ABORT, 'invalid rally assignment role'); END");
            DB::statement("CREATE TRIGGER rally_assignments_status_insert BEFORE INSERT ON rally_assignments WHEN NEW.status NOT IN ('assigned','confirmed','declined','participated','absent','removed') BEGIN SELECT RAISE(ABORT, 'invalid rally assignment status'); END");
            DB::statement("CREATE TRIGGER rally_assignments_status_update BEFORE UPDATE OF status ON rally_assignments WHEN NEW.status NOT IN ('assigned','confirmed','declined','participated','absent','removed') BEGIN SELECT RAISE(ABORT, 'invalid rally assignment status'); END");
            DB::statement("CREATE TRIGGER rally_guidance_rules_effective_window_insert BEFORE INSERT ON rally_guidance_rules WHEN NEW.effective_until IS NOT NULL AND NEW.effective_from IS NOT NULL AND NEW.effective_until < NEW.effective_from BEGIN SELECT RAISE(ABORT, 'invalid rally guidance effective window'); END");
            DB::statement("CREATE TRIGGER rally_guidance_rules_effective_window_update BEFORE UPDATE OF effective_from, effective_until ON rally_guidance_rules WHEN NEW.effective_until IS NOT NULL AND NEW.effective_from IS NOT NULL AND NEW.effective_until < NEW.effective_from BEGIN SELECT RAISE(ABORT, 'invalid rally guidance effective window'); END");
        }
    }

    public function down(): void
    {
        $driver = DB::connection()->getDriverName();
        if (in_array($driver, ['pgsql', 'sqlite'], true)) {
            DB::statement('DROP INDEX IF EXISTS player_formations_one_default_per_player');
            DB::statement('DROP INDEX IF EXISTS rally_assignments_active_lead_unique');
            DB::statement('DROP INDEX IF EXISTS rally_assignments_active_slot_unique');
        }
        if ($driver === 'sqlite') {
            foreach ([
                'player_formations_composition_insert', 'player_formations_composition_update',
                'rally_guidance_rules_composition_insert', 'rally_guidance_rules_composition_update',
                'event_recommended_formations_composition_insert', 'event_recommended_formations_composition_update',
                'rally_assignments_role_insert', 'rally_assignments_role_update',
                'rally_assignments_status_insert', 'rally_assignments_status_update',
                'rally_guidance_rules_effective_window_insert', 'rally_guidance_rules_effective_window_update',
            ] as $trigger) {
                DB::statement('DROP TRIGGER IF EXISTS '.$trigger);
            }
        }

        Schema::dropIfExists('rally_assignments');
        Schema::dropIfExists('rally_groups');
        Schema::dropIfExists('event_recommended_formations');
        Schema::dropIfExists('rally_guidance_rules');
        Schema::dropIfExists('player_formations');
    }
};
