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

    public function test_slice_c1_schema_has_explicit_diplomacy_state_and_append_history_without_contacts_or_scoring(): void
    {
        $migration = file_get_contents(
            dirname(__DIR__, 2).'/database/migrations/2026_08_10_090000_create_kingdom_alliance_diplomacy.php',
        );
        self::assertIsString($migration);

        foreach ([
            'kingdom_alliance_diplomacy_relationships',
            'kingdom_alliance_diplomacy_transitions',
            'alliance_id',
            'tracked_kingdom_alliance_id',
            'kingdom_alliance_id',
            'current_state',
            'effective_at',
            'review_at',
            'expires_at',
            'terms',
            'rationale',
            'last_transition_user_id',
            'from_state',
            'to_state',
            'actor_user_id',
        ] as $field) {
            self::assertStringContainsString($field, $migration);
        }

        foreach ([
            'contact',
            'phone',
            'address',
            'handle',
            'kingdom_player_id',
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

    public function test_slice_c2_schema_has_private_contacts_without_identity_authorization_or_future_scoring_fields(): void
    {
        $migration = file_get_contents(
            dirname(__DIR__, 2).'/database/migrations/2026_08_10_100000_create_kingdom_alliance_diplomacy_contacts.php',
        );
        self::assertIsString($migration);

        foreach ([
            'kingdom_alliance_diplomacy_contacts',
            'alliance_id',
            'tracked_kingdom_alliance_id',
            'kingdom_alliance_id',
            'display_name',
            'game_role',
            'channel_type',
            'handle',
            'state',
            'last_verified_at',
            'manager_notes',
            'created_by_user_id',
            'updated_by_user_id',
            'deactivated_at',
            'deactivated_by_user_id',
        ] as $field) {
            self::assertStringContainsString($field, $migration);
        }

        foreach ([
            'kingdom_player_id',
            'alliance_membership_id',
            'role_id',
            'permission_id',
            'phone',
            'home_address',
            'password',
            'credential',
            'recovery_secret',
            'threat',
            'rank',
            'score',
            'recommendation',
            'ingestion',
            'scrape',
            'ocr',
            'webhook',
        ] as $forbiddenField) {
            self::assertStringNotContainsString($forbiddenField, $migration);
        }
    }

    public function test_k3_runtime_stays_under_the_kingdoms_domain(): void
    {
        $root = dirname(__DIR__, 2).'/app/Domain/Kingdoms/';

        foreach ([
            'Enums/KingdomAllianceStatus.php',
            'Enums/TrackedKingdomAllianceState.php',
            'Enums/KingdomAllianceDiplomacyState.php',
            'Enums/KingdomAllianceContactChannel.php',
            'Enums/KingdomAllianceContactState.php',
            'Models/KingdomAlliance.php',
            'Models/TrackedKingdomAlliance.php',
            'Models/KingdomAllianceObservation.php',
            'Models/KingdomAllianceDiplomacy.php',
            'Models/KingdomAllianceDiplomacyTransition.php',
            'Models/KingdomAllianceDiplomacyContact.php',
            'Actions/ResolveKingdomAlliance.php',
            'Actions/StartTrackingKingdomAlliance.php',
            'Actions/UpdateTrackedKingdomAlliance.php',
            'Actions/ArchiveTrackedKingdomAlliance.php',
            'Actions/RecordKingdomAllianceObservation.php',
            'Actions/InvalidateKingdomAllianceObservation.php',
            'Actions/TransitionKingdomAllianceDiplomacy.php',
            'Actions/SaveKingdomAllianceDiplomacyContact.php',
            'Actions/DeactivateKingdomAllianceDiplomacyContact.php',
            'Queries/KingdomAllianceQuery.php',
            'Queries/KingdomAllianceObservationQuery.php',
            'Queries/KingdomAllianceDiplomacyQuery.php',
            'Queries/KingdomAllianceDiplomacyContactQuery.php',
            'Queries/KingdomAllianceIntelligenceQuery.php',
            'Services/KingdomAllianceIntelligence.php',
            'Http/Controllers/KingdomAllianceController.php',
            'Http/Controllers/KingdomAllianceObservationController.php',
            'Http/Controllers/KingdomAllianceDiplomacyController.php',
            'Http/Controllers/KingdomAllianceDiplomacyContactController.php',
            'Http/Controllers/KingdomAllianceIntelligenceController.php',
        ] as $path) {
            self::assertFileExists($root.$path);
        }
    }

    public function test_slice_d_intelligence_is_descriptive_and_has_no_scoring_or_automatic_decision_engine(): void
    {
        $root = dirname(__DIR__, 2);
        $service = file_get_contents($root.'/app/Domain/Kingdoms/Services/KingdomAllianceIntelligence.php');
        $query = file_get_contents($root.'/app/Domain/Kingdoms/Queries/KingdomAllianceIntelligenceQuery.php');
        $controller = file_get_contents($root.'/app/Domain/Kingdoms/Http/Controllers/KingdomAllianceIntelligenceController.php');
        self::assertIsString($service);
        self::assertIsString($query);
        self::assertIsString($controller);

        foreach ([
            'SEVEN_DAY_WINDOW',
            'THIRTY_DAY_WINDOW',
            'observationQuality',
            'diplomacyStates',
            'relationshipsNeedingReview',
            'priorChange',
            'sevenDayChange',
            'thirtyDayChange',
            'contactDiagnostics',
        ] as $descriptiveContract) {
            self::assertStringContainsString($descriptiveContract, $service);
        }

        foreach ([
            'threatScore',
            'desirabilityScore',
            'targetScore',
            'compositeScore',
            'recommendedAction',
            'recommendationEngine',
            'autoTransition',
            'autoNegotiate',
            'rankedAlliances',
        ] as $forbiddenBehavior) {
            self::assertStringNotContainsString($forbiddenBehavior, $service);
            self::assertStringNotContainsString($forbiddenBehavior, $query);
            self::assertStringNotContainsString($forbiddenBehavior, $controller);
        }

        self::assertStringContainsString('captured_at desc', $query);
        self::assertStringContainsString('$days * 2', $query);
        self::assertStringContainsString("'tracking' =>", $controller);
        self::assertStringContainsString("'freshness' =>", $controller);
        self::assertStringContainsString("'diplomacy' =>", $controller);
        self::assertStringContainsString("'sort' =>", $controller);
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
            'contacts',
            'kingdoms:read',
            'diplomacy:read',
        ] as $publicContract) {
            self::assertStringNotContainsString($publicContract, $apiRoutes);
        }
    }

    public function test_slice_d_routes_add_read_only_intelligence_without_delete_or_automation_contracts(): void
    {
        $routes = file_get_contents(dirname(__DIR__, 2).'/routes/kingdoms.php');
        self::assertIsString($routes);

        self::assertStringContainsString('/alliance/kingdom-alliances', $routes);
        self::assertStringContainsString('/kingdom-alliances/intelligence', $routes);
        self::assertStringContainsString('/observations', $routes);
        self::assertStringContainsString('/history', $routes);
        self::assertStringContainsString('/diplomacy', $routes);
        self::assertStringContainsString('/diplomacy/transitions', $routes);
        self::assertStringContainsString('/diplomacy/contacts', $routes);
        self::assertStringContainsString('/deactivate', $routes);
        self::assertStringNotContainsString('/diplomacy/contacts/{contact}/delete', $routes);

        foreach ([
            'threat-score',
            'recommendation',
            'auto-diplomacy',
            'auto-transfer',
            'ingestion',
        ] as $futureContract) {
            self::assertStringNotContainsString($futureContract, $routes);
        }
    }
}
