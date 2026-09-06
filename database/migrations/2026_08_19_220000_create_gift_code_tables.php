<?php

declare(strict_types=1);

use App\Contexts\GameWorld\GiftCodes\Enums\GiftCodeAccountStateStatus;
use App\Contexts\GameWorld\GiftCodes\Enums\GiftCodeEvidenceClassification;
use App\Contexts\GameWorld\GiftCodes\Enums\GiftCodeEvidenceVerificationState;
use App\Contexts\GameWorld\GiftCodes\Enums\GiftCodeRedemptionSessionItemState;
use App\Contexts\GameWorld\GiftCodes\Enums\GiftCodeRedemptionSessionMode;
use App\Contexts\GameWorld\GiftCodes\Enums\GiftCodeRedemptionSessionStatus;
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
            $table->boolean('push_enabled')->default(false)->index();
            $table->boolean('head_poll_enabled')->default(true)->index();
            $table->boolean('reconciliation_enabled')->default(true)->index();
            $table->boolean('backfill_enabled')->default(true)->index();
            $table->boolean('authority_promotion_enabled')->default(true)->index();
            $table->string('activation_status', 32)->default('registered')->index();
            $table->string('health_status', 32)->default('disabled')->index();
            $table->timestampTz('next_eligible_ingestion_at')->nullable()->index();
            $table->unsignedInteger('consecutive_failures')->default(0);
            $table->unsignedInteger('consecutive_quarantined_runs')->default(0);
            $table->unsignedBigInteger('request_count')->default(0);
            $table->unsignedBigInteger('observation_count')->default(0);
            $table->unsignedBigInteger('accepted_observation_count')->default(0);
            $table->unsignedBigInteger('quarantined_observation_count')->default(0);
            $table->unsignedBigInteger('duplicate_observation_count')->default(0);
            $table->unsignedBigInteger('rate_limit_event_count')->default(0);
            $table->unsignedBigInteger('reconciliation_gap_count')->default(0);
            $table->unsignedBigInteger('signature_failure_count')->default(0);
            $table->unsignedBigInteger('replay_rejection_count')->default(0);
            $table->timestampTz('last_observation_at')->nullable()->index();
            $table->timestampTz('last_accepted_observation_at')->nullable()->index();
            $table->timestampTz('last_quarantined_observation_at')->nullable()->index();
            $table->timestampTz('last_push_received_at')->nullable()->index();
            $table->timestampTz('last_provider_event_at')->nullable()->index();
            $table->timestampTz('last_reconciliation_gap_at')->nullable()->index();
            $table->timestampTz('last_health_checked_at')->nullable()->index();
            $table->string('last_provider_request_id', 255)->nullable();
            $table->string('last_retrieval_version', 120)->nullable();
            $table->integer('last_quota_remaining')->nullable();
            $table->integer('last_rate_limit_remaining')->nullable();
            $table->unsignedInteger('last_retry_after_seconds')->nullable();
            $table->timestampTz('last_ingestion_attempt_at')->nullable()->index();
            $table->timestampTz('last_ingestion_success_at')->nullable()->index();
            $table->timestampTz('last_ingestion_failure_at')->nullable()->index();
            $table->string('last_ingestion_failure_code', 120)->nullable();
            $table->text('last_ingestion_error')->nullable();
            $table->timestampTz('revoked_at')->nullable()->index();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
        });

        Schema::create('gift_code_source_sync_states', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('gift_code_source_id')->constrained('gift_code_sources')->cascadeOnDelete();
            $table->string('sync_mode', 32);
            $table->text('latest_observed_provider_id')->nullable();
            $table->text('committed_high_water')->nullable();
            $table->text('candidate_high_water')->nullable();
            $table->text('active_sync_since_id')->nullable();
            $table->text('active_page_token')->nullable();
            $table->text('backfill_page_token')->nullable();
            $table->text('backfill_boundary_provider_id')->nullable();
            $table->text('http_etag')->nullable();
            $table->text('http_last_modified')->nullable();
            $table->timestampTz('last_not_modified_at')->nullable()->index();
            $table->timestampTz('last_head_poll_at')->nullable()->index();
            $table->timestampTz('last_reconciliation_at')->nullable()->index();
            $table->timestampTz('last_backfill_at')->nullable()->index();
            $table->unsignedBigInteger('version')->default(0);
            $table->timestampsTz();

            $table->unique(['gift_code_source_id', 'sync_mode'], 'gift_code_source_sync_mode_unique');
            $table->index(['sync_mode', 'last_head_poll_at']);
            $table->index(['sync_mode', 'last_reconciliation_at']);
            $table->index(['sync_mode', 'last_backfill_at']);
        });

        Schema::create('gift_code_source_subscriptions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('gift_code_source_id')->constrained('gift_code_sources')->cascadeOnDelete();
            $table->string('provider', 48)->index();
            $table->string('transport', 48)->index();
            $table->string('provider_subscription_id', 255)->nullable();
            $table->text('topic_or_rule')->nullable();
            $table->json('configured_identity')->nullable();
            $table->string('status', 32)->default('pending')->index();
            $table->timestampTz('activated_at')->nullable();
            $table->timestampTz('expires_at')->nullable()->index();
            $table->timestampTz('last_verified_at')->nullable();
            $table->timestampTz('last_event_received_at')->nullable()->index();
            $table->string('secret_version', 120)->nullable();
            $table->string('last_error_code', 120)->nullable()->index();
            $table->timestampsTz();

            $table->unique(['gift_code_source_id', 'provider', 'transport'], 'gift_code_source_subscription_unique');
            $table->index(['status', 'expires_at']);
        });

        Schema::create('gift_code_source_deliveries', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('gift_code_source_id')->constrained('gift_code_sources')->cascadeOnDelete();
            $table->string('provider', 48)->index();
            $table->string('provider_event_id', 255)->nullable()->index();
            $table->string('provider_item_id', 255)->nullable()->index();
            $table->string('replay_key', 64);
            $table->string('payload_sha256', 64);
            $table->timestampTz('received_at')->index();
            $table->timestampTz('authenticated_at')->nullable();
            $table->boolean('signature_valid')->default(false)->index();
            $table->timestampTz('processed_at')->nullable()->index();
            $table->string('processing_status', 32)->default('received')->index();
            $table->string('error_code', 120)->nullable()->index();
            $table->string('correlation_id', 255)->nullable()->index();
            $table->timestampsTz();

            $table->unique(['gift_code_source_id', 'replay_key'], 'gift_code_source_delivery_replay_unique');
            $table->index(['gift_code_source_id', 'received_at']);
            $table->index(['processing_status', 'received_at']);
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

        Schema::create('gift_code_account_states', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('gift_code_id')->constrained('gift_codes')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('state', 32)->default(GiftCodeAccountStateStatus::Actionable->value)->index();
            $table->timestampTz('snoozed_until')->nullable()->index();
            $table->timestampTz('remind_at')->nullable()->index();
            $table->timestampTz('last_opened_at')->nullable();
            $table->timestampTz('last_action_at')->nullable();
            $table->timestampsTz();

            $table->unique(['gift_code_id', 'user_id']);
            $table->index(['user_id', 'state']);
        });

        Schema::create('gift_code_redemption_sessions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('mode', 32)->default(GiftCodeRedemptionSessionMode::AllActionable->value)->index();
            $table->string('status', 32)->default(GiftCodeRedemptionSessionStatus::Active->value)->index();
            $table->unsignedInteger('total_items')->default(0);
            $table->unsignedInteger('completed_items')->default(0);
            $table->unsignedInteger('skipped_items')->default(0);
            $table->unsignedInteger('failed_items')->default(0);
            $table->timestampTz('last_activity_at')->index();
            $table->timestampTz('completed_at')->nullable()->index();
            $table->timestampTz('abandoned_at')->nullable()->index();
            $table->timestampsTz();

            $table->index(['user_id', 'status', 'last_activity_at']);
        });

        Schema::create('gift_code_redemption_session_items', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('session_id')->constrained('gift_code_redemption_sessions')->cascadeOnDelete();
            $table->foreignUlid('gift_code_id')->constrained('gift_codes')->cascadeOnDelete();
            $table->foreignUlid('player_id')->constrained('players')->cascadeOnDelete();
            $table->unsignedInteger('sequence');
            $table->string('state', 32)->default(GiftCodeRedemptionSessionItemState::Pending->value)->index();
            $table->unsignedBigInteger('status_revision_snapshot')->default(0);
            $table->unsignedBigInteger('expires_revision_snapshot')->default(0);
            $table->string('skip_reason', 120)->nullable();
            $table->string('unavailable_reason', 120)->nullable()->index();
            $table->timestampTz('completed_at')->nullable()->index();
            $table->timestampsTz();

            $table->unique(['session_id', 'gift_code_id', 'player_id'], 'gift_code_session_item_pair_unique');
            $table->unique(['session_id', 'sequence'], 'gift_code_session_item_sequence_unique');
            $table->index(['session_id', 'state']);
            $table->index(['player_id', 'state']);
        });

        Schema::create('gift_code_contributor_projections', function (Blueprint $table): void {
            $table->foreignId('user_id')->primary()->constrained('users')->cascadeOnDelete();
            $table->unsignedInteger('accepted_count')->default(0);
            $table->unsignedInteger('corroborated_count')->default(0);
            $table->unsignedInteger('rejected_count')->default(0);
            $table->unsignedInteger('misleading_count')->default(0);
            $table->unsignedBigInteger('revision')->default(0);
            $table->timestampTz('derived_at')->index();
            $table->timestampsTz();
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
            $table->string('sync_mode', 32)->default('head')->index();
            $table->text('source_cursor')->nullable();
            $table->text('result_cursor')->nullable();
            $table->json('result_checkpoint')->nullable();
            $table->unsignedInteger('request_count')->default(0);
            $table->string('provider_request_id', 255)->nullable();
            $table->string('retrieval_version', 120)->nullable();
            $table->integer('quota_remaining')->nullable();
            $table->integer('rate_limit_remaining')->nullable();
            $table->unsignedInteger('retry_after_seconds')->nullable();
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
            $table->index(['gift_code_source_id', 'sync_mode', 'started_at'], 'gift_code_ingestion_mode_runs');
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
        Schema::dropIfExists('gift_code_contributor_projections');
        Schema::dropIfExists('gift_code_redemption_session_items');
        Schema::dropIfExists('gift_code_redemption_sessions');
        Schema::dropIfExists('gift_code_account_states');
        Schema::dropIfExists('gift_code_redemptions');
        Schema::dropIfExists('gift_code_provenances');
        Schema::dropIfExists('gift_codes');
        Schema::dropIfExists('gift_code_source_deliveries');
        Schema::dropIfExists('gift_code_source_subscriptions');
        Schema::dropIfExists('gift_code_source_sync_states');
        Schema::dropIfExists('gift_code_sources');
    }
};
