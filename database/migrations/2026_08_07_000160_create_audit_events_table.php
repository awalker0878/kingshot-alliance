<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('audit_events', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('alliance_id')->nullable()->constrained('alliances')->nullOnDelete();
            $table->foreignId('actor_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event', 120)->index();
            $table->nullableUlidMorphs('subject');
            $table->json('metadata')->nullable();
            $table->uuid('request_id')->nullable()->index();
            $table->string('trace_id', 32)->nullable()->index();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['alliance_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('audit_events');
    }
};
