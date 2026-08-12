<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kingdom_intelligence_share_targets', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('kingdom_intelligence_share_id')
                ->constrained('kingdom_intelligence_shares')
                ->cascadeOnDelete();
            $table->foreignUlid('tracked_kingdom_alliance_id')
                ->constrained('tracked_kingdom_alliances')
                ->cascadeOnDelete();
            $table->string('state', 24)->default('active');
            $table->foreignId('shared_by_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('removed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('shared_at');
            $table->timestampTz('removed_at')->nullable();
            $table->timestamps();

            $table->unique(
                ['kingdom_intelligence_share_id', 'tracked_kingdom_alliance_id'],
                'kingdom_share_target_unique',
            );
            $table->index(['kingdom_intelligence_share_id', 'state', 'created_at'], 'kingdom_share_target_state_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kingdom_intelligence_share_targets');
    }
};
