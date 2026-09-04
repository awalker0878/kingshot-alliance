<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('permissions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('key', 100)->unique();
            $table->string('description', 255);
            $table->timestamps();
        });

        Schema::create('roles', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('alliance_id')->constrained('alliances')->cascadeOnDelete();
            $table->string('key', 64);
            $table->string('name', 100);
            $table->boolean('is_system')->default(true);
            $table->timestamp('archived_at')->nullable();
            $table->timestamps();

            $table->unique(['alliance_id', 'key']);
            $table->unique(['id', 'alliance_id']);
            $table->index(['alliance_id', 'archived_at', 'name']);
        });

        Schema::create('role_permissions', function (Blueprint $table): void {
            $table->foreignUlid('role_id')->constrained('roles')->cascadeOnDelete();
            $table->foreignUlid('permission_id')->constrained('permissions')->cascadeOnDelete();
            $table->primary(['role_id', 'permission_id']);
        });

        Schema::create('membership_roles', function (Blueprint $table): void {
            $table->ulid('alliance_id');
            $table->ulid('membership_id');
            $table->ulid('role_id');

            $table->primary(['membership_id', 'role_id']);
            $table->index(['alliance_id', 'membership_id']);
            $table->index(['alliance_id', 'role_id']);

            $table->foreign(['membership_id', 'alliance_id'])
                ->references(['id', 'alliance_id'])
                ->on('alliance_memberships')
                ->cascadeOnDelete();

            $table->foreign(['role_id', 'alliance_id'])
                ->references(['id', 'alliance_id'])
                ->on('roles')
                ->cascadeOnDelete();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('membership_roles');
        Schema::dropIfExists('role_permissions');
        Schema::dropIfExists('roles');
        Schema::dropIfExists('permissions');
    }
};
