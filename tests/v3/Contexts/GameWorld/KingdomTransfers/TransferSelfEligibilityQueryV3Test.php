<?php

declare(strict_types=1);

namespace Tests\v3\Contexts\GameWorld\KingdomTransfers;

use App\Contexts\Alliance\Lifecycle\ValueObjects\AllianceReference;
use App\Contexts\Alliance\Membership\ValueObjects\RosterEntryReference;
use App\Contexts\GameWorld\KingdomTransfers\Actions\CreateTransferPlan;
use App\Contexts\GameWorld\KingdomTransfers\Actions\SaveTransferParticipant;
use App\Contexts\GameWorld\KingdomTransfers\Actions\SaveTransferWindow;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferDirection;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferEligibilityOutcome;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferSourceType;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferParticipant;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferPlan;
use App\Contexts\GameWorld\KingdomTransfers\Queries\TransferSelfEligibilityQuery;
use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\v3\Support\ScenarioFactory;
use Tests\v3\TestCase;

final class TransferSelfEligibilityQueryV3Test extends TestCase
{
    use RefreshDatabase;

    private CarbonImmutable $now;

    protected function setUp(): void
    {
        parent::setUp();
        $this->now = CarbonImmutable::parse('2026-08-24T16:00:00Z');
        CarbonImmutable::setTestNow($this->now);
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_projection_evaluates_only_active_governors_participant_and_preserves_needs_verification(): void
    {
        $scenario = $this->outgoingScenario(64501, 64502);
        $factory = app(ScenarioFactory::class);
        $otherAccount = $factory->account();
        $other = $factory->player($otherAccount->userId, 64501, 'TRANSFER-OTHER');
        $otherRoster = $factory->roster($scenario['actor'], $scenario['alliance'], $other);
        app(SaveTransferParticipant::class)->handle(
            $scenario['alliance']->allianceId,
            $scenario['actor']->playerId,
            (string) $scenario['plan']->id,
            [
                'direction' => TransferDirection::Outgoing,
                'roster_entry_id' => $otherRoster->rosterEntryId,
                'destination_kingdom' => 64502,
            ],
        );
        $otherParticipant = TransferParticipant::query()
            ->where('transfer_plan_id', $scenario['plan']->id)
            ->where('player_id', $other->playerId)
            ->firstOrFail();

        $result = app(TransferSelfEligibilityQuery::class)->forPlayer(
            $scenario['actor']->playerId,
            $scenario['alliance']->allianceId,
        );

        self::assertNotNull($result);
        self::assertSame((string) $scenario['participant']->id, $result['participantId'] ?? null);
        self::assertSame(TransferEligibilityOutcome::NeedsVerification->value, $result['outcome'] ?? null);
        self::assertNotSame(TransferEligibilityOutcome::EligibleNow->value, $result['outcome'] ?? null);
        self::assertStringNotContainsString(
            (string) $otherParticipant->id,
            json_encode($result, JSON_THROW_ON_ERROR),
        );
    }

    public function test_requested_kingdom_number_only_constrains_existing_authorized_target(): void
    {
        $scenario = $this->outgoingScenario(64511, 64512);
        app(ScenarioFactory::class)->kingdom(64513);

        self::assertNotNull(app(TransferSelfEligibilityQuery::class)->forPlayer(
            $scenario['actor']->playerId,
            $scenario['alliance']->allianceId,
            64512,
        ));
        self::assertNull(app(TransferSelfEligibilityQuery::class)->forPlayer(
            $scenario['actor']->playerId,
            $scenario['alliance']->allianceId,
            64513,
        ));
    }

    public function test_foreign_governor_cannot_use_another_alliances_transfer_plan_as_scope(): void
    {
        $scenario = $this->outgoingScenario(64521, 64522);
        $foreign = $this->ownerScenario(64523);

        self::assertNull(app(TransferSelfEligibilityQuery::class)->forPlayer(
            $foreign['actor']->playerId,
            $scenario['alliance']->allianceId,
        ));
    }

    /**
     * @return array{actor:PlayerReference,alliance:AllianceReference,roster:RosterEntryReference}
     */
    private function ownerScenario(int $kingdomNumber): array
    {
        $factory = app(ScenarioFactory::class);
        $account = $factory->account();
        $actor = $factory->player($account->userId, $kingdomNumber, 'TRANSFER-'.$kingdomNumber);
        $alliance = $factory->alliance($actor);
        $roster = $factory->roster($actor, $alliance, $actor);

        return compact('actor', 'alliance', 'roster');
    }

    /**
     * @return array{
     *   actor:PlayerReference,
     *   alliance:AllianceReference,
     *   roster:RosterEntryReference,
     *   plan:TransferPlan,
     *   participant:TransferParticipant
     * }
     */
    private function outgoingScenario(int $homeNumber, int $targetNumber): array
    {
        $scenario = $this->ownerScenario($homeNumber);
        app(ScenarioFactory::class)->kingdom($targetNumber);
        $windowId = app(SaveTransferWindow::class)->handle(
            $scenario['alliance']->allianceId,
            $scenario['actor']->playerId,
            [
                'label' => 'Assistant current transfer',
                'pre_transfer_starts_at' => $this->now->subDays(3)->toIso8601String(),
                'invitational_starts_at' => $this->now->subDays(2)->toIso8601String(),
                'transfer_opens_at' => $this->now->subDay()->toIso8601String(),
                'ends_at' => $this->now->addDay()->toIso8601String(),
                'source_type' => TransferSourceType::OfficialPublication,
                'source_reference' => 'Century Games Kingdom Transfer publication',
                'observed_at' => $this->now->subDays(4)->toIso8601String(),
            ],
        );
        app(CreateTransferPlan::class)->handle(
            $scenario['alliance']->allianceId,
            $scenario['actor']->playerId,
            ['label' => 'Assistant transfer plan', 'transfer_window_id' => $windowId],
        );
        $plan = TransferPlan::query()
            ->where('alliance_id', $scenario['alliance']->allianceId)
            ->where('transfer_window_id', $windowId)
            ->firstOrFail();
        app(SaveTransferParticipant::class)->handle(
            $scenario['alliance']->allianceId,
            $scenario['actor']->playerId,
            (string) $plan->id,
            [
                'direction' => TransferDirection::Outgoing,
                'roster_entry_id' => $scenario['roster']->rosterEntryId,
                'destination_kingdom' => $targetNumber,
            ],
        );
        $participant = TransferParticipant::query()
            ->where('transfer_plan_id', $plan->id)
            ->where('player_id', $scenario['actor']->playerId)
            ->firstOrFail();

        return [
            ...$scenario,
            'plan' => $plan,
            'participant' => $participant,
        ];
    }
}
