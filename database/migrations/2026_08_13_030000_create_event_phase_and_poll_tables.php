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
        Schema::create('event_phases', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('occurrence_id')->constrained('event_occurrences')->cascadeOnDelete();
            $table->string('key', 64);
            $table->string('name_key', 180)->nullable();
            $table->string('name', 160)->nullable();
            $table->string('phase_type', 32);
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->string('status', 16)->default('scheduled')->index();
            $table->unsignedInteger('sort_order')->default(0);
            $table->json('settings')->nullable();
            $table->foreignUlid('created_by_player_id')->nullable()->constrained('players')->nullOnDelete();
            $table->foreignUlid('updated_by_player_id')->nullable()->constrained('players')->nullOnDelete();
            $table->timestamps();

            $table->unique(['occurrence_id', 'key']);
            $table->index(['occurrence_id', 'sort_order']);
            $table->index(['occurrence_id', 'starts_at', 'ends_at']);
        });

        Schema::create('event_polls', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('occurrence_id')->constrained('event_occurrences')->cascadeOnDelete();
            $table->string('key', 64);
            $table->string('poll_type', 32)->default('choice');
            $table->string('question_key', 180)->nullable();
            $table->string('question', 500)->nullable();
            $table->timestamp('opens_at')->nullable();
            $table->timestamp('closes_at')->nullable();
            $table->string('status', 16)->default('draft')->index();
            $table->unsignedSmallInteger('max_choices')->default(1);
            $table->json('settings')->nullable();
            $table->foreignUlid('created_by_player_id')->constrained('players')->restrictOnDelete();
            $table->foreignUlid('updated_by_player_id')->nullable()->constrained('players')->restrictOnDelete();
            $table->timestamps();

            $table->unique(['occurrence_id', 'key']);
            $table->index(['occurrence_id', 'status', 'closes_at']);
        });

        Schema::create('event_poll_options', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('poll_id')->constrained('event_polls')->cascadeOnDelete();
            $table->string('label', 180);
            $table->string('value', 255);
            $table->unsignedInteger('sort_order')->default(0);
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['poll_id', 'value']);
            $table->unique(['id', 'poll_id'], 'event_poll_options_id_poll_unique');
            $table->index(['poll_id', 'sort_order']);
        });

        Schema::create('event_poll_votes', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->foreignUlid('poll_id')->constrained('event_polls')->cascadeOnDelete();
            $table->ulid('option_id');
            $table->foreignUlid('player_id')->constrained('players')->restrictOnDelete();
            $table->foreignUlid('cast_by_player_id')->constrained('players')->restrictOnDelete();
            $table->timestamp('cast_at');

            $table->unique(['poll_id', 'option_id', 'player_id']);
            $table->foreign(['option_id', 'poll_id'], 'event_poll_votes_option_poll_foreign')
                ->references(['id', 'poll_id'])
                ->on('event_poll_options')
                ->cascadeOnDelete();
            $table->index(['poll_id', 'player_id']);
            $table->index(['option_id', 'player_id']);
        });

        Schema::table('event_reminder_rules', function (Blueprint $table): void {
            $table->dropUnique('event_reminder_rules_definition_unique');
            $table->foreignUlid('poll_id')->nullable()->after('event_id')->constrained('event_polls')->cascadeOnDelete();
        });

        $driver = DB::connection()->getDriverName();
        if (in_array($driver, ['pgsql', 'sqlite'], true)) {
            DB::statement('CREATE UNIQUE INDEX event_reminder_rules_event_definition_unique ON event_reminder_rules (event_id, trigger_type, minutes_before, audience, channel) WHERE poll_id IS NULL');
            DB::statement('CREATE UNIQUE INDEX event_reminder_rules_poll_definition_unique ON event_reminder_rules (poll_id, trigger_type, minutes_before, audience, channel) WHERE poll_id IS NOT NULL');
        }

        $this->createContentGuards();
        $this->createReminderTriggerGuard();
    }

    private function createContentGuards(): void
    {
        $driver = DB::connection()->getDriverName();
        $phaseExpression = '((name_key IS NOT NULL AND length(trim(name_key)) > 0) OR (name IS NOT NULL AND length(trim(name)) > 0))';
        $pollExpression = '((question_key IS NOT NULL AND length(trim(question_key)) > 0) OR (question IS NOT NULL AND length(trim(question)) > 0))';

        if ($driver === 'pgsql') {
            DB::statement("ALTER TABLE event_phases ADD CONSTRAINT event_phases_name_check CHECK ({$phaseExpression})");
            DB::statement("ALTER TABLE event_polls ADD CONSTRAINT event_polls_question_check CHECK ({$pollExpression})");

            return;
        }

        if ($driver === 'sqlite') {
            DB::statement("CREATE TRIGGER event_phases_name_insert BEFORE INSERT ON event_phases WHEN NOT {$phaseExpression} BEGIN SELECT RAISE(ABORT, 'event phase name required'); END");
            DB::statement("CREATE TRIGGER event_phases_name_update BEFORE UPDATE OF name_key, name ON event_phases WHEN NOT {$phaseExpression} BEGIN SELECT RAISE(ABORT, 'event phase name required'); END");
            DB::statement("CREATE TRIGGER event_polls_question_insert BEFORE INSERT ON event_polls WHEN NOT {$pollExpression} BEGIN SELECT RAISE(ABORT, 'event poll question required'); END");
            DB::statement("CREATE TRIGGER event_polls_question_update BEFORE UPDATE OF question_key, question ON event_polls WHEN NOT {$pollExpression} BEGIN SELECT RAISE(ABORT, 'event poll question required'); END");
        }
    }

    private function createReminderTriggerGuard(): void
    {
        $expression = "((trigger_type = 'before_start' AND poll_id IS NULL) OR (trigger_type = 'before_poll_close' AND poll_id IS NOT NULL))";
        $driver = DB::connection()->getDriverName();

        if ($driver === 'pgsql') {
            DB::statement("ALTER TABLE event_reminder_rules ADD CONSTRAINT event_reminder_rules_trigger_reference_check CHECK ({$expression})");

            return;
        }

        if ($driver === 'sqlite') {
            DB::statement("CREATE TRIGGER event_reminder_rules_trigger_reference_insert BEFORE INSERT ON event_reminder_rules WHEN NOT {$expression} BEGIN SELECT RAISE(ABORT, 'invalid event reminder trigger reference'); END");
            DB::statement("CREATE TRIGGER event_reminder_rules_trigger_reference_update BEFORE UPDATE OF trigger_type, poll_id ON event_reminder_rules WHEN NOT {$expression} BEGIN SELECT RAISE(ABORT, 'invalid event reminder trigger reference'); END");
        }
    }

    public function down(): void
    {
        $driver = DB::connection()->getDriverName();
        if (in_array($driver, ['pgsql', 'sqlite'], true)) {
            DB::statement('DROP INDEX IF EXISTS event_reminder_rules_poll_definition_unique');
            DB::statement('DROP INDEX IF EXISTS event_reminder_rules_event_definition_unique');
        }
        if ($driver === 'pgsql') {
            DB::statement('ALTER TABLE event_reminder_rules DROP CONSTRAINT IF EXISTS event_reminder_rules_trigger_reference_check');
        }
        if ($driver === 'sqlite') {
            DB::statement('DROP TRIGGER IF EXISTS event_reminder_rules_trigger_reference_insert');
            DB::statement('DROP TRIGGER IF EXISTS event_reminder_rules_trigger_reference_update');
            DB::statement('DROP TRIGGER IF EXISTS event_phases_name_insert');
            DB::statement('DROP TRIGGER IF EXISTS event_phases_name_update');
            DB::statement('DROP TRIGGER IF EXISTS event_polls_question_insert');
            DB::statement('DROP TRIGGER IF EXISTS event_polls_question_update');
        }

        Schema::table('event_reminder_rules', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('poll_id');
            $table->unique(['event_id', 'trigger_type', 'minutes_before', 'audience', 'channel'], 'event_reminder_rules_definition_unique');
        });

        Schema::dropIfExists('event_poll_votes');
        Schema::dropIfExists('event_poll_options');
        Schema::dropIfExists('event_polls');
        Schema::dropIfExists('event_phases');
    }
};
