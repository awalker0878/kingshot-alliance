<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('game_evidence', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('alliance_id')->constrained('alliances')->restrictOnDelete();
            $table->foreignUlid('occurrence_id')->constrained('event_occurrences')->restrictOnDelete();
            $table->string('expected_kind', 64);
            $table->string('kind', 64);
            $table->string('lifecycle_status', 32);
            $table->string('original_name', 255);
            $table->string('disk', 64);
            $table->string('path', 1024);
            $table->string('mime_type', 96);
            $table->unsignedBigInteger('size_bytes');
            $table->unsignedInteger('width');
            $table->unsignedInteger('height');
            $table->char('sha256', 64);
            $table->char('perceptual_hash', 16)->nullable();
            $table->ulid('visual_duplicate_evidence_id')->nullable();
            $table->unsignedTinyInteger('visual_duplicate_distance')->nullable();
            $table->foreignUlid('uploaded_by_player_id')->constrained('players')->restrictOnDelete();
            $table->timestamp('scanned_at');
            $table->timestamps();

            $table->unique(['alliance_id', 'occurrence_id', 'sha256'], 'game_evidence_exact_unique');
            $table->index(['occurrence_id', 'lifecycle_status', 'created_at'], 'game_evidence_occurrence_status_idx');
            $table->index(['alliance_id', 'sha256'], 'game_evidence_alliance_hash_idx');
            $table->index(['alliance_id', 'occurrence_id', 'perceptual_hash'], 'game_evidence_visual_hash_idx');
            $table->index(['alliance_id', 'lifecycle_status', 'created_at'], 'game_evidence_alliance_status_idx');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('game_evidence');
    }
};
