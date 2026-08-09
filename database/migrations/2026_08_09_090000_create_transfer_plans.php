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
        Schema::create('transfer_plans', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('alliance_id')->constrained('alliances')->cascadeOnDelete();
            $table->foreignUlid('home_kingdom_id')->constrained('kingdoms')->restrictOnDelete();
            $table->string('label', 160);
            $table->date('starts_on')->nullable();
            $table->date('ends_on')->nullable();
            $table->string('state', 24)->default('draft');
            $table->timestamps();

            $table->index(['alliance_id', 'state', 'created_at']);
            $table->index(['alliance_id', 'home_kingdom_id']);
        });

        DB::statement(
            "CREATE UNIQUE INDEX transfer_plans_one_open_per_alliance ON transfer_plans (alliance_id) WHERE state = 'open'"
        );
    }

    public function down(): void
    {
        Schema::dropIfExists('transfer_plans');
    }
};
