<?php

declare(strict_types=1);

namespace Tests\Integration\Recruitment;

use Illuminate\Database\Migrations\Migration;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

final class RecruitmentMigrationRollbackTest extends TestCase
{
    use RefreshDatabase;

    /** @var list<string> */
    private const TABLES = [
        'recruitment_settings',
        'recruitment_questions',
        'recruitment_application_invites',
        'recruitment_candidates',
        'recruitment_answers',
        'recruitment_candidate_reviewers',
        'recruitment_notes',
        'recruitment_tags',
        'recruitment_candidate_tags',
        'recruitment_stage_history',
        'recruitment_decision_templates',
        'recruitment_communications',
        'recruitment_onboarding_items',
        'recruitment_candidate_onboarding',
    ];

    public function test_phase_four_migrations_roll_back_and_reapply_cleanly(): void
    {
        $recruitment = require database_path('migrations/2026_08_07_030000_create_recruitment_tables.php');
        $anonymization = require database_path('migrations/2026_08_07_031000_add_recruitment_anonymization.php');
        self::assertInstanceOf(Migration::class, $recruitment);
        self::assertInstanceOf(Migration::class, $anonymization);

        $anonymization->down();
        self::assertFalse(Schema::hasColumn('recruitment_candidates', 'anonymized_at'));

        $recruitment->down();
        foreach (self::TABLES as $table) {
            self::assertFalse(Schema::hasTable($table), sprintf('%s should be removed by Phase 4 rollback.', $table));
        }

        $recruitment->up();
        $anonymization->up();

        foreach (self::TABLES as $table) {
            self::assertTrue(Schema::hasTable($table), sprintf('%s should be restored by Phase 4 migration.', $table));
        }
        self::assertTrue(Schema::hasColumn('recruitment_candidates', 'anonymized_at'));
    }
}
