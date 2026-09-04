<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name', 100);
            $table->string('email')->unique();
            $table->timestamp('email_verified_at')->nullable();
            $table->string('pending_email')->nullable()->unique();
            $table->timestamp('pending_email_requested_at')->nullable();
            $table->string('password')->nullable();
            $table->string('timezone', 64)->default('UTC');
            $table->text('two_factor_secret')->nullable();
            $table->text('two_factor_recovery_codes')->nullable();
            $table->timestamp('two_factor_confirmed_at')->nullable();
            $table->timestamp('deletion_requested_at')->nullable();
            $table->timestamp('anonymized_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        Schema::create('account_identities', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('provider', 32);
            $table->string('provider_subject', 255);
            $table->string('provider_email')->nullable();
            $table->timestamp('provider_email_verified_at')->nullable();
            $table->timestamp('linked_at');
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'provider_subject']);
            $table->unique(['user_id', 'provider']);
            $table->index(['provider', 'provider_email']);
        });

        Schema::create('passkeys', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('name', 100);
            $table->string('credential_id')->unique();
            $table->json('credential');
            $table->timestamp('last_used_at')->nullable();
            $table->timestamps();

            $table->index('user_id');
        });

        Schema::create('account_sessions', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->unique();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->char('session_id_hash', 64);
            $table->text('session_id');
            $table->string('browser_family', 64)->nullable();
            $table->string('platform_family', 64)->nullable();
            $table->string('device_family', 32)->nullable();
            $table->timestamp('first_seen_at');
            $table->timestamp('last_seen_at');
            $table->timestamp('revoked_at')->nullable();
            $table->timestamps();

            $table->unique(['user_id', 'session_id_hash']);
            $table->index(['user_id', 'revoked_at', 'last_seen_at']);
        });

        Schema::create('password_reset_tokens', function (Blueprint $table): void {
            $table->string('email')->primary();
            $table->string('token');
            $table->timestamp('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('password_reset_tokens');
        Schema::dropIfExists('account_sessions');
        Schema::dropIfExists('passkeys');
        Schema::dropIfExists('account_identities');
        Schema::dropIfExists('users');
    }
};
