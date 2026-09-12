<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('kingdoms', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->integer('number')->unique();
            $table->string('status', 24)->default('active')->index();
            $table->timestamps();
            $table->index(['status', 'id'], 'kingdoms_recovery_choice_page');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kingdoms');
    }
};
