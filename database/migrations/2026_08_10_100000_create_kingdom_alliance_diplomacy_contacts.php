<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kingdom_alliance_diplomacy_contacts', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('alliance_id')->constrained('alliances')->cascadeOnDelete();
            $table->foreignUlid('tracked_kingdom_alliance_id')->constrained('tracked_kingdom_alliances')->restrictOnDelete();
            $table->foreignUlid('kingdom_alliance_id')->constrained('kingdom_alliances')->restrictOnDelete();
            $table->string('display_name', 160);
            $table->string('game_role', 120)->nullable();
            $table->string('channel_type', 24);
            $table->string('handle', 255);
            $table->string('state', 16)->default('active');
            $table->timestampTz('last_verified_at')->nullable();
            $table->text('manager_notes')->nullable();
            $table->foreignUlid('created_by_player_id')->nullable()->constrained('players')->nullOnDelete();
            $table->foreignUlid('updated_by_player_id')->nullable()->constrained('players')->nullOnDelete();
            $table->timestampTz('deactivated_at')->nullable();
            $table->foreignUlid('deactivated_by_player_id')->nullable()->constrained('players')->nullOnDelete();
            $table->timestamps();

            $table->index(
                ['alliance_id', 'tracked_kingdom_alliance_id', 'state'],
                'ka_diplomacy_contacts_tracking_state_idx',
            );
            $table->index(
                ['alliance_id', 'last_verified_at'],
                'ka_diplomacy_contacts_verified_idx',
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kingdom_alliance_diplomacy_contacts');
    }
};
