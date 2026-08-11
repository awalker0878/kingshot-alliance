<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kingdom_alliance_observations', function (Blueprint $table): void {
            $table->string('source_subscription_id', 26)->nullable();
            $table->string('source_batch_id', 26)->nullable();
            $table->string('source_adapter_key', 80)->nullable();
            $table->string('source_adapter_version', 40)->nullable();
            $table->string('source_record_id', 191)->nullable();
            $table->char('source_identity_hash', 64)->nullable();
            $table->char('source_payload_hash', 64)->nullable();

            $table->unique(
                ['alliance_id', 'source_identity_hash'],
                'ka_obs_alliance_source_identity_unique',
            );
        });
    }

    public function down(): void
    {
        Schema::table('kingdom_alliance_observations', function (Blueprint $table): void {
            $table->dropUnique('ka_obs_alliance_source_identity_unique');
            $table->dropColumn([
                'source_subscription_id',
                'source_batch_id',
                'source_adapter_key',
                'source_adapter_version',
                'source_record_id',
                'source_identity_hash',
                'source_payload_hash',
            ]);
        });
    }
};
