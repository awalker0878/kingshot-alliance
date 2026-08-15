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
        Schema::create('kingdom_roles', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('kingdom_id')->constrained('kingdoms')->cascadeOnDelete();
            $table->string('key', 64);
            $table->string('name', 100);
            $table->boolean('is_system')->default(true);
            $table->timestamps();

            $table->unique(['kingdom_id', 'key']);
            $table->unique(['id', 'kingdom_id']);
        });

        Schema::create('kingdom_role_permissions', function (Blueprint $table): void {
            $table->foreignUlid('kingdom_role_id')->constrained('kingdom_roles')->cascadeOnDelete();
            $table->foreignUlid('permission_id')->constrained('permissions')->cascadeOnDelete();
            $table->primary(['kingdom_role_id', 'permission_id']);
        });

        Schema::create('kingdom_role_assignments', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->ulid('kingdom_id');
            $table->foreignUlid('player_id')->constrained('players')->cascadeOnDelete();
            $table->ulid('kingdom_role_id');
            $table->timestamps();

            $table->unique(['kingdom_id', 'player_id', 'kingdom_role_id'], 'kingdom_role_assignments_unique');
            $table->index(['player_id', 'kingdom_id']);
            $table->index(['kingdom_id', 'kingdom_role_id']);

            $table->foreign('kingdom_id')->references('id')->on('kingdoms')->cascadeOnDelete();
            $table->foreign(['kingdom_role_id', 'kingdom_id'])
                ->references(['id', 'kingdom_id'])
                ->on('kingdom_roles')
                ->cascadeOnDelete();
        });

        $driver = DB::connection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('DROP FUNCTION IF EXISTS kingdom_role_assignments_validate_player_kingdom()');
            DB::statement('DROP FUNCTION IF EXISTS players_prevent_kingdom_role_drift()');
            DB::statement("CREATE FUNCTION kingdom_role_assignments_validate_player_kingdom() RETURNS trigger AS $$ BEGIN IF NOT EXISTS (SELECT 1 FROM players p WHERE p.id = NEW.player_id AND p.current_kingdom_id = NEW.kingdom_id) THEN RAISE EXCEPTION 'kingdom role player must currently belong to the role kingdom'; END IF; RETURN NEW; END; $$ LANGUAGE plpgsql");
            DB::statement('CREATE TRIGGER kingdom_role_assignments_player_kingdom_insert BEFORE INSERT ON kingdom_role_assignments FOR EACH ROW EXECUTE FUNCTION kingdom_role_assignments_validate_player_kingdom()');
            DB::statement('CREATE TRIGGER kingdom_role_assignments_player_kingdom_update BEFORE UPDATE OF kingdom_id, player_id ON kingdom_role_assignments FOR EACH ROW EXECUTE FUNCTION kingdom_role_assignments_validate_player_kingdom()');
            DB::statement("CREATE FUNCTION players_prevent_kingdom_role_drift() RETURNS trigger AS $$ BEGIN IF NEW.current_kingdom_id IS DISTINCT FROM OLD.current_kingdom_id AND EXISTS (SELECT 1 FROM kingdom_role_assignments a WHERE a.player_id = NEW.id AND a.kingdom_id <> NEW.current_kingdom_id) THEN RAISE EXCEPTION 'remove kingdom roles before changing player kingdom'; END IF; RETURN NEW; END; $$ LANGUAGE plpgsql");
            DB::statement('CREATE TRIGGER players_kingdom_role_guard BEFORE UPDATE OF current_kingdom_id ON players FOR EACH ROW EXECUTE FUNCTION players_prevent_kingdom_role_drift()');
        }

        if ($driver === 'sqlite') {
            $mismatch = 'NOT EXISTS (SELECT 1 FROM players p WHERE p.id = NEW.player_id AND p.current_kingdom_id = NEW.kingdom_id)';
            DB::statement("CREATE TRIGGER kingdom_role_assignments_player_kingdom_insert BEFORE INSERT ON kingdom_role_assignments WHEN {$mismatch} BEGIN SELECT RAISE(ABORT, 'kingdom role player must currently belong to the role kingdom'); END");
            DB::statement("CREATE TRIGGER kingdom_role_assignments_player_kingdom_update BEFORE UPDATE OF kingdom_id, player_id ON kingdom_role_assignments WHEN {$mismatch} BEGIN SELECT RAISE(ABORT, 'kingdom role player must currently belong to the role kingdom'); END");
            DB::statement("CREATE TRIGGER players_kingdom_role_guard BEFORE UPDATE OF current_kingdom_id ON players WHEN EXISTS (SELECT 1 FROM kingdom_role_assignments a WHERE a.player_id = NEW.id AND a.kingdom_id <> NEW.current_kingdom_id) BEGIN SELECT RAISE(ABORT, 'remove kingdom roles before changing player kingdom'); END");
        }
    }

    public function down(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('DROP TRIGGER IF EXISTS players_kingdom_role_guard ON players');
            DB::statement('DROP FUNCTION IF EXISTS players_prevent_kingdom_role_drift()');
            DB::statement('DROP TRIGGER IF EXISTS kingdom_role_assignments_player_kingdom_update ON kingdom_role_assignments');
            DB::statement('DROP TRIGGER IF EXISTS kingdom_role_assignments_player_kingdom_insert ON kingdom_role_assignments');
            DB::statement('DROP FUNCTION IF EXISTS kingdom_role_assignments_validate_player_kingdom()');
        }

        if ($driver === 'sqlite') {
            DB::statement('DROP TRIGGER IF EXISTS players_kingdom_role_guard');
            DB::statement('DROP TRIGGER IF EXISTS kingdom_role_assignments_player_kingdom_update');
            DB::statement('DROP TRIGGER IF EXISTS kingdom_role_assignments_player_kingdom_insert');
        }

        Schema::dropIfExists('kingdom_role_assignments');
        Schema::dropIfExists('kingdom_role_permissions');
        Schema::dropIfExists('kingdom_roles');
    }
};
