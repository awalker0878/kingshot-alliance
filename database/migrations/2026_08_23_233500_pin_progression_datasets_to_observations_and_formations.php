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
            $table->string('progression_dataset_id', 120)->nullable()->after('progression_level');
            $table->char('progression_dataset_checksum', 64)->nullable()->after('progression_dataset_id');
            $table->json('hero_observations')->nullable()->after('progression_dataset_checksum');
        });

        Schema::table('player_formations', function (Blueprint $table): void {
            $table->string('progression_dataset_id', 120)->nullable()->after('heroes');
            $table->char('progression_dataset_checksum', 64)->nullable()->after('progression_dataset_id');
        });
    }

    public function down(): void
    {
        Schema::table('player_snapshots', function (Blueprint $table): void {
            $table->dropColumn(['progression_dataset_id', 'progression_dataset_checksum', 'hero_observations']);
        });
        Schema::table('player_formations', function (Blueprint $table): void {
            $table->dropColumn(['progression_dataset_id', 'progression_dataset_checksum']);
        });
    }
};
