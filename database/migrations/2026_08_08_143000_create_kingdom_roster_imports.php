<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kingdom_roster_imports', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('alliance_id')->constrained('alliances')->cascadeOnDelete();
            $table->foreignUlid('created_by_player_id')->constrained('players')->restrictOnDelete();
            $table->foreignUlid('committed_by_player_id')->nullable()->constrained('players')->nullOnDelete();
            $table->string('status', 24)->default('previewed');
            $table->string('schema_version', 32);
            $table->string('original_filename', 255);
            $table->char('checksum', 64);
            $table->unsignedSmallInteger('row_count')->default(0);
            $table->unsignedSmallInteger('create_count')->default(0);
            $table->unsignedSmallInteger('update_count')->default(0);
            $table->unsignedSmallInteger('ambiguous_count')->default(0);
            $table->unsignedSmallInteger('rejected_count')->default(0);
            $table->json('preview_payload');
            $table->json('resolution_payload')->nullable();
            $table->json('committed_summary')->nullable();
            $table->timestampTz('committed_at')->nullable();
            $table->timestamps();

            $table->unique(['alliance_id', 'schema_version', 'checksum']);
            $table->index(['alliance_id', 'status', 'created_at']);
        });

        Schema::table('player_snapshots', function (Blueprint $table): void {
            $table->foreignUlid('roster_import_id')
                ->nullable()
                ->constrained('kingdom_roster_imports')
                ->nullOnDelete();
            $table->index(['alliance_id', 'roster_import_id']);
        });
    }

    public function down(): void
    {
        Schema::table('player_snapshots', function (Blueprint $table): void {
            $table->dropIndex(['alliance_id', 'roster_import_id']);
            $table->dropForeign(['roster_import_id']);
            $table->dropColumn('roster_import_id');
        });

        Schema::dropIfExists('kingdom_roster_imports');
    }
};
