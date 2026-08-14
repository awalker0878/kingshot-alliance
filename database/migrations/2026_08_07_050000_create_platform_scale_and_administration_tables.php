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
        Schema::table('alliances', function (Blueprint $table): void {
            $table->timestamp('suspended_at')->nullable();
            $table->timestamp('closed_at')->nullable();
            $table->timestamp('deleted_at')->nullable();
            $table->timestamp('restored_at')->nullable();
            $table->timestamp('retention_until')->nullable()->index();
            $table->string('lifecycle_reason', 500)->nullable();
        });

        Schema::table('users', function (Blueprint $table): void {
            $table->timestamp('deletion_requested_at')->nullable();
            $table->timestamp('anonymized_at')->nullable();
        });

        Schema::create('platform_administrators', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->foreignId('granted_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('granted_at');
            $table->timestamp('revoked_at')->nullable()->index();
            $table->timestamps();
        });

        Schema::create('platform_plans', function (Blueprint $table): void {
            $table->string('code', 40)->primary();
            $table->string('name', 100);
            $table->boolean('is_active')->default(true)->index();
            $table->timestamps();
        });

        Schema::create('platform_plan_entitlements', function (Blueprint $table): void {
            $table->id();
            $table->string('plan_code', 40);
            $table->string('entitlement_key', 80);
            $table->unsignedBigInteger('limit_value');
            $table->timestamps();

            $table->foreign('plan_code')->references('code')->on('platform_plans')->cascadeOnDelete();
            $table->unique(['plan_code', 'entitlement_key']);
        });

        Schema::create('alliance_plan_assignments', function (Blueprint $table): void {
            $table->foreignUlid('alliance_id')->primary()->constrained('alliances')->cascadeOnDelete();
            $table->string('plan_code', 40);
            $table->foreignId('assigned_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('assigned_at');
            $table->timestamps();

            $table->foreign('plan_code')->references('code')->on('platform_plans')->restrictOnDelete();
        });

        Schema::create('alliance_platform_settings', function (Blueprint $table): void {
            $table->foreignUlid('alliance_id')->primary()->constrained('alliances')->cascadeOnDelete();
            $table->unsignedSmallInteger('retention_days')->default(30);
            $table->string('queue_partition', 40)->default('standard');
            $table->boolean('api_access_enabled')->default(true);
            $table->boolean('webhooks_enabled')->default(true);
            $table->timestamps();
        });

        Schema::create('alliance_feature_flags', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('alliance_id')->constrained('alliances')->cascadeOnDelete();
            $table->string('feature_key', 100);
            $table->boolean('enabled')->default(false);
            $table->json('configuration')->nullable();
            $table->foreignId('updated_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamps();

            $table->unique(['alliance_id', 'feature_key']);
        });

        Schema::create('alliance_usage_snapshots', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('alliance_id')->constrained('alliances')->cascadeOnDelete();
            $table->unsignedInteger('active_members');
            $table->unsignedBigInteger('storage_bytes');
            $table->unsignedInteger('active_api_credentials');
            $table->unsignedInteger('active_webhook_subscriptions');
            $table->unsignedInteger('pending_outbox_messages');
            $table->timestamp('captured_at')->index();
            $table->timestamps();

            $table->index(['alliance_id', 'captured_at']);
        });

        Schema::create('legal_holds', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('subject_type', 24);
            $table->string('subject_id', 64);
            $table->string('reason', 1000);
            $table->foreignId('placed_by_user_id')->constrained('users')->restrictOnDelete();
            $table->timestamp('placed_at');
            $table->foreignId('released_by_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('released_at')->nullable();
            $table->timestamps();

            $table->index(['subject_type', 'subject_id', 'released_at']);
        });

        Schema::create('account_deletion_requests', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignId('user_id')->unique()->constrained('users')->cascadeOnDelete();
            $table->string('status', 32)->default('pending')->index();
            $table->timestamp('requested_at');
            $table->timestamp('eligible_at')->index();
            $table->timestamp('processed_at')->nullable();
            $table->string('blocked_reason', 500)->nullable();
            $table->timestamps();
        });

        Schema::create('alliance_data_exports', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('alliance_id')->constrained('alliances')->cascadeOnDelete();
            $table->foreignId('requested_by_user_id')->constrained('users')->restrictOnDelete();
            $table->string('schema_version', 40);
            $table->string('format', 20)->default('json');
            $table->unsignedInteger('row_count');
            $table->char('sha256', 64);
            $table->timestamp('generated_at');
            $table->timestamps();

            $table->index(['alliance_id', 'generated_at']);
        });

        Schema::create('api_credentials', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('alliance_id')->constrained('alliances')->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('prefix', 24)->unique();
            $table->char('secret_hash', 64);
            $table->json('scopes');
            $table->timestamp('expires_at')->nullable()->index();
            $table->timestamp('last_used_at')->nullable();
            $table->timestamp('revoked_at')->nullable()->index();
            $table->foreignUlid('created_by_player_id')->constrained('players')->restrictOnDelete();
            $table->timestamps();

            $table->index(['alliance_id', 'revoked_at']);
        });

        Schema::create('webhook_subscriptions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('alliance_id')->constrained('alliances')->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('url', 2048);
            $table->json('events');
            $table->text('signing_secret');
            $table->boolean('is_active')->default(true)->index();
            $table->timestamp('revoked_at')->nullable();
            $table->foreignUlid('created_by_player_id')->constrained('players')->restrictOnDelete();
            $table->timestamps();

            $table->index(['alliance_id', 'is_active']);
        });

        Schema::create('webhook_deliveries', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('alliance_id')->constrained('alliances')->cascadeOnDelete();
            $table->foreignUlid('webhook_subscription_id')->constrained('webhook_subscriptions')->cascadeOnDelete();
            $table->string('source_message_id', 64);
            $table->string('event_type', 120);
            $table->json('payload')->nullable();
            $table->string('status', 24)->default('pending')->index();
            $table->unsignedTinyInteger('attempts')->default(0);
            $table->timestamp('available_at')->index();
            $table->timestamp('last_attempt_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->unsignedSmallInteger('response_code')->nullable();
            $table->string('response_excerpt', 1000)->nullable();
            $table->string('last_error', 1000)->nullable();
            $table->string('idempotency_key', 191)->unique();
            $table->timestamps();

            $table->index(['alliance_id', 'status', 'available_at']);
        });

        $now = now();
        DB::table('platform_plans')->insert([
            'code' => 'standard',
            'name' => 'Standard',
            'is_active' => true,
            'created_at' => $now,
            'updated_at' => $now,
        ]);
        DB::table('platform_plan_entitlements')->insert([
            ['plan_code' => 'standard', 'entitlement_key' => 'members.max', 'limit_value' => 250, 'created_at' => $now, 'updated_at' => $now],
            ['plan_code' => 'standard', 'entitlement_key' => 'storage.bytes.max', 'limit_value' => 1073741824, 'created_at' => $now, 'updated_at' => $now],
            ['plan_code' => 'standard', 'entitlement_key' => 'api_credentials.max', 'limit_value' => 5, 'created_at' => $now, 'updated_at' => $now],
            ['plan_code' => 'standard', 'entitlement_key' => 'webhook_subscriptions.max', 'limit_value' => 10, 'created_at' => $now, 'updated_at' => $now],
        ]);

        foreach (DB::table('alliances')->pluck('id') as $allianceId) {
            DB::table('alliance_plan_assignments')->insert([
                'alliance_id' => $allianceId,
                'plan_code' => 'standard',
                'assigned_by_user_id' => null,
                'assigned_at' => $now,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            DB::table('alliance_platform_settings')->insert([
                'alliance_id' => $allianceId,
                'retention_days' => 30,
                'queue_partition' => 'standard',
                'api_access_enabled' => true,
                'webhooks_enabled' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_deliveries');
        Schema::dropIfExists('webhook_subscriptions');
        Schema::dropIfExists('api_credentials');
        Schema::dropIfExists('alliance_data_exports');
        Schema::dropIfExists('account_deletion_requests');
        Schema::dropIfExists('legal_holds');
        Schema::dropIfExists('alliance_usage_snapshots');
        Schema::dropIfExists('alliance_feature_flags');
        Schema::dropIfExists('alliance_platform_settings');
        Schema::dropIfExists('alliance_plan_assignments');
        Schema::dropIfExists('platform_plan_entitlements');
        Schema::dropIfExists('platform_plans');
        Schema::dropIfExists('platform_administrators');

        Schema::table('users', function (Blueprint $table): void {
            $table->dropColumn(['deletion_requested_at', 'anonymized_at']);
        });

        Schema::table('alliances', function (Blueprint $table): void {
            $table->dropColumn([
                'suspended_at',
                'closed_at',
                'deleted_at',
                'restored_at',
                'retention_until',
                'lifecycle_reason',
            ]);
        });
    }
};
