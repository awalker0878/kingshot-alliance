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
