<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;

final class KingdomAllianceStructureTest extends TestCase
{
    public function test_slice_a_schema_contains_only_neutral_identity_and_tenant_tracking(): void
    {
        $migration = file_get_contents(
            dirname(__DIR__, 2).'/database/migrations/2026_08_09_140000_create_kingdom_alliance_tracking.php',
        );
        self::assertIsString($migration);

        foreach ([
            'kingdom_alliances',
            'game_alliance_id',
            'current_name',
            'current_tag',
            'tracked_kingdom_alliances',
            'alliance_id',
            'kingdom_alliance_id',
            'kingdom_id',
            'manager_notes',
            'archived_at',
            'tracked_kingdom_alliances_one_active_per_reference',
            "WHERE state = 'active'",
        ] as $field) {
            self::assertStringContainsString($field, $migration);
        }

        foreach ([
            'observation',
            'power',
            'member_count',
            'diplomacy',
            'nap_',
            'contact',
            'threat',
            'score',
            'recommendation',
            'ingestion',
            'bot_',
            'webhook',
        ] as $futureField) {
            self::assertStringNotContainsString($futureField, $migration);
        }
    }

    public function test_slice_b_schema_is_append_oriented_observation_history_without_future_workflow_fields(): void
    {
        $migration = file_get_contents(
            dirname(__DIR__, 2).'/database/migrations/2026_08_09_150000_create_kingdom_alliance_observations.php',
        );
        self::assertIsString($migration);

        foreach ([
            'kingdom_alliance_observations',
            'alliance_id',
            'tracked_kingdom_alliance_id',
            'kingdom_alliance_id',
            'actor_user_id',
            'observed_name',
            'observed_tag',
            'power',
            'member_count',
            'captured_at',
            'source',
            'idempotency_key',
            'corrects_observation_id',
            'invalidated_at',
            'invalidated_by_user_id',
            'invalidation_reason',
        ] as $field) {
            self::assertStringContainsString($field, $migration);
        }

        foreach ([
            'diplomacy',
            'nap_',
            'contact',
            'threat',
            'rank',
            'score',
            'recommendation',
            'ingestion',
            'scrape',
            'ocr',
            'webhook',
        ] as $futureField) {
            self::assertStringNotContainsString($futureField, $migration);
        }
    }

    public function test_k3_runtime_stays_under_the_kingdoms_domain(): void
    {
        $root = dirname(__DIR__, 2).'/app/Domain/Kingdoms/';

        foreach ([
            'Enums/KingdomAllianceStatus.php',
            'Enums/TrackedKingdomAllianceState.php',
            'Models/KingdomAlliance.php',
            'Models/TrackedKingdomAlliance.php',
            'Models/KingdomAllianceObservation.php',
            'Actions/ResolveKingdomAlliance.php',
            'Actions/StartTrackingKingdomAlliance.php',
            'Actions/UpdateTrackedKingdomAlliance.php',
            'Actions/ArchiveTrackedKingdomAlliance.php',
            'Actions/RecordKingdomAllianceObservation.php',
            'Actions/InvalidateKingdomAllianceObservation.php',
            'Queries/KingdomAllianceQuery.php',
            'Queries/KingdomAllianceObservationQuery.php',
            'Http/Controllers/KingdomAllianceController.php',
            'Http/Controllers/KingdomAllianceObservationController.php',
        ] as $path) {
            self::assertFileExists($root.$path);
        }
    }

    public function test_kingdom_alliance_events_remain_inside_existing_internal_webhook_boundary(): void
    {
        $queue = file_get_contents(dirname(__DIR__, 2).'/app/Domain/Integrations/Actions/QueueWebhookDeliveries.php');
        self::assertIsString($queue);
        self::assertStringContainsString("str_starts_with(\$eventType, 'kingdoms.')", $queue);
    }

    public function test_k3_adds_no_public_kingdom_alliance_api_contract(): void
    {
        $apiRoutes = file_get_contents(dirname(__DIR__, 2).'/routes/api.php');
        self::assertIsString($apiRoutes);

        foreach ([
            'kingdom-alliances',
            'kingdom_alliances',
            'alliance-observations',
            'diplomacy',
            'kingdoms:read',
            'diplomacy:read',
        ] as $publicContract) {
            self::assertStringNotContainsString($publicContract, $apiRoutes);
        }
    }

    public function test_slice_b_routes_expose_observation_history_but_no_later_k3_or_k4_behavior(): void
    {
        $routes = file_get_contents(dirname(__DIR__, 2).'/routes/kingdoms.php');
        self::assertIsString($routes);

        self::assertStringContainsString('/alliance/kingdom-alliances', $routes);
        self::assertStringContainsString('/observations', $routes);
        self::assertStringContainsString('/history', $routes);

        foreach ([
            'diplomacy',
            'contacts',
            'threat-score',
            'recommendation',
            'ingestion',
        ] as $futureContract) {
            self::assertStringNotContainsString($futureContract, $routes);
        }
    }
}
