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
        Schema::create('transfer_cohorts', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('alliance_id')->constrained('alliances')->cascadeOnDelete();
            $table->foreignUlid('transfer_plan_id')->constrained('transfer_plans')->cascadeOnDelete();
            $table->string('name', 160);
            $table->string('direction', 24)->index();
            $table->foreignUlid('destination_kingdom_id')->nullable()->constrained('kingdoms')->restrictOnDelete();
            $table->string('state', 24)->default('active')->index();
            $table->foreignUlid('coordinator_player_id')->nullable()->constrained('players')->nullOnDelete();
            $table->text('manager_notes')->nullable();
            $table->timestamps();
            $table->index(['alliance_id', 'transfer_plan_id', 'state']);
            $table->index(['transfer_plan_id', 'direction', 'destination_kingdom_id']);
        });
        DB::statement('CREATE UNIQUE INDEX transfer_cohorts_one_active_name_per_plan ON transfer_cohorts (transfer_plan_id, lower(name)) WHERE state = \'active\'');
        Schema::table('transfer_participants', function (Blueprint $table): void {
            $table->foreignUlid('transfer_cohort_id')->nullable()->constrained('transfer_cohorts')->nullOnDelete();
            $table->index(['transfer_plan_id', 'transfer_cohort_id', 'withdrawn_at']);
        });
    }

    public function down(): void
    {
        Schema::table('transfer_participants', function (Blueprint $table): void {
            $table->dropIndex(['transfer_plan_id', 'transfer_cohort_id', 'withdrawn_at']);
            $table->dropForeign(['transfer_cohort_id']);
            $table->dropColumn('transfer_cohort_id');
        });
        Schema::dropIfExists('transfer_cohorts');
    }
};
