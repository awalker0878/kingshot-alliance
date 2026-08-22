<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evidence_commit_attempts', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('evidence_id')->constrained('game_evidence')->cascadeOnDelete();
            $table->foreignUlid('review_id')->constrained('evidence_reviews')->restrictOnDelete();
            $table->string('status', 24);
            $table->char('idempotency_key', 64);
            $table->string('destination_context', 96)->default('operations.results');
            $table->ulid('destination_report_id')->nullable();
            $table->jsonb('destination_receipt')->nullable();
            $table->string('failure_code', 64)->nullable();
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['review_id', 'status', 'created_at'], 'evidence_commit_review_status_idx');
            $table->index(['idempotency_key', 'status'], 'evidence_commit_idempotency_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evidence_commit_attempts');
    }
};
