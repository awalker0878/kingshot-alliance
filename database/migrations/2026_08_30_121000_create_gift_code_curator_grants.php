<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gift_code_curator_grants', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('granted_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestampTz('granted_at');
            $table->timestampTz('revoked_at')->nullable()->index();
            $table->timestampsTz();

            $table->unique('user_id');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gift_code_curator_grants');
    }
};
