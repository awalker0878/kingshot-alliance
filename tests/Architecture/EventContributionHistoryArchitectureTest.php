<?php

declare(strict_types=1);

namespace Tests\Architecture;

use PHPUnit\Framework\TestCase;

final class EventContributionHistoryArchitectureTest extends TestCase
{
    public function test_historical_event_ownership_adr_is_accepted_and_indexed(): void
    {
        $adrPath = $this->root().'/docs/adr/0011-event-history-and-contribution-ownership.md';
        self::assertFileExists($adrPath);

        $adr = $this->read('docs/adr/0011-event-history-and-contribution-ownership.md');
        $index = $this->read('docs/adr/README.md');

        self::assertStringContainsString('- **Status:** Accepted', $adr);
        self::assertStringContainsString('Player history follows durable `player_id`', $adr);
        self::assertStringContainsString('current membership', strtolower($adr));
        self::assertStringContainsString('0011-event-history-and-contribution-ownership.md', $index);
    }

    public function test_event_history_contract_preserves_player_alliance_and_kingdom_axes(): void
    {
        $contract = $this->read('docs/domains/events/event-contribution-history.md');

        foreach ([
            'Player history follows Player.',
            "Alliance history follows the Event's Alliance target.",
            "Kingdom history follows the Event's Kingdom target.",
            'Current membership never rewrites historical ownership.',
            'player_id',
            'alliance_id',
            'kingdom_id',
            'Platform Administrator status does not bypass',
        ] as $rule) {
            self::assertStringContainsString($rule, $contract, $rule);
        }
    }

    public function test_contributions_contract_does_not_make_event_facts_a_second_canonical_ledger(): void
    {
        $contributions = $this->read('docs/domains/contributions/README.md');
        $composition = $this->read('docs/domains/contributions/event-reconciliation.md');

        self::assertStringContainsString('does not create a second canonical Event ledger', $contributions);
        self::assertStringContainsString('no Events-to-Contributions reconciliation/materialization workflow exists in the final model', $composition);
        self::assertStringContainsString('Current membership is authority/eligibility context only', $composition);
    }

    public function test_current_event_scope_model_still_has_exact_historical_owner_types(): void
    {
        $scope = $this->read('app/Domain/Events/Enums/EventScope.php');

        self::assertStringContainsString("case Player = 'player';", $scope);
        self::assertStringContainsString("case Alliance = 'alliance';", $scope);
        self::assertStringContainsString("case Kingdom = 'kingdom';", $scope);
    }

    public function test_player_event_results_use_durable_player_identity_not_membership_identity(): void
    {
        $result = $this->read('app/Domain/Events/Models/EventPlayerResult.php');

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
        self::assertStringContainsString('event historical target is immutable', $migration);
    }

    public function test_greenfield_result_schema_uses_alliance_identity_normalized_metrics_and_historical_context(): void
    {
        $migration = $this->read('database/migrations/2026_08_13_070000_create_event_result_tables.php');

        foreach ([
            'event_metric_definitions',
            'event_player_contexts',
            'event_alliance_results',
            'event_result_metrics',
            'event_alliance_result_metrics',
            'event_player_result_metrics',
            'represented_alliance_id',
            'kingdom_id_at_event',
            'context_frozen_at',
            "string('dimension_kind', 32)->nullable()",
            "foreignUlid('alliance_id')->constrained('alliances')->restrictOnDelete()",
            'event_alliance_results_validate_kingdom',
            "decimal('value', 30, 4)",
        ] as $contract) {
            self::assertStringContainsString($contract, $migration, $contract);
        }

        self::assertStringNotContainsString('event_kingdom_alliance_results', $migration);
        self::assertStringNotContainsString('represented_kingdom_alliance_id', $migration);
        self::assertStringNotContainsString("\$table->json('metrics')", $migration);
    }

    public function test_metric_catalogue_keeps_score_semantics_separate_from_component_metrics(): void
    {
        $catalogue = $this->read('app/Domain/Events/Catalog/KingShotEventMetricCatalog.php');
        $typeMigration = $this->read('database/migrations/2026_08_07_020000_create_event_type_catalogue_tables.php');
        $seedMigration = $this->read('database/migrations/2026_08_13_071000_seed_event_metric_catalogue.php');
        $capability = $this->read('docs/domains/events/event-metric-catalogue.md');
        $domainIndex = $this->read('docs/domains/events/README.md');

        self::assertStringContainsString("'score' => \$score", $catalogue);
        self::assertStringContainsString("'metrics' => \$metrics", $catalogue);
        self::assertStringContainsString('result_score_label_key', $typeMigration);
        self::assertStringContainsString('result_score_unit', $typeMigration);
        self::assertStringContainsString('result_score_higher_is_better', $typeMigration);
        self::assertStringContainsString('KingShotEventMetricCatalog::profile', $seedMigration);
        self::assertStringNotContainsString('Schema::table', $seedMigration);
        self::assertStringContainsString('## 12. Related documentation', $capability);
        self::assertStringContainsString('event-metric-catalogue.md', $domainIndex);
    }

    public function test_metric_subjects_use_canonical_alliance_identity_and_not_kingdom_alliance_identity(): void
    {
        $subject = $this->read('app/Domain/Events/Enums/EventMetricSubject.php');
        $catalogue = $this->read('app/Domain/Events/Catalog/KingShotEventMetricCatalog.php');
        $resultMigration = $this->read('database/migrations/2026_08_13_070000_create_event_result_tables.php');

        self::assertStringContainsString("case Event = 'event';", $subject);
        self::assertStringContainsString("case Alliance = 'alliance';", $subject);
        self::assertStringContainsString("case Player = 'player';", $subject);
        self::assertStringNotContainsString('KingdomAlliance', $subject);
        self::assertStringNotContainsString('KingdomAlliance', $catalogue);
        self::assertStringNotContainsString('kingdom_alliance', $resultMigration);
    }

    public function test_metric_catalogue_does_not_define_a_universal_cross_event_contribution_score(): void
    {
        $catalogue = strtolower($this->read('app/Domain/Events/Catalog/KingShotEventMetricCatalog.php'));
        $contract = strtolower($this->read('docs/domains/events/event-contribution-history.md'));

        self::assertStringNotContainsString('universal_contribution_score', $catalogue);
        self::assertStringNotContainsString('total_contribution_score', $catalogue);
        self::assertStringContainsString('does not create an unexplained universal total', $contract);
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
