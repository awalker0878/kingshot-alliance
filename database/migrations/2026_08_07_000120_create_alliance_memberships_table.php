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
        Schema::create('alliance_memberships', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('alliance_id')->constrained('alliances')->cascadeOnDelete();
            $table->foreignUlid('player_id')->constrained('players')->cascadeOnDelete();
            $table->string('status', 32)->default('invited')->index();
            $table->string('rank', 2)->default('r1')->index();
            $table->timestamp('joined_at')->nullable();
            $table->timestamp('left_at')->nullable();
            $table->timestamps();

            $table->unique(['alliance_id', 'player_id']);
            $table->unique(['id', 'alliance_id']);
            $table->index(['player_id', 'status']);
            $table->index(['alliance_id', 'status', 'rank']);
        });

        DB::statement("CREATE UNIQUE INDEX alliance_memberships_one_active_r5 ON alliance_memberships (alliance_id) WHERE status = 'active' AND rank = 'r5'");
        DB::statement("CREATE UNIQUE INDEX alliance_memberships_one_active_alliance_per_player ON alliance_memberships (player_id) WHERE status = 'active'");

        $driver = DB::connection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::statement("CREATE OR REPLACE FUNCTION alliance_memberships_validate_kingdom() RETURNS trigger AS $$ BEGIN IF NEW.status = 'active' AND NOT EXISTS (SELECT 1 FROM players p JOIN alliances a ON a.id = NEW.alliance_id WHERE p.id = NEW.player_id AND p.current_kingdom_id = a.kingdom_id) THEN RAISE EXCEPTION 'active player kingdom must match alliance kingdom'; END IF; RETURN NEW; END; $$ LANGUAGE plpgsql");
            DB::statement('DROP TRIGGER IF EXISTS alliance_memberships_kingdom_insert ON alliance_memberships');
            DB::statement('DROP TRIGGER IF EXISTS alliance_memberships_kingdom_update ON alliance_memberships');
            DB::statement('CREATE TRIGGER alliance_memberships_kingdom_insert BEFORE INSERT ON alliance_memberships FOR EACH ROW EXECUTE FUNCTION alliance_memberships_validate_kingdom()');
            DB::statement('CREATE TRIGGER alliance_memberships_kingdom_update BEFORE UPDATE OF alliance_id, player_id, status ON alliance_memberships FOR EACH ROW EXECUTE FUNCTION alliance_memberships_validate_kingdom()');
            DB::statement("CREATE OR REPLACE FUNCTION players_prevent_active_alliance_kingdom_mismatch() RETURNS trigger AS $$ BEGIN IF NEW.current_kingdom_id IS DISTINCT FROM OLD.current_kingdom_id AND EXISTS (SELECT 1 FROM alliance_memberships m JOIN alliances a ON a.id = m.alliance_id WHERE m.player_id = NEW.id AND m.status = 'active' AND a.kingdom_id <> NEW.current_kingdom_id) THEN RAISE EXCEPTION 'leave the active alliance before changing player kingdom'; END IF; RETURN NEW; END; $$ LANGUAGE plpgsql");
            DB::statement('DROP TRIGGER IF EXISTS players_active_alliance_kingdom_guard ON players');
            DB::statement('CREATE TRIGGER players_active_alliance_kingdom_guard BEFORE UPDATE OF current_kingdom_id ON players FOR EACH ROW EXECUTE FUNCTION players_prevent_active_alliance_kingdom_mismatch()');
        }

        if ($driver === 'sqlite') {
            $mismatch = "NEW.status = 'active' AND NOT EXISTS (SELECT 1 FROM players p JOIN alliances a ON a.id = NEW.alliance_id WHERE p.id = NEW.player_id AND p.current_kingdom_id = a.kingdom_id)";
            DB::statement('DROP TRIGGER IF EXISTS alliance_memberships_kingdom_insert');
            DB::statement('DROP TRIGGER IF EXISTS alliance_memberships_kingdom_update');
            DB::statement('DROP TRIGGER IF EXISTS players_active_alliance_kingdom_guard');
            DB::statement("CREATE TRIGGER alliance_memberships_kingdom_insert BEFORE INSERT ON alliance_memberships WHEN {$mismatch} BEGIN SELECT RAISE(ABORT, 'player kingdom must match alliance kingdom'); END");
            DB::statement("CREATE TRIGGER alliance_memberships_kingdom_update BEFORE UPDATE OF alliance_id, player_id, status ON alliance_memberships WHEN {$mismatch} BEGIN SELECT RAISE(ABORT, 'player kingdom must match alliance kingdom'); END");
            DB::statement("CREATE TRIGGER players_active_alliance_kingdom_guard BEFORE UPDATE OF current_kingdom_id ON players WHEN EXISTS (SELECT 1 FROM alliance_memberships m JOIN alliances a ON a.id = m.alliance_id WHERE m.player_id = NEW.id AND m.status = 'active' AND a.kingdom_id <> NEW.current_kingdom_id) BEGIN SELECT RAISE(ABORT, 'leave the active alliance before changing player kingdom'); END");
        }
    }

    public function down(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('DROP TRIGGER IF EXISTS players_active_alliance_kingdom_guard ON players');
            DB::statement('DROP FUNCTION IF EXISTS players_prevent_active_alliance_kingdom_mismatch()');
            DB::statement('DROP TRIGGER IF EXISTS alliance_memberships_kingdom_update ON alliance_memberships');
            DB::statement('DROP TRIGGER IF EXISTS alliance_memberships_kingdom_insert ON alliance_memberships');
            DB::statement('DROP FUNCTION IF EXISTS alliance_memberships_validate_kingdom()');
        }

        if ($driver === 'sqlite') {
            DB::statement('DROP TRIGGER IF EXISTS players_active_alliance_kingdom_guard');
            DB::statement('DROP TRIGGER IF EXISTS alliance_memberships_kingdom_update');
            DB::statement('DROP TRIGGER IF EXISTS alliance_memberships_kingdom_insert');
        }

        Schema::dropIfExists('alliance_memberships');
    }
};
