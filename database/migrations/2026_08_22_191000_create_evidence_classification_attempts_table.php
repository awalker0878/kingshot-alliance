<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evidence_classification_attempts', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('evidence_id')->constrained('game_evidence')->cascadeOnDelete();
            $table->string('status', 24);
            $table->string('classifier_key', 96);
            $table->string('classifier_version', 64);
            $table->char('input_sha256', 64);
            $table->string('ocr_engine', 64)->nullable();
            $table->string('ocr_version', 64)->nullable();
            $table->string('ocr_language', 32)->nullable();
            $table->jsonb('ocr_payload')->nullable();
            $table->text('raw_text')->nullable();
            $table->string('classified_kind', 64);
            $table->decimal('confidence', 6, 5)->default(0);
            $table->string('reason', 512)->nullable();
            $table->string('failure_code', 64)->nullable();
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['evidence_id', 'created_at'], 'evidence_classification_history_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evidence_classification_attempts');
    }
};
