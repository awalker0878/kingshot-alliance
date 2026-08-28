<?php

declare(strict_types=1);

use App\Contexts\Operations\Events\Catalog\KingShotEventTypeCatalog;
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
            $table->string('verification_state', 24)->default('candidate')->index();
            $table->string('profile_state', 24)->default('disabled')->index();
            $table->string('source_label', 180)->nullable();
            $table->string('source_reference', 512)->nullable();
            $table->timestampTz('source_observed_at')->nullable();
            $table->string('game_version_boundary', 96)->nullable();
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
            $table->boolean('is_active')->default(true)->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestamps();

            $table->unique(['event_type_id', 'scope']);
            $table->unique(['id', 'event_type_id', 'scope']);
            $table->index(['scope', 'is_active', 'sort_order']);
        });

        Schema::create('event_type_workflow_dimensions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('event_type_id')->constrained('event_types')->cascadeOnDelete();
            $table->string('dimension', 48);

            $table->unique(['event_type_id', 'dimension']);
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
                'verification_state' => $definition['verification_state']->value,
                'profile_state' => $definition['profile_state']->value,
                'source_label' => $definition['source_label'],
                'source_reference' => $definition['source_reference'],
                'source_observed_at' => $definition['source_observed_at'],
                'game_version_boundary' => $definition['game_version_boundary'],
                'icon_key' => $definition['icon_key'],
                'is_system' => true,
                'is_active' => true,
                'sort_order' => $definition['sort_order'],
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            foreach ($definition['workflow_dimensions'] as $dimension) {
                DB::table('event_type_workflow_dimensions')->insert([
                    'id' => (string) Str::ulid(),
                    'event_type_id' => $typeId,
                    'dimension' => $dimension->value,
                ]);
            }

            foreach ($definition['scopes'] as $index => $scope) {
                DB::table('event_type_scopes')->insert([
                    'id' => (string) Str::ulid(),
                    'event_type_id' => $typeId,
                    'scope' => $scope['scope']->value,
                    'view_permission_key' => $scope['view_permission']->value,
                    'create_permission_key' => $scope['create_permission']->value,
                    'manage_permission_key' => $scope['manage_permission']->value,
                    'is_active' => true,
                    'sort_order' => $index,
                    'created_at' => $now,
                    'updated_at' => $now,
                ]);
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('event_type_workflow_dimensions');
        Schema::dropIfExists('event_type_scopes');
        Schema::dropIfExists('event_types');
    }
};
