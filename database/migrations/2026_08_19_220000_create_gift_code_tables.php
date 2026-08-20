<?php

declare(strict_types=1);

use App\Contexts\GameWorld\GiftCodes\Enums\GiftCodeRedemptionStatus;
use App\Contexts\GameWorld\GiftCodes\Enums\GiftCodeSource;
use App\Contexts\GameWorld\GiftCodes\Enums\GiftCodeStatus;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('gift_codes', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('code', 64);
            $table->string('normalized_code', 64)->unique();
            $table->string('source_type', 32)->default(GiftCodeSource::Manual->value);
            $table->string('source_label', 160)->nullable();
            $table->text('source_url')->nullable();
            $table->foreignUlid('created_by_player_id')->nullable()->constrained('players')->nullOnDelete();
            $table->string('status', 32)->default(GiftCodeStatus::Pending->value)->index();
            $table->timestampTz('status_changed_at')->nullable();
            $table->timestampTz('discovered_at')->index();
            $table->timestampTz('expires_at')->nullable()->index();
            $table->timestampsTz();
        });

        Schema::create('gift_code_provenances', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('gift_code_id')->constrained('gift_codes')->cascadeOnDelete();
            $table->foreignUlid('submitted_by_player_id')->nullable()->constrained('players')->nullOnDelete();
            $table->string('source_type', 32);
            $table->string('source_label', 160)->nullable();
            $table->text('source_url')->nullable();
            $table->timestampTz('observed_at')->index();
            $table->string('fingerprint', 64);
            $table->timestampsTz();

            $table->unique(['gift_code_id', 'fingerprint']);
            $table->index(['gift_code_id', 'observed_at']);
        });

        Schema::create('gift_code_redemptions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('gift_code_id')->constrained('gift_codes')->cascadeOnDelete();
            $table->foreignUlid('player_id')->constrained('players')->cascadeOnDelete();
            $table->foreignUlid('kingdom_id')->constrained('kingdoms')->restrictOnDelete();
            $table->string('status', 48)->default(GiftCodeRedemptionStatus::AwaitingConfirmation->value)->index();
            $table->string('provider', 80);
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->string('last_result_code', 120)->nullable();
            $table->text('last_message')->nullable();
            $table->text('redemption_url')->nullable();
            $table->timestampTz('last_attempt_at')->nullable();
            $table->timestampTz('next_attempt_at')->nullable()->index();
            $table->timestampTz('redeemed_at')->nullable()->index();
            $table->timestampsTz();

            $table->unique(['gift_code_id', 'player_id']);
            $table->index(['player_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gift_code_redemptions');
        Schema::dropIfExists('gift_code_provenances');
        Schema::dropIfExists('gift_codes');
    }
};
