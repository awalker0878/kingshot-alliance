<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;

final class KingdomAllianceAcceptanceStructureTest extends TestCase
{
    public function test_whole_increment_keeps_tenant_first_indexes_and_append_history_boundaries(): void
    {
        $root = dirname(__DIR__, 2).'/database/migrations/';
        $tracking = file_get_contents($root.'2026_08_09_140000_create_kingdom_alliance_tracking.php');
        $observations = file_get_contents($root.'2026_08_09_150000_create_kingdom_alliance_observations.php');
        $diplomacy = file_get_contents($root.'2026_08_10_090000_create_kingdom_alliance_diplomacy.php');
        $contacts = file_get_contents($root.'2026_08_10_100000_create_kingdom_alliance_diplomacy_contacts.php');
        self::assertIsString($tracking);
        self::assertIsString($observations);
        self::assertIsString($diplomacy);
        self::assertIsString($contacts);

        foreach ([
            "index(['alliance_id', 'state', 'created_at'])",
            "index(['alliance_id', 'kingdom_id', 'state'])",
            "index(['alliance_id', 'kingdom_alliance_id'])",
            'tracked_kingdom_alliances_one_active_per_reference',
        ] as $contract) {
            self::assertStringContainsString($contract, $tracking);
        }

        foreach ([
            'ka_obs_alliance_idempotency_unique',
            'ka_obs_tracking_capture_idx',
            'ka_obs_tracking_acceptance_capture_idx',
            'ka_obs_correction_target_idx',
            'ka_obs_correction_target_fk',
        ] as $contract) {
            self::assertStringContainsString($contract, $observations);
        }

        foreach ([
            'ka_diplomacy_alliance_tracking_unique',
            'ka_diplomacy_state_review_idx',
            'ka_diplomacy_state_expiry_idx',
            'ka_diplomacy_transition_tracking_idx',
            'ka_diplomacy_transition_relation_idx',
        ] as $contract) {
            self::assertStringContainsString($contract, $diplomacy);
        }

        foreach ([
            'ka_diplomacy_contacts_tracking_state_idx',
            'ka_diplomacy_contacts_verified_idx',
        ] as $contract) {
            self::assertStringContainsString($contract, $contacts);
        }
    }

    public function test_whole_increment_keeps_contacts_out_of_identity_and_authorization(): void
    {
        $contacts = file_get_contents(
            dirname(__DIR__, 2).'/database/migrations/2026_08_10_100000_create_kingdom_alliance_diplomacy_contacts.php',
        );
        self::assertIsString($contacts);

        foreach ([
            'player_id',
            'alliance_membership_id',
            'role_id',
            'permission_id',
            'phone',
            'home_address',
            'password',
            'credential',
            'recovery_secret',
        ] as $forbiddenContract) {
            self::assertStringNotContainsString($forbiddenContract, $contacts);
        }
    }

    public function test_whole_increment_has_no_scoring_recommendation_automation_or_ingestion_contract(): void
    {
        $root = dirname(__DIR__, 2);
        $paths = [
            '/routes/kingdoms.php',
            '/database/migrations/2026_08_09_140000_create_kingdom_alliance_tracking.php',
            '/database/migrations/2026_08_09_150000_create_kingdom_alliance_observations.php',
            '/database/migrations/2026_08_10_090000_create_kingdom_alliance_diplomacy.php',
            '/database/migrations/2026_08_10_100000_create_kingdom_alliance_diplomacy_contacts.php',
            '/app/Domain/Kingdoms/Services/KingdomAllianceIntelligence.php',
            '/app/Domain/Kingdoms/Http/Controllers/KingdomAllianceIntelligenceController.php',
            '/app/Domain/Kingdoms/Actions/RecordKingdomAllianceObservation.php',
            '/app/Domain/Kingdoms/Actions/TransitionKingdomAllianceDiplomacy.php',
            '/app/Domain/Kingdoms/Actions/SaveKingdomAllianceDiplomacyContact.php',
        ];

        foreach ($paths as $path) {
            $source = file_get_contents($root.$path);
            self::assertIsString($source);

            foreach ([
                'threat_score',
                'desirability_score',
                'target_score',
                'composite_score',
                'recommended_action',
                'recommendation_engine',
                'auto_diplomacy',
                'auto_negotiate',
                'auto_transfer',
                'game_ingestion',
                'scrape_job',
                'ocr_job',
                'shared_intelligence',
            ] as $forbiddenContract) {
                self::assertStringNotContainsString($forbiddenContract, $source, $path);
            }
        }
    }

    public function test_whole_increment_adds_no_public_kingdoms_api_contract(): void
    {
        $api = file_get_contents(dirname(__DIR__, 2).'/routes/api.php');
        self::assertIsString($api);

        foreach ([
            'kingdom-alliances',
            'kingdom_alliances',
            'alliance-observations',
            'diplomacy',
            'kingdoms:read',
            'diplomacy:read',
            'intelligence:read',
        ] as $publicContract) {
            self::assertStringNotContainsString($publicContract, $api);
        }
    }
}
