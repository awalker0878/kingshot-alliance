<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('outbox_messages', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('alliance_id')->nullable()->constrained('alliances')->cascadeOnDelete();
            $table->string('partition_key', 180)->nullable()->index();
            $table->string('event_type', 160);
            $table->string('aggregate_type', 160);
            $table->string('aggregate_id', 64);
            $table->string('idempotency_key', 160)->unique();
            $table->json('payload');
            $table->timestamp('occurred_at');
            $table->timestamp('available_at')->index();
            $table->timestamp('published_at')->nullable()->index();
            $table->unsignedInteger('attempts')->default(0);
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->index(['alliance_id', 'event_type']);
            $table->index(['partition_key', 'event_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('outbox_messages');
    }
};
