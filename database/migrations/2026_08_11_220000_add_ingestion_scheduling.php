<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('kingdom_ingestion_subscriptions', function (Blueprint $table): void {
            $table->timestampTz('next_run_at')->nullable()->after('source_cursor');
            $table->timestampTz('last_claimed_at')->nullable()->after('next_run_at');
            $table->unsignedSmallInteger('consecutive_failures')->default(0)->after('last_failed_at');
            $table->timestampTz('circuit_open_until')->nullable()->after('consecutive_failures');
            $table->string('last_failure_code', 80)->nullable()->after('circuit_open_until');
            $table->index(['state', 'next_run_at'], 'kingdom_ingestion_subscriptions_due_idx');
        });

        Schema::table('kingdom_ingestion_batches', function (Blueprint $table): void {
            $table->string('next_source_cursor', 255)->nullable()->after('source_cursor');
        });
    }

    public function down(): void
    {
        Schema::table('kingdom_ingestion_batches', function (Blueprint $table): void {
            $table->dropColumn('next_source_cursor');
        });

        Schema::table('kingdom_ingestion_subscriptions', function (Blueprint $table): void {
            $table->dropIndex('kingdom_ingestion_subscriptions_due_idx');
            $table->dropColumn([
                'next_run_at',
                'last_claimed_at',
                'consecutive_failures',
                'circuit_open_until',
                'last_failure_code',
            ]);
        });
    }
};
