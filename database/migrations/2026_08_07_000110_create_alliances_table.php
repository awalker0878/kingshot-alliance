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
        Schema::create('alliances', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('kingdom_id')->constrained('kingdoms')->restrictOnDelete();
            $table->string('name', 120);
            $table->string('slug', 120)->unique();
            $table->string('language', 16)->default('en');
            $table->string('timezone', 64)->default('UTC');
            $table->string('status', 32)->default('active')->index();
            $table->timestamp('suspended_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->timestamp('restored_at')->nullable();
            $table->timestamp('retention_until')->nullable()->index();
            $table->string('lifecycle_reason', 500)->nullable();
            $table->timestamps();

            $table->index(['kingdom_id', 'status']);
        });

        $driver = DB::connection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::statement("CREATE OR REPLACE FUNCTION alliances_prevent_kingdom_change() RETURNS trigger AS $$ BEGIN IF NEW.kingdom_id IS DISTINCT FROM OLD.kingdom_id THEN RAISE EXCEPTION 'alliance kingdom is immutable'; END IF; RETURN NEW; END; $$ LANGUAGE plpgsql");
            DB::statement('DROP TRIGGER IF EXISTS alliances_kingdom_immutable ON alliances');
            DB::statement('CREATE TRIGGER alliances_kingdom_immutable BEFORE UPDATE OF kingdom_id ON alliances FOR EACH ROW EXECUTE FUNCTION alliances_prevent_kingdom_change()');
        }

        if ($driver === 'sqlite') {
            DB::statement('DROP TRIGGER IF EXISTS alliances_kingdom_immutable');
            DB::statement("CREATE TRIGGER alliances_kingdom_immutable BEFORE UPDATE OF kingdom_id ON alliances WHEN NEW.kingdom_id <> OLD.kingdom_id BEGIN SELECT RAISE(ABORT, 'alliance kingdom is immutable'); END");
        }
    }

    public function down(): void
    {
        $driver = DB::connection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::statement('DROP TRIGGER IF EXISTS alliances_kingdom_immutable ON alliances');
            DB::statement('DROP FUNCTION IF EXISTS alliances_prevent_kingdom_change()');
        }

        if ($driver === 'sqlite') {
            DB::statement('DROP TRIGGER IF EXISTS alliances_kingdom_immutable');
        }

        Schema::dropIfExists('alliances');
    }
};
