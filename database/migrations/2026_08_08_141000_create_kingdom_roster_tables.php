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
        Schema::create('alliance_roster_entries', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('alliance_id')->constrained('alliances')->cascadeOnDelete();
            $table->foreignUlid('player_id')->constrained('players')->restrictOnDelete();
            $table->string('observed_name', 160);
            $table->string('game_role', 64)->nullable();
            $table->string('state', 24)->default('active')->index();
            $table->date('joined_at')->nullable();
            $table->timestampTz('left_at')->nullable();
            $table->text('manager_notes')->nullable();
            $table->timestampTz('last_observed_at')->nullable();
            $table->string('source', 24)->default('manual');
            $table->timestamps();

            $table->unique(['alliance_id', 'player_id']);
            $table->index(['alliance_id', 'state', 'observed_name']);
            $table->index(['alliance_id', 'last_observed_at']);
        });

        $driver = DB::connection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('DROP FUNCTION IF EXISTS alliance_roster_entries_validate_kingdom()');
            DB::statement('DROP FUNCTION IF EXISTS players_prevent_active_roster_kingdom_mismatch()');
            DB::statement("CREATE FUNCTION alliance_roster_entries_validate_kingdom() RETURNS trigger AS $$ BEGIN IF NEW.state IN ('active', 'tracked') AND NOT EXISTS (SELECT 1 FROM players p JOIN alliances a ON a.id = NEW.alliance_id WHERE p.id = NEW.player_id AND p.current_kingdom_id = a.kingdom_id) THEN RAISE EXCEPTION 'current roster player kingdom must match alliance kingdom'; END IF; RETURN NEW; END; $$ LANGUAGE plpgsql");
            DB::statement("CREATE TRIGGER alliance_roster_entries_kingdom_insert BEFORE INSERT ON alliance_roster_entries FOR EACH ROW EXECUTE FUNCTION alliance_roster_entries_validate_kingdom()");
            DB::statement("CREATE TRIGGER alliance_roster_entries_kingdom_update BEFORE UPDATE OF alliance_id, player_id, state ON alliance_roster_entries FOR EACH ROW EXECUTE FUNCTION alliance_roster_entries_validate_kingdom()");
            DB::statement("CREATE FUNCTION players_prevent_active_roster_kingdom_mismatch() RETURNS trigger AS $$ BEGIN IF NEW.current_kingdom_id IS DISTINCT FROM OLD.current_kingdom_id AND EXISTS (SELECT 1 FROM alliance_roster_entries r JOIN alliances a ON a.id = r.alliance_id WHERE r.player_id = NEW.id AND r.state IN ('active', 'tracked') AND a.kingdom_id <> NEW.current_kingdom_id) THEN RAISE EXCEPTION 'leave current alliance rosters before changing player kingdom'; END IF; RETURN NEW; END; $$ LANGUAGE plpgsql");
            DB::statement("CREATE TRIGGER players_active_roster_kingdom_guard BEFORE UPDATE OF current_kingdom_id ON players FOR EACH ROW EXECUTE FUNCTION players_prevent_active_roster_kingdom_mismatch()");
        }

        if ($driver === 'sqlite') {
            $mismatch = "NEW.state IN ('active', 'tracked') AND NOT EXISTS (SELECT 1 FROM players p JOIN alliances a ON a.id = NEW.alliance_id WHERE p.id = NEW.player_id AND p.current_kingdom_id = a.kingdom_id)";
            DB::statement("CREATE TRIGGER alliance_roster_entries_kingdom_insert BEFORE INSERT ON alliance_roster_entries WHEN {$mismatch} BEGIN SELECT RAISE(ABORT, 'current roster player kingdom must match alliance kingdom'); END");
            DB::statement("CREATE TRIGGER alliance_roster_entries_kingdom_update BEFORE UPDATE OF alliance_id, player_id, state ON alliance_roster_entries WHEN {$mismatch} BEGIN SELECT RAISE(ABORT, 'current roster player kingdom must match alliance kingdom'); END");
            DB::statement("CREATE TRIGGER players_active_roster_kingdom_guard BEFORE UPDATE OF current_kingdom_id ON players WHEN EXISTS (SELECT 1 FROM alliance_roster_entries r JOIN alliances a ON a.id = r.alliance_id WHERE r.player_id = NEW.id AND r.state IN ('active', 'tracked') AND a.kingdom_id <> NEW.current_kingdom_id) BEGIN SELECT RAISE(ABORT, 'leave current alliance rosters before changing player kingdom'); END");
        }
    }

    public function down(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('DROP TRIGGER IF EXISTS players_active_roster_kingdom_guard ON players');
            DB::statement('DROP FUNCTION IF EXISTS players_prevent_active_roster_kingdom_mismatch()');
            DB::statement('DROP TRIGGER IF EXISTS alliance_roster_entries_kingdom_update ON alliance_roster_entries');
            DB::statement('DROP TRIGGER IF EXISTS alliance_roster_entries_kingdom_insert ON alliance_roster_entries');
            DB::statement('DROP FUNCTION IF EXISTS alliance_roster_entries_validate_kingdom()');
        }

        if ($driver === 'sqlite') {
            DB::statement('DROP TRIGGER IF EXISTS players_active_roster_kingdom_guard');
            DB::statement('DROP TRIGGER IF EXISTS alliance_roster_entries_kingdom_update');
            DB::statement('DROP TRIGGER IF EXISTS alliance_roster_entries_kingdom_insert');
        }

        Schema::dropIfExists('alliance_roster_entries');
    }
};
