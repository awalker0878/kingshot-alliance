<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('territory_plans', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('kingdom_id')->constrained('kingdoms')->restrictOnDelete();
            $table->foreignUlid('owner_alliance_id')->nullable()->constrained('alliances')->restrictOnDelete();
            $table->string('scope', 16);
            $table->string('name', 160);
            $table->string('status', 24)->default('draft')->index();
            $table->unsignedBigInteger('revision')->default(1);
            $table->string('map_dataset_id', 120);
            $table->char('map_dataset_checksum', 64);
            $table->json('planning_preferences')->nullable();
            $table->foreignUlid('created_by_player_id')->constrained('players')->restrictOnDelete();
            $table->foreignUlid('updated_by_player_id')->constrained('players')->restrictOnDelete();
            $table->timestamp('published_at')->nullable();
            $table->timestamps();

            $table->index(['scope', 'owner_alliance_id', 'status']);
            $table->index(['scope', 'kingdom_id', 'status']);
        });

        Schema::create('territory_plan_alliances', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('territory_plan_id')->constrained('territory_plans')->cascadeOnDelete();
            $table->string('plan_key', 120);
            $table->foreignUlid('alliance_id')->nullable()->constrained('alliances')->restrictOnDelete();
            $table->string('external_name', 160)->nullable();
            $table->string('external_tag', 32)->nullable();
            $table->string('display_name', 160);
            $table->string('presentation_color', 16)->default('#4da3ff');
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->boolean('visible')->default(true);
            $table->boolean('locked')->default(false);
            $table->timestamps();

            $table->unique(['territory_plan_id', 'plan_key']);
            $table->unique(['territory_plan_id', 'alliance_id']);
            $table->index(['territory_plan_id', 'sort_order']);
            $table->index(['alliance_id', 'territory_plan_id']);
        });

        Schema::create('territory_plan_groups', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('territory_plan_id')->constrained('territory_plans')->cascadeOnDelete();
            $table->string('plan_key', 120);
            $table->string('label', 160)->nullable();
            $table->timestamps();

            $table->unique(['territory_plan_id', 'plan_key']);
            $table->index('territory_plan_id');
        });

        Schema::create('territory_plan_objects', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('territory_plan_id')->constrained('territory_plans')->cascadeOnDelete();
            $table->string('plan_key', 120);
            $table->foreignUlid('territory_plan_alliance_id')->constrained('territory_plan_alliances')->cascadeOnDelete();
            $table->foreignUlid('group_id')->nullable()->constrained('territory_plan_groups')->nullOnDelete();
            $table->string('object_type', 40);
            $table->foreignUlid('player_id')->nullable()->constrained('players')->restrictOnDelete();
            $table->string('external_player_name', 160)->nullable();
            $table->string('label', 160)->nullable();
            $table->integer('coordinate_x');
            $table->integer('coordinate_y');
            $table->unsignedSmallInteger('rotation')->default(0);
            $table->unsignedSmallInteger('sort_order')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['territory_plan_id', 'plan_key']);
            $table->index(['territory_plan_id', 'object_type']);
            $table->index(['territory_plan_alliance_id', 'object_type']);
            $table->index(['player_id', 'territory_plan_id']);
        });

        Schema::create('territory_plan_revisions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('territory_plan_id')->constrained('territory_plans')->cascadeOnDelete();
            $table->unsignedBigInteger('revision_number');
            $table->unsignedSmallInteger('schema_version')->default(1);
            $table->string('map_dataset_id', 120);
            $table->char('map_dataset_checksum', 64);
            $table->json('snapshot');
            $table->char('snapshot_checksum', 64);
            $table->foreignUlid('published_by_player_id')->constrained('players')->restrictOnDelete();
            $table->timestamp('published_at');
            $table->timestamp('created_at');

            $table->unique(['territory_plan_id', 'revision_number']);
            $table->index(['territory_plan_id', 'published_at']);
        });

        Schema::create('event_territory_plan_revisions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('event_occurrence_id')->constrained('event_occurrences')->cascadeOnDelete();
            $table->foreignUlid('territory_plan_revision_id')->constrained('territory_plan_revisions')->restrictOnDelete();
            $table->string('purpose', 40)->default('positioning');
            $table->foreignUlid('created_by_player_id')->constrained('players')->restrictOnDelete();
            $table->timestamp('created_at');

            $table->unique(['event_occurrence_id', 'purpose'], 'event_territory_plan_purpose_unique');
        });

        $this->createPlanScopeGuard();
        $this->createAllianceIdentityGuard();
    }

    private function createPlanScopeGuard(): void
    {
        $expression = "((scope = 'alliance' AND owner_alliance_id IS NOT NULL) OR (scope = 'kingdom' AND owner_alliance_id IS NULL))";
        $driver = DB::connection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::statement("ALTER TABLE territory_plans ADD CONSTRAINT territory_plans_scope_target_check CHECK ({$expression})");
        }

        if ($driver === 'sqlite') {
            DB::statement("CREATE TRIGGER territory_plans_scope_target_insert BEFORE INSERT ON territory_plans WHEN NOT {$expression} BEGIN SELECT RAISE(ABORT, 'invalid territory plan scope target'); END");
            DB::statement("CREATE TRIGGER territory_plans_scope_target_update BEFORE UPDATE OF scope, owner_alliance_id ON territory_plans WHEN NOT {$expression} BEGIN SELECT RAISE(ABORT, 'invalid territory plan scope target'); END");
        }
    }

    private function createAllianceIdentityGuard(): void
    {
        $expression = '((alliance_id IS NOT NULL AND external_name IS NULL) OR (alliance_id IS NULL AND external_name IS NOT NULL))';
        $driver = DB::connection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::statement("ALTER TABLE territory_plan_alliances ADD CONSTRAINT territory_plan_alliances_identity_check CHECK ({$expression})");
        }

        if ($driver === 'sqlite') {
            DB::statement("CREATE TRIGGER territory_plan_alliances_identity_insert BEFORE INSERT ON territory_plan_alliances WHEN NOT {$expression} BEGIN SELECT RAISE(ABORT, 'invalid territory Alliance layer identity'); END");
            DB::statement("CREATE TRIGGER territory_plan_alliances_identity_update BEFORE UPDATE OF alliance_id, external_name ON territory_plan_alliances WHEN NOT {$expression} BEGIN SELECT RAISE(ABORT, 'invalid territory Alliance layer identity'); END");
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('event_territory_plan_revisions');
        Schema::dropIfExists('territory_plan_revisions');
        Schema::dropIfExists('territory_plan_objects');
        Schema::dropIfExists('territory_plan_groups');
        Schema::dropIfExists('territory_plan_alliances');
        Schema::dropIfExists('territory_plans');
    }
};
