<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;

final class EventContributionHistoryArchitectureTest extends TestCase
{
    public function test_living_architecture_preserves_durable_historical_identity(): void
    {
        $ownership = $this->read('docs/architecture/data-ownership.md');

        self::assertStringContainsString('Historical facts should retain durable Player/Alliance/Kingdom/Event identifiers', $ownership);
        self::assertStringContainsString('Later membership or placement changes must not silently rewrite history', $ownership);
        self::assertStringContainsString('Operations owns operational Event state', $ownership);
        self::assertStringContainsString('Intelligence owns observations/analytical history', $ownership);
    }

    public function test_product_terminology_keeps_observation_distinct_from_gameworld_identity(): void
    {
        $intelligence = $this->read('docs/architecture/contexts/intelligence/README.md');
        self::assertStringContainsString('does not duplicate neutral GameWorld identity as writable identity state', $intelligence);
    }

    public function test_current_event_scope_model_has_exact_historical_owner_types(): void
    {
        $scope = $this->read('app/Contexts/Operations/EventCore/Enums/EventScope.php');

        self::assertStringContainsString("case Player = 'player';", $scope);
        self::assertStringContainsString("case Alliance = 'alliance';", $scope);
        self::assertStringContainsString("case Kingdom = 'kingdom';", $scope);
    }

    public function test_player_event_results_use_durable_player_identity_not_membership_identity(): void
    {
        $result = $this->read('app/Contexts/Operations/Results/Models/EventPlayerResult.php');

        self::assertStringContainsString("'player_id'", $result);
        self::assertStringNotContainsString('membership_id', $result);
        self::assertStringNotContainsString('user_id', $result);
    }

    public function test_greenfield_event_schema_protects_historical_target_identity(): void
    {
        $migration = $this->read('database/migrations/2026_08_08_141500_create_event_scheduling_tables.php');

        self::assertStringContainsString("\$table->string('target_display_name', 180);", $migration);
        self::assertStringContainsString("\$table->string('target_secondary_label', 180)->nullable();", $migration);
        self::assertStringContainsString("->constrained('alliances')->restrictOnDelete()", $migration);
        self::assertStringContainsString("->constrained('kingdoms')->restrictOnDelete()", $migration);
        self::assertStringContainsString("->constrained('players')->restrictOnDelete()", $migration);
        self::assertStringContainsString('events_historical_target_immutable_guard', $migration);
    }

    public function test_metric_catalogue_keeps_score_semantics_separate_from_component_metrics(): void
    {
        $catalogue = $this->read('app/Contexts/Operations/Results/Catalog/KingShotEventMetricCatalog.php');
        $typeMigration = $this->read('database/migrations/2026_08_07_020000_create_event_type_catalogue_tables.php');
        $seedMigration = $this->read('database/migrations/2026_08_13_071000_seed_event_metric_catalogue.php');

        self::assertStringContainsString("'score' => \$score", $catalogue);
        self::assertStringContainsString("'metrics' => \$metrics", $catalogue);
        self::assertStringContainsString('result_score_label_key', $typeMigration);
        self::assertStringContainsString('result_score_unit', $typeMigration);
        self::assertStringContainsString('result_score_higher_is_better', $typeMigration);
        self::assertStringContainsString('KingShotEventMetricCatalog::profile', $seedMigration);
        self::assertStringNotContainsString('Schema::table', $seedMigration);
    }

    public function test_metric_subjects_use_canonical_alliance_identity(): void
    {
        $subject = $this->read('app/Contexts/Operations/Results/Enums/EventMetricSubject.php');
        $catalogue = $this->read('app/Contexts/Operations/Results/Catalog/KingShotEventMetricCatalog.php');

        self::assertStringContainsString("case Event = 'event';", $subject);
        self::assertStringContainsString("case Alliance = 'alliance';", $subject);
        self::assertStringContainsString("case Player = 'player';", $subject);
        self::assertStringNotContainsString('KingdomAlliance', $subject);
        self::assertStringNotContainsString('KingdomAlliance', $catalogue);
    }

    private function read(string $path): string
    {
        $source = file_get_contents($this->root().'/'.$path);
        self::assertIsString($source, $path);

        return $source;
    }

    private function root(): string
    {
        return dirname(__DIR__, 2);
    }
}
