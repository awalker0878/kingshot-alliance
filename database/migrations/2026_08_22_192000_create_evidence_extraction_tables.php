<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('evidence_extraction_attempts', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('evidence_id')->constrained('game_evidence')->cascadeOnDelete();
            $table->foreignUlid('classification_attempt_id')->constrained('evidence_classification_attempts')->restrictOnDelete();
            $table->string('status', 24);
            $table->string('extractor_key', 96);
            $table->string('extractor_version', 64);
            $table->string('schema_version', 64);
            $table->char('input_sha256', 64);
            $table->decimal('overall_confidence', 6, 5)->default(0);
            $table->unsignedInteger('field_count')->default(0);
            $table->string('failure_code', 64)->nullable();
            $table->timestamp('started_at');
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
            $table->index(['evidence_id', 'created_at'], 'evidence_extraction_history_idx');
        });

        Schema::create('evidence_extracted_fields', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('extraction_attempt_id')->constrained('evidence_extraction_attempts')->cascadeOnDelete();
            $table->string('field_key', 96);
            $table->unsignedInteger('row_ordinal');
            $table->text('raw_text');
            $table->text('normalized_value');
            $table->string('data_type', 32);
            $table->decimal('confidence', 6, 5);
            $table->jsonb('bounding_box')->nullable();
            $table->jsonb('warnings')->nullable();
            $table->timestamps();
            $table->unique(['extraction_attempt_id', 'field_key', 'row_ordinal'], 'evidence_extracted_field_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('evidence_extracted_fields');
        Schema::dropIfExists('evidence_extraction_attempts');
    }
};
