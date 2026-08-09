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

    public function test_transfer_events_remain_inside_the_existing_kingdoms_webhook_boundary(): void
    {
        $queue = file_get_contents(dirname(__DIR__, 2).'/app/Domain/Integrations/Actions/QueueWebhookDeliveries.php');
        self::assertIsString($queue);
        self::assertStringContainsString("str_starts_with(\$eventType, 'kingdoms.')", $queue);
    }

    public function test_transfer_runtime_stays_under_the_kingdoms_domain(): void
    {
        $root = dirname(__DIR__, 2).'/app/Domain/Kingdoms/';

        foreach ([
            'Models/TransferPlan.php',
            'Models/TransferParticipant.php',
            'Models/TransferGroup.php',
            'Queries/TransferPlanQuery.php',
            'Queries/TransferParticipantQuery.php',
            'Queries/TransferGroupQuery.php',
            'Enums/TransferDirection.php',
            'Enums/TransferGroupState.php',
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
            'Http/Controllers/TransferPlanController.php',
            'Http/Controllers/TransferParticipantController.php',
            'Http/Controllers/TransferGroupController.php',
        ] as $path) {
            self::assertFileExists($root.$path);
        }
    }
}
