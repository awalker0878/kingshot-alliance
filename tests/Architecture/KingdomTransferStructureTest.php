<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;

final class KingdomTransferStructureTest extends TestCase
{
    public function test_slice_a_schema_contains_only_transfer_cycle_foundation(): void
    {
        $migration = file_get_contents(dirname(__DIR__, 2).'/database/migrations/2026_08_09_090000_create_transfer_plans.php');
        self::assertIsString($migration);

        foreach ([
            'transfer_participant',
            'transfer_group',
            'readiness',
            'blocker',
            'destination_kingdom_id',
            'coordinator',
        ] as $futureField) {
            self::assertStringNotContainsString($futureField, $migration);
        }

        self::assertStringContainsString('transfer_plans_one_open_per_alliance', $migration);
        self::assertStringContainsString("WHERE state = 'open'", $migration);
    }

    public function test_slice_b_schema_contains_participant_planning_without_later_slice_fields(): void
    {
        $migration = file_get_contents(dirname(__DIR__, 2).'/database/migrations/2026_08_09_100000_create_transfer_participants.php');
        self::assertIsString($migration);

        foreach ([
            'transfer_plan_id',
            'direction',
            'roster_entry_id',
            'kingdom_player_id',
            'source_kingdom_id',
            'destination_kingdom_id',
            'manager_notes',
            'withdrawn_at',
        ] as $field) {
            self::assertStringContainsString($field, $migration);
        }

        foreach ([
            'transfer_group',
            'coordinator',
            'readiness',
            'blocker',
            'completed_at',
        ] as $futureField) {
            self::assertStringNotContainsString($futureField, $migration);
        }
    }

    public function test_slice_c1_schema_contains_group_coordination_without_readiness_or_completion(): void
    {
        $migration = file_get_contents(dirname(__DIR__, 2).'/database/migrations/2026_08_09_110000_create_transfer_groups.php');
        self::assertIsString($migration);

        foreach ([
            'transfer_groups',
            'transfer_group_id',
            'direction',
            'destination_kingdom_id',
            'state',
            'coordinator_membership_id',
            'manager_notes',
        ] as $field) {
            self::assertStringContainsString($field, $migration);
        }

        foreach ([
            'readiness',
            'blocker',
            'completed_at',
            'eligibility',
            'transfer_pass',
        ] as $futureField) {
            self::assertStringNotContainsString($futureField, $migration);
        }
    }

    public function test_slice_c2_schema_contains_manual_readiness_history_and_blockers_without_completion(): void
    {
        $migration = file_get_contents(dirname(__DIR__, 2).'/database/migrations/2026_08_09_120000_create_transfer_readiness_and_blockers.php');
        self::assertIsString($migration);

        foreach ([
            'readiness_state',
            'transfer_readiness_transitions',
            'from_state',
            'to_state',
            'actor_user_id',
            'transfer_blockers',
            'summary',
            'details',
            'resolved_at',
            'resolved_by_user_id',
        ] as $field) {
            self::assertStringContainsString($field, $migration);
        }

        foreach ([
            'completed_at',
            'completion',
            'handoff',
            'eligibility',
            'transfer_pass',
            'ticket_count',
            'resource_score',
        ] as $futureField) {
            self::assertStringNotContainsString($futureField, $migration);
        }
    }

    public function test_slice_d_schema_contains_only_explicit_completion_handoff(): void
    {
        $migration = file_get_contents(dirname(__DIR__, 2).'/database/migrations/2026_08_09_130000_create_transfer_completions.php');
        self::assertIsString($migration);

        foreach ([
            'transfer_completions',
            'transfer_plan_id',
            'transfer_participant_id',
            'roster_entry_id',
            'direction',
            'completed_by_user_id',
            'completed_at',
            "unique('transfer_participant_id')",
        ] as $field) {
            self::assertStringContainsString($field, $migration);
        }

        foreach ([
            'eligibility',
            'transfer_pass',
            'ticket_count',
            'resource_score',
            'snapshot_power',
            'bulk_complete',
            'automatic_transfer',
        ] as $futureField) {
            self::assertStringNotContainsString($futureField, $migration);
        }
    }

