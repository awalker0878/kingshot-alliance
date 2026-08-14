<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('invitations', function (Blueprint $table): void {
            $table->unique(['id', 'alliance_id'], 'invitations_id_alliance_unique');
        });

        Schema::create('recruitment_settings', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->ulid('alliance_id')->unique();
            $table->string('application_mode', 24)->default('public')->index();
            $table->string('title', 160)->default('Join our alliance');
            $table->text('introduction')->nullable();
            $table->unsignedSmallInteger('retention_unsuccessful_days')->default(90);
            $table->boolean('is_open')->default(true)->index();
            $table->foreignUlid('created_by_player_id')->constrained('players')->restrictOnDelete();
            $table->foreignUlid('updated_by_player_id')->constrained('players')->restrictOnDelete();
            $table->timestamps();

            $table->foreign('alliance_id')->references('id')->on('alliances')->cascadeOnDelete();
            $table->unique(['id', 'alliance_id']);
        });

        Schema::create('recruitment_questions', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->ulid('alliance_id');
            $table->string('prompt', 240);
            $table->text('help_text')->nullable();
            $table->string('question_type', 24)->default('short_text');
            $table->json('options')->nullable();
            $table->boolean('is_required')->default(false);
            $table->unsignedSmallInteger('position')->default(0);
            $table->boolean('is_active')->default(true)->index();
            $table->foreignUlid('created_by_player_id')->constrained('players')->restrictOnDelete();
            $table->foreignUlid('updated_by_player_id')->constrained('players')->restrictOnDelete();
            $table->timestamps();

            $table->foreign('alliance_id')->references('id')->on('alliances')->cascadeOnDelete();
            $table->unique(['id', 'alliance_id']);
            $table->index(['alliance_id', 'is_active', 'position']);
        });

        Schema::create('recruitment_application_invites', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->ulid('alliance_id');
            $table->string('email')->nullable();
            $table->char('token_hash', 64)->unique();
            $table->timestamp('expires_at')->index();
            $table->timestamp('used_at')->nullable();
            $table->foreignUlid('created_by_player_id')->constrained('players')->restrictOnDelete();
            $table->timestamps();

            $table->foreign('alliance_id')->references('id')->on('alliances')->cascadeOnDelete();
            $table->unique(['id', 'alliance_id']);
            $table->index(['alliance_id', 'email']);
        });

        Schema::create('recruitment_candidates', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->ulid('alliance_id');
            $table->foreignId('applicant_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignUlid('player_id')->nullable()->constrained('players')->nullOnDelete();
            $table->ulid('application_invite_id')->nullable();
            $table->ulid('membership_invitation_id')->nullable();
            $table->ulid('merged_into_id')->nullable();
            $table->string('full_name', 160);
            $table->string('email', 320);
            $table->string('contact_handle', 160)->nullable();
            $table->string('source', 120)->nullable()->index();
            $table->string('stage', 24)->default('new')->index();
            $table->timestamp('next_action_at')->nullable()->index();
            $table->timestamp('submitted_at')->index();
            $table->timestamp('first_responded_at')->nullable();
            $table->timestamp('accepted_at')->nullable();
            $table->timestamp('declined_at')->nullable();
            $table->timestamp('withdrawn_at')->nullable();
            $table->timestamp('joined_at')->nullable();
            $table->timestamp('retention_due_at')->nullable()->index();
            $table->foreignUlid('updated_by_player_id')->nullable()->constrained('players')->nullOnDelete();
            $table->timestamps();

            $table->foreign('alliance_id')->references('id')->on('alliances')->cascadeOnDelete();
            $table->foreign(['application_invite_id', 'alliance_id'])
                ->references(['id', 'alliance_id'])
                ->on('recruitment_application_invites')
                ->restrictOnDelete();
            $table->foreign(['membership_invitation_id', 'alliance_id'])
                ->references(['id', 'alliance_id'])
                ->on('invitations')
                ->restrictOnDelete();
            $table->unique(['id', 'alliance_id']);
            $table->index(['alliance_id', 'email']);
            $table->index(['alliance_id', 'stage', 'submitted_at']);
            $table->index(['alliance_id', 'next_action_at']);
        });

        Schema::table('recruitment_candidates', function (Blueprint $table): void {
            $table->foreign(['merged_into_id', 'alliance_id'])
                ->references(['id', 'alliance_id'])
                ->on('recruitment_candidates')
                ->restrictOnDelete();
        });

        Schema::create('recruitment_answers', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->ulid('alliance_id');
            $table->ulid('candidate_id');
            $table->ulid('question_id');
            $table->string('prompt_snapshot', 240);
            $table->string('question_type_snapshot', 24);
            $table->json('answer');
            $table->timestamps();

            $table->foreign('alliance_id')->references('id')->on('alliances')->cascadeOnDelete();
            $table->foreign(['candidate_id', 'alliance_id'])
                ->references(['id', 'alliance_id'])
                ->on('recruitment_candidates')
                ->cascadeOnDelete();
            $table->foreign(['question_id', 'alliance_id'])
                ->references(['id', 'alliance_id'])
                ->on('recruitment_questions')
                ->restrictOnDelete();
            $table->unique(['candidate_id', 'question_id']);
            $table->index(['alliance_id', 'candidate_id']);
        });

        Schema::create('recruitment_candidate_reviewers', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->ulid('alliance_id');
            $table->ulid('candidate_id');
            $table->foreignUlid('reviewer_player_id')->constrained('players')->restrictOnDelete();
            $table->foreignUlid('assigned_by_player_id')->constrained('players')->restrictOnDelete();
            $table->timestamps();

            $table->foreign('alliance_id')->references('id')->on('alliances')->cascadeOnDelete();
            $table->foreign(['candidate_id', 'alliance_id'])
                ->references(['id', 'alliance_id'])
                ->on('recruitment_candidates')
                ->cascadeOnDelete();
            $table->unique(['candidate_id', 'reviewer_player_id']);
            $table->index(['alliance_id', 'reviewer_player_id']);
        });

        Schema::create('recruitment_notes', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->ulid('alliance_id');
            $table->ulid('candidate_id');
            $table->foreignUlid('author_player_id')->constrained('players')->restrictOnDelete();
            $table->text('body');
            $table->timestamps();

            $table->foreign('alliance_id')->references('id')->on('alliances')->cascadeOnDelete();
            $table->foreign(['candidate_id', 'alliance_id'])
                ->references(['id', 'alliance_id'])
                ->on('recruitment_candidates')
                ->cascadeOnDelete();
            $table->index(['alliance_id', 'candidate_id', 'created_at']);
        });

        Schema::create('recruitment_tags', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->ulid('alliance_id');
            $table->string('name', 80);
            $table->timestamps();

            $table->foreign('alliance_id')->references('id')->on('alliances')->cascadeOnDelete();
            $table->unique(['id', 'alliance_id']);
            $table->unique(['alliance_id', 'name']);
        });

        Schema::create('recruitment_candidate_tags', function (Blueprint $table): void {
            $table->ulid('alliance_id');
            $table->ulid('candidate_id');
            $table->ulid('tag_id');
            $table->timestamps();

            $table->foreign('alliance_id')->references('id')->on('alliances')->cascadeOnDelete();
            $table->foreign(['candidate_id', 'alliance_id'])
                ->references(['id', 'alliance_id'])
                ->on('recruitment_candidates')
                ->cascadeOnDelete();
            $table->foreign(['tag_id', 'alliance_id'])
                ->references(['id', 'alliance_id'])
                ->on('recruitment_tags')
                ->cascadeOnDelete();
            $table->primary(['candidate_id', 'tag_id']);
            $table->index(['alliance_id', 'tag_id']);
        });

        Schema::create('recruitment_stage_history', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->ulid('alliance_id');
            $table->ulid('candidate_id');
            $table->string('from_stage', 24)->nullable();
            $table->string('to_stage', 24);
            $table->text('reason')->nullable();
            $table->foreignUlid('changed_by_player_id')->nullable()->constrained('players')->nullOnDelete();
            $table->timestamp('changed_at')->index();
            $table->timestamps();

            $table->foreign('alliance_id')->references('id')->on('alliances')->cascadeOnDelete();
            $table->foreign(['candidate_id', 'alliance_id'])
                ->references(['id', 'alliance_id'])
                ->on('recruitment_candidates')
                ->cascadeOnDelete();
            $table->index(['alliance_id', 'candidate_id', 'changed_at']);
        });

        Schema::create('recruitment_decision_templates', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->ulid('alliance_id');
            $table->string('name', 120);
            $table->string('decision_stage', 24)->index();
            $table->string('subject', 200);
            $table->text('body');
            $table->boolean('is_active')->default(true)->index();
            $table->foreignUlid('created_by_player_id')->constrained('players')->restrictOnDelete();
            $table->foreignUlid('updated_by_player_id')->constrained('players')->restrictOnDelete();
            $table->timestamps();

            $table->foreign('alliance_id')->references('id')->on('alliances')->cascadeOnDelete();
            $table->unique(['id', 'alliance_id']);
            $table->unique(['alliance_id', 'name']);
        });

        Schema::create('recruitment_communications', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->ulid('alliance_id');
            $table->ulid('candidate_id');
            $table->ulid('template_id')->nullable();
            $table->string('channel', 24)->default('email');
            $table->string('subject', 200);
            $table->text('body');
            $table->string('status', 24)->default('prepared')->index();
            $table->char('idempotency_key', 64)->unique();
            $table->foreignUlid('created_by_player_id')->constrained('players')->restrictOnDelete();
            $table->timestamp('sent_at')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamps();

            $table->foreign('alliance_id')->references('id')->on('alliances')->cascadeOnDelete();
            $table->foreign(['candidate_id', 'alliance_id'])
                ->references(['id', 'alliance_id'])
                ->on('recruitment_candidates')
                ->cascadeOnDelete();
            $table->foreign(['template_id', 'alliance_id'])
                ->references(['id', 'alliance_id'])
                ->on('recruitment_decision_templates')
                ->restrictOnDelete();
            $table->unique(['id', 'alliance_id']);
            $table->index(['alliance_id', 'candidate_id', 'created_at']);
        });

        Schema::create('recruitment_onboarding_items', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->ulid('alliance_id');
            $table->string('name', 160);
            $table->text('description')->nullable();
            $table->unsignedSmallInteger('position')->default(0);
            $table->boolean('is_required')->default(true);
            $table->boolean('is_active')->default(true)->index();
            $table->foreignUlid('created_by_player_id')->constrained('players')->restrictOnDelete();
            $table->foreignUlid('updated_by_player_id')->constrained('players')->restrictOnDelete();
            $table->timestamps();

            $table->foreign('alliance_id')->references('id')->on('alliances')->cascadeOnDelete();
            $table->unique(['id', 'alliance_id']);
            $table->unique(['alliance_id', 'name']);
        });

        Schema::create('recruitment_candidate_onboarding', function (Blueprint $table): void {
            $table->ulid('id')->primary();
            $table->ulid('alliance_id');
            $table->ulid('candidate_id');
            $table->ulid('onboarding_item_id');
            $table->string('status', 24)->default('pending')->index();
            $table->timestamp('completed_at')->nullable();
            $table->foreignUlid('completed_by_player_id')->nullable()->constrained('players')->nullOnDelete();
            $table->timestamps();

            $table->foreign('alliance_id')->references('id')->on('alliances')->cascadeOnDelete();
            $table->foreign(['candidate_id', 'alliance_id'])
                ->references(['id', 'alliance_id'])
                ->on('recruitment_candidates')
                ->cascadeOnDelete();
            $table->foreign(['onboarding_item_id', 'alliance_id'])
                ->references(['id', 'alliance_id'])
                ->on('recruitment_onboarding_items')
                ->restrictOnDelete();
            $table->unique(['candidate_id', 'onboarding_item_id']);
            $table->index(['alliance_id', 'candidate_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('recruitment_candidate_onboarding');
        Schema::dropIfExists('recruitment_onboarding_items');
        Schema::dropIfExists('recruitment_communications');
        Schema::dropIfExists('recruitment_decision_templates');
        Schema::dropIfExists('recruitment_stage_history');
        Schema::dropIfExists('recruitment_candidate_tags');
        Schema::dropIfExists('recruitment_tags');
        Schema::dropIfExists('recruitment_notes');
        Schema::dropIfExists('recruitment_candidate_reviewers');
        Schema::dropIfExists('recruitment_answers');
        Schema::dropIfExists('recruitment_candidates');
        Schema::dropIfExists('recruitment_application_invites');
        Schema::dropIfExists('recruitment_questions');
        Schema::dropIfExists('recruitment_settings');

        Schema::table('invitations', function (Blueprint $table): void {
            $table->dropUnique('invitations_id_alliance_unique');
        });
    }
};
