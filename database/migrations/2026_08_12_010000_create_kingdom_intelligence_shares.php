<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kingdom_intelligence_shares', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('source_alliance_id')->constrained('alliances')->cascadeOnDelete();
            $table->foreignUlid('recipient_alliance_id')->nullable()->constrained('alliances')->nullOnDelete();
            $table->foreignUlid('kingdom_id')->constrained('kingdoms')->restrictOnDelete();
            $table->char('invitation_token_hash', 64)->unique();
            $table->string('state', 24)->default('pending');
            $table->foreignId('invited_by_user_id')->constrained('users')->restrictOnDelete();
            $table->foreignId('accepted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('declined_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('revoked_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('invitation_expires_at');
            $table->timestampTz('invitation_used_at')->nullable();
            $table->timestampTz('accepted_at')->nullable();
            $table->timestampTz('declined_at')->nullable();
            $table->timestampTz('revoked_at')->nullable();
            $table->timestamps();

            $table->index(['source_alliance_id', 'state', 'created_at']);
            $table->index(['recipient_alliance_id', 'state', 'created_at']);
            $table->index(['kingdom_id', 'state', 'created_at']);
            $table->index(['source_alliance_id', 'recipient_alliance_id', 'kingdom_id', 'state']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kingdom_intelligence_shares');
    }
};
