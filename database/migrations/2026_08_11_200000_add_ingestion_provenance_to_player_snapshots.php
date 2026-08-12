<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('player_snapshots', function (Blueprint $table): void {
            $table->unsignedBigInteger('actor_user_id')->nullable()->change();
            $table->string('source_subscription_id', 26)->nullable();
            $table->string('source_batch_id', 26)->nullable();
            $table->string('source_adapter_key', 80)->nullable();
            $table->string('source_adapter_version', 40)->nullable();
            $table->string('source_record_id', 191)->nullable();
            $table->char('source_identity_hash', 64)->nullable();
            $table->char('source_payload_hash', 64)->nullable();

            $table->unique(
                ['alliance_id', 'source_identity_hash'],
                'player_snapshots_alliance_source_identity_unique',
            );
        });

        Schema::table('kingdom_ingestion_candidates', function (Blueprint $table): void {
            $table->string('promoted_record_type', 40)->nullable();
            $table->string('promoted_record_id', 26)->nullable();
            $table->timestampTz('promoted_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('kingdom_ingestion_candidates', function (Blueprint $table): void {
            $table->dropColumn(['promoted_record_type', 'promoted_record_id', 'promoted_at']);
        });

        Schema::table('player_snapshots', function (Blueprint $table): void {
            $table->dropUnique('player_snapshots_alliance_source_identity_unique');
            $table->dropColumn([
                'source_subscription_id',
                'source_batch_id',
                'source_adapter_key',
                'source_adapter_version',
                'source_record_id',
                'source_identity_hash',
                'source_payload_hash',
            ]);
            $table->unsignedBigInteger('actor_user_id')->nullable(false)->change();
        });
    }
};
