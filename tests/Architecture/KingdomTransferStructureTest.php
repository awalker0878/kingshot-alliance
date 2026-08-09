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
            'Queries/TransferPlanQuery.php',
            'Actions/CreateTransferPlan.php',
            'Actions/TransitionTransferPlan.php',
            'Actions/OpenTransferPlan.php',
            'Actions/LockTransferPlan.php',
            'Actions/CloseTransferPlan.php',
            'Actions/CancelTransferPlan.php',
            'Http/Controllers/TransferPlanController.php',
        ] as $path) {
            self::assertFileExists($root.$path);
        }
    }
}
