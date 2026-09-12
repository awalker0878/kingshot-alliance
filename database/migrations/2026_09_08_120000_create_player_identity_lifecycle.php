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
        Schema::table('players', function (Blueprint $table): void {
            $table->ulid('canonical_player_id')->nullable();
            $table->index('canonical_player_id', 'players_canonical_idx');
            $table->index(['current_kingdom_id', 'canonical_player_id', 'id'], 'players_recovery_choice_page');
        });

        Schema::table('players', function (Blueprint $table): void {
            $table->foreign('canonical_player_id', 'players_canonical_fk')
                ->references('id')
                ->on('players')
                ->restrictOnDelete();
        });

        Schema::create('player_identity_history', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('player_id')->constrained('players')->restrictOnDelete();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->foreignUlid('kingdom_id')->constrained('kingdoms')->restrictOnDelete();
            $table->string('name', 160);
            $table->string('game_player_id', 100)->nullable();
            $table->timestampTz('valid_from');
            $table->timestampTz('valid_to')->nullable();
            $table->string('source_type', 40)->default('manual');
            $table->string('source_reference', 191)->nullable();
            $table->timestampTz('observed_at')->nullable();
            $table->unsignedSmallInteger('confidence_basis_points')->nullable();
            $table->text('reason')->nullable();
            $table->timestamps();

            $table->index(['player_id', 'valid_from'], 'player_identity_history_player_time_idx');
            $table->index(['game_player_id', 'valid_from'], 'player_identity_history_game_id_idx');
            $table->index(['source_type', 'observed_at'], 'player_identity_history_source_idx');
        });

        DB::statement(
            'CREATE UNIQUE INDEX player_identity_history_one_current ON player_identity_history (player_id) WHERE valid_to IS NULL'
        );

        Schema::create('player_reconciliations', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->ulid('canonical_player_id');
            $table->ulid('duplicate_player_id');
            $table->text('reason');
            $table->string('source_type', 40)->default('system_reconciliation');
            $table->string('source_reference', 191)->nullable();
            $table->unsignedSmallInteger('confidence_basis_points')->nullable();
            $table->timestampTz('reconciled_at');
            $table->timestamps();

            $table->foreign('canonical_player_id', 'player_recon_canonical_fk')
                ->references('id')->on('players')->restrictOnDelete();
            $table->foreign('duplicate_player_id', 'player_recon_duplicate_fk')
                ->references('id')->on('players')->restrictOnDelete();
            $table->unique('duplicate_player_id', 'player_recon_duplicate_unique');
            $table->index(['canonical_player_id', 'reconciled_at'], 'player_recon_canonical_time_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('player_reconciliations');
        Schema::dropIfExists('player_identity_history');

        Schema::table('players', function (Blueprint $table): void {
            $table->dropForeign('players_canonical_fk');
            $table->dropIndex('players_canonical_idx');
            $table->dropIndex('players_recovery_choice_page');
            $table->dropColumn('canonical_player_id');
        });
    }
};
