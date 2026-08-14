<?php

declare(strict_types=1);

use App\Domain\Events\Catalog\KingShotEventTypeCatalog;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('event_types', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->string('slug', 96)->unique();
            $table->string('name_key', 180);
            $table->string('description_key', 180)->nullable();
            $table->string('category', 48)->index();
            $table->string('icon_key', 64)->nullable();
            $table->boolean('is_system')->default(true);
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();
        });

        Schema::create('event_type_scopes', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('event_type_id')->constrained('event_types')->cascadeOnDelete();
            $table->string('scope', 16);
            $table->string('view_permission_key', 96);
            $table->string('create_permission_key', 96);
            $table->string('manage_permission_key', 96);
            $table->unsignedInteger('default_duration_minutes')->nullable();
            $table->unsignedInteger('default_capacity')->nullable();
            $table->string('schedule_source', 32)->default('game_calendar');
            $table->string('recurrence_policy', 32)->default('disabled');
            $table->string('default_recurrence_frequency', 24)->default('none');
            $table->unsignedInteger('default_recurrence_interval')->default(1);
            $table->unsignedInteger('minimum_repeat_interval_minutes')->nullable();
            $table->unsignedInteger('default_registration_opens_minutes_before')->nullable();
            $table->unsignedInteger('default_registration_closes_minutes_before')->nullable();
            $table->string('default_instructions_key', 180)->nullable();
            $table->json('default_settings')->nullable();
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['event_type_id', 'scope']);
            $table->unique(['id', 'event_type_id', 'scope']);
            $table->index(['scope', 'is_active', 'sort_order']);
        });

        Schema::create('event_type_capabilities', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('event_type_scope_id')->constrained('event_type_scopes')->cascadeOnDelete();
            $table->string('capability', 48);
            $table->json('configuration')->nullable();

            $table->unique(['event_type_scope_id', 'capability']);
        });

        $now = now();
        foreach (KingShotEventTypeCatalog::definitions() as $definition) {
            $typeId = (string) Str::ulid();
            DB::table('event_types')->insert([
                'id' => $typeId,
                'slug' => $definition['slug'],
                'name_key' => $definition['name_key'],
                'description_key' => $definition['description_key'],
                'category' => $definition['category']->value,
                'icon_key' => $definition['icon_key'],
                'is_system' => true,
                'is_active' => true,
                'sort_order' => $definition['sort_order'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            foreach ($definition['scopes'] as $index => $scope) {
                $scopeId = (string) Str::ulid();
                DB::table('event_type_scopes')->insert([
                    'id' => $scopeId,
                    'event_type_id' => $typeId,
                    'scope' => $scope['scope']->value,
                    'view_permission_key' => $scope['view_permission']->value,
                    'create_permission_key' => $scope['create_permission']->value,
                    'manage_permission_key' => $scope['manage_permission']->value,
                    'default_duration_minutes' => $scope['default_duration_minutes'],
                    'default_capacity' => $scope['default_capacity'],
                    'schedule_source' => $scope['schedule_source']->value,
                    'recurrence_policy' => $scope['recurrence_policy']->value,
                    'default_recurrence_frequency' => $scope['default_recurrence_frequency']->value,
                    'default_recurrence_interval' => $scope['default_recurrence_interval'],
                    'minimum_repeat_interval_minutes' => $scope['minimum_repeat_interval_minutes'],
                    'default_registration_opens_minutes_before' => $scope['default_registration_opens_minutes_before'],
                    'default_registration_closes_minutes_before' => $scope['default_registration_closes_minutes_before'],
                    'default_instructions_key' => $scope['default_instructions_key'],
                    'default_settings' => empty($scope['default_settings']) ? null : json_encode($scope['default_settings'], JSON_THROW_ON_ERROR),
                    'is_active' => true,
                    'sort_order' => $index,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);

                foreach ($scope['capabilities'] as $capability) {
                    $configuration = $scope['capability_configuration'][$capability->value] ?? null;
                    DB::table('event_type_capabilities')->insert([
                        'id' => (string) Str::ulid(),
                        'event_type_scope_id' => $scopeId,
                        'capability' => $capability->value,
                        'configuration' => $configuration === null ? null : json_encode($configuration, JSON_THROW_ON_ERROR),
                    ]);
                }
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('event_type_capabilities');
        Schema::dropIfExists('event_type_scopes');
        Schema::dropIfExists('event_types');
    }
};
