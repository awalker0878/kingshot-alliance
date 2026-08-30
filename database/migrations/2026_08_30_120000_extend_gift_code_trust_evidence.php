<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
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
            $table->json('provenance_policy')->nullable();
            $table->boolean('ingestion_enabled')->default(false)->index();
            $table->timestampTz('revoked_at')->nullable();
            $table->foreignId('created_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
        });

        Schema::table('gift_codes', function (Blueprint $table): void {
            $table->unsignedBigInteger('status_revision')->default(0)->after('status');
            $table->string('status_reason_code', 120)->nullable()->after('status_revision');
            $table->json('status_evidence_ids')->nullable()->after('status_reason_code');
            $table->timestampTz('status_derived_at')->nullable()->after('status_changed_at');
            $table->string('expires_precision', 32)->nullable()->after('expires_at');
            $table->unsignedBigInteger('expires_revision')->default(0)->after('expires_precision');
            $table->string('trust_v2_shadow_status', 32)->nullable()->after('expires_revision');
            $table->string('trust_v2_shadow_reason_code', 120)->nullable()->after('trust_v2_shadow_status');
            $table->timestampTz('trust_v2_compared_at')->nullable()->after('trust_v2_shadow_reason_code');
        });

        Schema::table('gift_code_provenances', function (Blueprint $table): void {
            $table->foreignUlid('registered_source_id')->nullable()->after('submitted_by_player_id')
                ->constrained('gift_code_sources')->nullOnDelete();
            $table->string('assertion', 48)->default('available')->after('source_url')->index();
            $table->json('assertion_payload')->nullable()->after('assertion');
            $table->timestampTz('claimed_expires_at')->nullable()->after('assertion_payload');
            $table->string('expiry_precision', 32)->nullable()->after('claimed_expires_at');
            $table->string('expiry_timezone', 80)->nullable()->after('expiry_precision');
            $table->timestampTz('published_at')->nullable()->after('expiry_timezone');
            $table->string('evidence_classification', 48)->default('community_claim')->after('published_at');
            $table->string('verification_state', 48)->default('unverified')->after('evidence_classification')->index();
            $table->string('source_version', 120)->nullable()->after('verification_state');
            $table->string('retrieval_version', 120)->nullable()->after('source_version');
            $table->string('parser_version', 120)->nullable()->after('retrieval_version');
            $table->string('content_fingerprint', 64)->nullable()->after('parser_version');
            $table->text('raw_evidence_ref')->nullable()->after('content_fingerprint');
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

        // Preserve legacy source labels as evidence but remove their implied authority.
        DB::table('gift_code_provenances')
            ->where('source_type', 'official')
            ->update([
                'verification_state' => 'legacy_unverified',
                'evidence_classification' => 'legacy_claim',
            ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('gift_code_moderation_decisions');

        Schema::table('gift_code_provenances', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('registered_source_id');
            $table->dropColumn([
                'assertion',
                'assertion_payload',
                'claimed_expires_at',
                'expiry_precision',
                'expiry_timezone',
                'published_at',
                'evidence_classification',
                'verification_state',
                'source_version',
                'retrieval_version',
                'parser_version',
                'content_fingerprint',
                'raw_evidence_ref',
            ]);
        });

        Schema::table('gift_codes', function (Blueprint $table): void {
            $table->dropColumn([
                'status_revision',
                'status_reason_code',
                'status_evidence_ids',
                'status_derived_at',
                'expires_precision',
                'expires_revision',
                'trust_v2_shadow_status',
                'trust_v2_shadow_reason_code',
                'trust_v2_compared_at',
            ]);
        });

        Schema::dropIfExists('gift_code_sources');
    }
};