    public function test_whole_increment_keeps_tenant_first_query_indexes(): void
    {
        $root = dirname(__DIR__, 2).'/database/migrations/';
        $participants = file_get_contents($root.'2026_08_09_100000_create_transfer_participants.php');
        $groups = file_get_contents($root.'2026_08_09_110000_create_transfer_groups.php');
        $readiness = file_get_contents($root.'2026_08_09_120000_create_transfer_readiness_and_blockers.php');
        $completions = file_get_contents($root.'2026_08_09_130000_create_transfer_completions.php');
        self::assertIsString($participants);
        self::assertIsString($groups);
        self::assertIsString($readiness);
        self::assertIsString($completions);

        self::assertStringContainsString(
            "index(['alliance_id', 'transfer_plan_id', 'direction', 'withdrawn_at'])",
            $participants,
        );
        self::assertStringContainsString(
            "index(['alliance_id', 'transfer_plan_id', 'state'])",
            $groups,
        );
        self::assertStringContainsString(
            "index(['transfer_plan_id', 'readiness_state', 'withdrawn_at'])",
            $readiness,
        );
        self::assertStringContainsString(
            "index(['transfer_plan_id', 'transfer_participant_id', 'state'])",
            $readiness,
        );
        self::assertStringContainsString(
            "index(['alliance_id', 'transfer_plan_id', 'completed_at'])",
            $completions,
        );
        self::assertStringContainsString("unique('transfer_participant_id')", $completions);
    }

    public function test_transfer_events_remain_inside_the_existing_kingdoms_webhook_boundary(): void
    {
        $queue = file_get_contents(dirname(__DIR__, 2).'/app/Domain/Integrations/Actions/QueueWebhookDeliveries.php');
        self::assertIsString($queue);
        self::assertStringContainsString("str_starts_with(\$eventType, 'kingdoms.')", $queue);
    }

    public function test_transfer_planning_has_no_public_api_route_or_scope(): void
    {
        $apiRoutes = file_get_contents(dirname(__DIR__, 2).'/routes/api.php');
        self::assertIsString($apiRoutes);

        foreach ([
            '/transfers',
            '/transfer',
            '/kingdoms',
            'transfer:read',
            'kingdoms:read',
        ] as $publicContract) {
            self::assertStringNotContainsString($publicContract, $apiRoutes);
        }
    }

    public function test_whole_transfer_runtime_contains_no_eligibility_scoring_or_automatic_execution_contract(): void
    {
        $root = dirname(__DIR__, 2);
        $paths = [
            '/routes/kingdoms.php',
            '/database/migrations/2026_08_09_090000_create_transfer_plans.php',
            '/database/migrations/2026_08_09_100000_create_transfer_participants.php',
            '/database/migrations/2026_08_09_110000_create_transfer_groups.php',
            '/database/migrations/2026_08_09_120000_create_transfer_readiness_and_blockers.php',
            '/database/migrations/2026_08_09_130000_create_transfer_completions.php',
        ];

        foreach ($paths as $path) {
            $source = file_get_contents($root.$path);
            self::assertIsString($source);

            foreach ([
                'transfer_pass',
                'ticket_count',
                'resource_score',
                'eligibility_score',
                'player_score',
                'automatic_transfer',
                'bulk_complete',
            ] as $unapprovedContract) {
                self::assertStringNotContainsString($unapprovedContract, $source, $path);
            }
        }
    }

    public function test_transfer_runtime_stays_under_the_kingdoms_domain(): void
    {
        $root = dirname(__DIR__, 2).'/app/Domain/Kingdoms/';

        foreach ([
            'Models/TransferPlan.php',
            'Models/TransferParticipant.php',
            'Models/TransferGroup.php',
            'Models/TransferBlocker.php',
            'Models/TransferReadinessTransition.php',
            'Models/TransferCompletion.php',
            'Queries/TransferPlanQuery.php',
            'Queries/TransferParticipantQuery.php',
            'Queries/TransferGroupQuery.php',
            'Enums/TransferDirection.php',
            'Enums/TransferGroupState.php',
            'Enums/TransferReadinessState.php',
            'Enums/TransferBlockerState.php',
            'Actions/CreateTransferPlan.php',
            'Actions/TransitionTransferPlan.php',
            'Actions/OpenTransferPlan.php',
            'Actions/LockTransferPlan.php',
            'Actions/CloseTransferPlan.php',
            'Actions/CancelTransferPlan.php',
            'Actions/SaveTransferParticipant.php',
            'Actions/WithdrawTransferParticipant.php',
            'Actions/ResolveTransferKingdomPlayer.php',
            'Actions/SaveTransferGroup.php',
            'Actions/ArchiveTransferGroup.php',
            'Actions/AssignTransferParticipantGroup.php',
            'Actions/TransitionTransferReadiness.php',
            'Actions/CreateTransferBlocker.php',
            'Actions/ResolveTransferBlocker.php',
            'Actions/CompleteTransferParticipant.php',
            'Http/Controllers/TransferPlanController.php',
            'Http/Controllers/TransferParticipantController.php',
            'Http/Controllers/TransferGroupController.php',
            'Http/Controllers/TransferReadinessController.php',
            'Http/Controllers/TransferCompletionController.php',
        ] as $path) {
            self::assertFileExists($root.$path);
        }
    }
}
