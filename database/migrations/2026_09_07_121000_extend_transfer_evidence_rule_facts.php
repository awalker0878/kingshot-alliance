<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('evidence_transfer_reviews', function (Blueprint $table): void {
            $table->unsignedInteger('target_hero_generation')->nullable()->after('target_power_cap');
            $table->unsignedInteger('target_truegold_level')->nullable()->after('target_hero_generation');
            $table->unsignedInteger('target_character_age_threshold_days')->nullable()->after('target_truegold_level');
        });
    }

    public function down(): void
    {
        Schema::table('evidence_transfer_reviews', function (Blueprint $table): void {
            $table->dropColumn([
                'target_hero_generation',
                'target_truegold_level',
                'target_character_age_threshold_days',
            ]);
        });
    }
};
