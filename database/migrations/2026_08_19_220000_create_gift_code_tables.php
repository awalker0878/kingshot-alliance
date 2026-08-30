<?php

declare(strict_types=1);

use App\Contexts\GameWorld\GiftCodes\Enums\GiftCodeEvidenceClassification;
use App\Contexts\GameWorld\GiftCodes\Enums\GiftCodeEvidenceVerificationState;
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
        Schema::create('gift_code_sources', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('source_key', 120)->unique();
            $table->string('name', 160);
            $table->string('classification', 40);
            $table->string('canonical_domain', 255)->nullable()->index();
            $table->boolean('is_active')->default(true)->index();
            $table->string('verification_method', 80);
            $table->string('adapter_key', 120)->nullable()->index();
            $table->unsignedBigInteger('policy_revision')->default(0);
            $table->json('provenance_policy')->nullable();
            $table->boolean('ingestion_enabled')->default(false)->index();
            $table->text('ingestion_cursor')->nullable();
            $table->timestampTz('last_ingestion_attempt_at')->nullable()->index();
            $table->timestampTz('last_ingestion_success_at')->nullable()->index();
            $table->timestampTz('last_ingestion_failure_at')->nullable()->index();
            $table->string('last_ingestion_failure_code', 120)->nullable();
            $table->text('last_ingestion_error')->nullable();
            $table->timestampTz('revoked_at')->nullable()->index();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
        });

        Schema::create('gift_codes', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('code', 64);
            $table->string('normalized_code', 64)->unique();
            $table->foreignUlid('created_by_player_id')->nullable()->constrained('players')->nullOnDelete();
            $table->string('status', 32)->default(GiftCodeStatus::Pending->value)->index();
            $table->unsignedBigInteger('status_revision')->default(0);
            $table->string('status_reason_code', 120)->nullable()->index();
            $table->json('status_evidence_ids')->nullable();
            $table->timestampTz('status_changed_at')->nullable();
            $table->timestampTz('status_derived_at')->nullable();
            $table->timestampTz('discovered_at')->index();
            $table->timestampTz('expires_at')->nullable()->index();
            $table->string('expires_precision', 32)->nullable();
            $table->unsignedBigInteger('expires_revision')->default(0);
            $table->timestampsTz();

            $table->index(['status', 'expires_at', 'discovered_at']);
        });

        Schema::create('gift_code_provenances', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('gift_code_id')->constrained('gift_codes')->cascadeOnDelete();
            $table->foreignUlid('submitted_by_player_id')->nullable()->constrained('players')->nullOnDelete();
            $table->foreignUlid('registered_source_id')->nullable()->constrained('gift_code_sources')->nullOnDelete();
            $table->string('source_type', 32)->default(GiftCodeSource::Manual->value)->index();
            $table->string('source_label', 160)->nullable();
            $table->text('source_url')->nullable();
            $table->string('assertion', 48)->default('available')->index();
            $table->json('assertion_payload')->nullable();
            $table->timestampTz('claimed_expires_at')->nullable()->index();
            $table->string('expiry_precision', 32)->nullable();
            $table->string('expiry_timezone', 80)->nullable();
            $table->timestampTz('published_at')->nullable();
            $table->string('evidence_classification', 48)
                ->default(GiftCodeEvidenceClassification::CommunityClaim->value)
                ->index();
            $table->string('verification_state', 48)
                ->default(GiftCodeEvidenceVerificationState::Unverified->value)
                ->index();
            $table->string('source_version', 120)->nullable();
            $table->string('retrieval_version', 120)->nullable();
            $table->string('parser_version', 120)->nullable();
            $table->string('content_fingerprint', 64)->nullable()->index();
            $table->text('raw_evidence_ref')->nullable();
            $table->timestampTz('observed_at')->index();
            $table->string('fingerprint', 64);
            $table->timestampsTz();

            $table->unique(['gift_code_id', 'fingerprint']);
            $table->index(['gift_code_id', 'observed_at']);
            $table->index(['gift_code_id', 'verification_state', 'evidence_classification']);
            $table->index(['registered_source_id', 'observed_at']);
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
            $table->index(['gift_code_id', 'status']);
        });

        Schema::create('gift_code_moderation_decisions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('gift_code_id')->constrained('gift_codes')->cascadeOnDelete();
            $table->foreignId('actor_user_id')->constrained('users')->restrictOnDelete();
            $table->string('action', 48)->index();
            $table->text('reason')->nullable();
            $table->string('previous_status', 32)->nullable();
            $table->string('proposed_status', 32)->nullable();
            $table->json('evidence_ids')->nullable();
            $table->json('metadata')->nullable();
            $table->timestampTz('decided_at')->index();
            $table->timestampsTz();

            $table->index(['gift_code_id', 'decided_at']);
        });

        Schema::create('gift_code_fact_projections', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('gift_code_id')->constrained('gift_codes')->cascadeOnDelete();
            $table->string('fact_type', 48);
            $table->boolean('qualified')->default(false)->index();
            $table->string('reason_code', 120)->index();
            $table->json('value')->nullable();
            $table->json('evidence_ids')->nullable();
            $table->unsignedBigInteger('revision')->default(0);
            $table->timestampTz('derived_at');
            $table->timestampsTz();

            $table->unique(['gift_code_id', 'fact_type']);
            $table->index(['gift_code_id', 'qualified', 'fact_type']);
        });

        Schema::create('gift_code_notification_campaigns', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('gift_code_id')->constrained('gift_codes')->cascadeOnDelete();
            $table->string('notification_type', 80);
            $table->unsignedBigInteger('status_revision');
            $table->unsignedBigInteger('expires_revision');
            $table->json('metadata')->nullable();
            $table->unsignedBigInteger('cursor_user_id')->nullable();
            $table->unsignedBigInteger('examined_count')->default(0);
            $table->unsignedBigInteger('delivery_count')->default(0);
            $table->unsignedBigInteger('created_delivery_count')->default(0);
            $table->timestampTz('completed_at')->nullable()->index();
            $table->timestampsTz();

            $table->unique(
                ['gift_code_id', 'notification_type', 'status_revision', 'expires_revision'],
                'gift_code_notification_campaign_revision_unique',
            );
            $table->index(['completed_at', 'notification_type', 'id'], 'gift_code_notification_campaign_queue');
        });

        Schema::create('gift_code_ingestion_runs', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('gift_code_source_id')->constrained('gift_code_sources')->cascadeOnDelete();
            $table->string('status', 32)->index();
            $table->text('source_cursor')->nullable();
            $table->text('result_cursor')->nullable();
            $table->unsignedInteger('examined_count')->default(0);
            $table->unsignedInteger('accepted_count')->default(0);
            $table->unsignedInteger('duplicate_count')->default(0);
            $table->unsignedInteger('quarantined_count')->default(0);
            $table->string('failure_code', 120)->nullable()->index();
            $table->text('failure_message')->nullable();
            $table->timestampTz('started_at');
            $table->timestampTz('completed_at')->nullable()->index();
            $table->timestampsTz();

            $table->index(['gift_code_source_id', 'started_at']);
        });

        Schema::create('gift_code_source_reconciliation_jobs', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('gift_code_source_id')->constrained('gift_code_sources')->cascadeOnDelete();
            $table->unsignedBigInteger('source_revision');
            $table->string('reason_code', 120);
            $table->ulid('cursor_gift_code_id')->nullable();
            $table->unsignedBigInteger('examined_count')->default(0);
            $table->timestampTz('completed_at')->nullable()->index();
            $table->timestampsTz();

            $table->unique(['gift_code_source_id', 'source_revision']);
            $table->index(['completed_at', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('gift_code_source_reconciliation_jobs');
        Schema::dropIfExists('gift_code_ingestion_runs');
        Schema::dropIfExists('gift_code_notification_campaigns');
        Schema::dropIfExists('gift_code_fact_projections');
        Schema::dropIfExists('gift_code_moderation_decisions');
        Schema::dropIfExists('gift_code_redemptions');
        Schema::dropIfExists('gift_code_provenances');
        Schema::dropIfExists('gift_codes');
        Schema::dropIfExists('gift_code_sources');
    }
};
