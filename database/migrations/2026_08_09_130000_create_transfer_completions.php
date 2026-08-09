<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transfer_completions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('alliance_id')->constrained('alliances')->cascadeOnDelete();
            $table->foreignUlid('transfer_plan_id')->constrained('transfer_plans')->cascadeOnDelete();
            $table->foreignUlid('transfer_participant_id')->constrained('transfer_participants')->cascadeOnDelete();
            $table->foreignUlid('roster_entry_id')->nullable()->constrained('alliance_roster_entries')->nullOnDelete();
            $table->string('direction', 16);
            $table->foreignId('completed_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('completed_at')->useCurrent();
            $table->timestamps();

            $table->unique('transfer_participant_id');
            $table->index(['alliance_id', 'transfer_plan_id', 'completed_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transfer_completions');
    }
};
