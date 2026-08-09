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
        Schema::create('transfer_groups', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('alliance_id')->constrained('alliances')->cascadeOnDelete();
            $table->foreignUlid('transfer_plan_id')->constrained('transfer_plans')->cascadeOnDelete();
            $table->string('name', 160);
            $table->timestampTz('archived_at')->nullable();
            $table->timestamps();

            $table->index(['alliance_id', 'transfer_plan_id', 'archived_at']);
        });

        DB::statement(
            'CREATE UNIQUE INDEX transfer_groups_one_active_name_per_plan '.
            'ON transfer_groups (transfer_plan_id, lower(name)) '.
            'WHERE archived_at IS NULL'
        );

        Schema::create('transfer_group_coordinators', function (Blueprint $table): void {
            $table->foreignUlid('alliance_id')->constrained('alliances')->cascadeOnDelete();
            $table->foreignUlid('transfer_plan_id')->constrained('transfer_plans')->cascadeOnDelete();
            $table->foreignUlid('transfer_group_id')->constrained('transfer_groups')->cascadeOnDelete();
            $table->foreignUlid('membership_id')->constrained('alliance_memberships')->cascadeOnDelete();
            $table->timestamps();

            $table->unique(['transfer_group_id', 'membership_id']);
            $table->index(['alliance_id', 'transfer_plan_id']);
            $table->index(['membership_id', 'transfer_plan_id']);
        });

        Schema::table('transfer_participants', function (Blueprint $table): void {
            $table->foreignUlid('transfer_group_id')
                ->nullable()
                ->constrained('transfer_groups')
                ->nullOnDelete();
            $table->index(['transfer_plan_id', 'transfer_group_id', 'withdrawn_at']);
        });
    }

    public function down(): void
    {
        Schema::table('transfer_participants', function (Blueprint $table): void {
            $table->dropIndex(['transfer_plan_id', 'transfer_group_id', 'withdrawn_at']);
            $table->dropForeign(['transfer_group_id']);
            $table->dropColumn('transfer_group_id');
        });

        Schema::dropIfExists('transfer_group_coordinators');
        Schema::dropIfExists('transfer_groups');
    }
};
