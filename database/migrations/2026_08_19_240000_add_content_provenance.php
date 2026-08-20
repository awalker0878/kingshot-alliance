<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('content_items', function (Blueprint $table): void {
            $table->string('source_label', 180)->nullable();
            $table->string('source_url', 2048)->nullable();
            $table->string('game_version', 64)->nullable();
            $table->date('reviewed_at')->nullable();
        });

        Schema::table('content_revisions', function (Blueprint $table): void {
            $table->boolean('notify_members')->default(false);
            $table->string('source_label', 180)->nullable();
            $table->string('source_url', 2048)->nullable();
            $table->string('game_version', 64)->nullable();
            $table->date('reviewed_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('content_revisions', function (Blueprint $table): void {
            $table->dropColumn([
                'notify_members',
                'source_label',
                'source_url',
                'game_version',
                'reviewed_at',
            ]);
        });

        Schema::table('content_items', function (Blueprint $table): void {
            $table->dropColumn(['source_label', 'source_url', 'game_version', 'reviewed_at']);
        });
    }
};
