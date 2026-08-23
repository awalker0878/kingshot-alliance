<?php

declare(strict_types=1);

namespace Tests\v3\Contexts\GameWorld\KingdomTransfers;

use App\Contexts\Alliance\Lifecycle\ValueObjects\AllianceReference;
use App\Contexts\Alliance\Membership\ValueObjects\RosterEntryReference;
use App\Contexts\GameWorld\KingdomTransfers\Actions\CreateTransferPlan;
use App\Contexts\GameWorld\KingdomTransfers\Actions\RecordTransferKingdomCondition;
use App\Contexts\GameWorld\KingdomTransfers\Actions\RecordTransferObservation;
use App\Contexts\GameWorld\KingdomTransfers\Actions\SaveTransferGroup;
use App\Contexts\GameWorld\KingdomTransfers\Actions\SaveTransferParticipant;
use App\Contexts\GameWorld\KingdomTransfers\Actions\SaveTransferWindow;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferDirection;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferEligibilityOutcome;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferInvitationStatus;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferKingdomClassification;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferObservationKind;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferReadinessState;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferRequirementState;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferSourceType;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferParticipant;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferPlan;
use App\Contexts\GameWorld\KingdomTransfers\Queries\TransferEligibilityQuery;
use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\v3\Support\ScenarioFactory;
use Tests\v3\TestCase;

final class TransferPlanningCompletenessV3Test extends TestCase
{
    use RefreshDatabase;

    private CarbonImmutable $now;

    protected function setUp(): void
    {
        parent::setUp();
        $this->now = CarbonImmutable::parse('2026-08-23T16:00:00Z');
        CarbonImmutable::setTestNow($this->now);
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        parent::tearDown();
    }

    public function test_overlapping_authoritative_observations_with_different_values_require_verification(): void
    {
        $scenario = $this->outgoingScenario(7351, 7352);
        $this->recordEligibleFacts($scenario, 118_000_000, 125_000_000);

        app(RecordTransferObservation::class)->handle(
            $scenario['alliance']->allianceId,
            $scenario['actor']->playerId,
            (string) $scenario['plan']->id,
            (string) $scenario['participant']->id,
            TransferObservationKind::GovernorPower,
            121_000_000,
            TransferSourceType::Evidence,
            'Reviewed Governor profile screenshot',
            $this->now->subMinutes(2)->toIso8601String(),
            $this->now->addHours(2)->toIso8601String(),
            evidenceId: 'evidence-conflicting-power',
        );

        $row = $this->eligibility($scenario);
        $power = collect($row['assessment']->requirements)
            ->firstWhere('key.value', 'power_cap');

        self::assertSame(TransferEligibilityOutcome::NeedsVerification, $row['assessment']->outcome);
        self::assertNotNull($power);
        self::assertSame(TransferRequirementState::Conflicting, $power->state);
        self::assertSame(
            TransferRequirementState::Conflicting,
            app(\App\Contexts\GameWorld\KingdomTransfers\Services\TransferObservationSelector::class)
                ->select(
                    $row['observations'],
                    TransferObservationKind::GovernorPower,
                    null,
                    $this->now,
                )->state,
        );
    }

    public function test_authoritative_source_correction_re_evaluates_eligibility_deterministically(): void
    {
        $scenario = $this->outgoingScenario(7361, 7362);
        $this->recordEligibleFacts($scenario, 118_000_000, 125_000_000);

        self::assertSame(TransferEligibilityOutcome::EligibleNow, $this->eligibility($scenario)['assessment']->outcome);

        app(RecordTransferKingdomCondition::class)->handle(
            $scenario['alliance']->allianceId,
            $scenario['actor']->playerId,
            $scenario['windowId'],
            $scenario['targetNumber'],
            110_000_000,
            TransferKingdomClassification::Ordinary,
            TransferSourceType::InGame,
            'KingShot corrected target Kingdom transfer screen',
            $this->now->addMinute()->toIso8601String(),
            true,
        );

        $afterCorrection = $this->eligibility($scenario);
        self::assertSame(TransferEligibilityOutcome::NeedsVerification, $afterCorrection['assessment']->outcome);
        self::assertSame(110_000_000, $afterCorrection['targetCondition']?->power_cap);

        app(RecordTransferObservation::class)->handle(
            $scenario['alliance']->allianceId,
            $scenario['actor']->playerId,
            (string) $scenario['plan']->id,
            (string) $scenario['participant']->id,
            TransferObservationKind::InvitationStatus,
            TransferInvitationStatus::SpecialApproved->value,
            TransferSourceType::InGame,
            'KingShot Special Invite approval',
            $this->now->addMinutes(2)->toIso8601String(),
            $this->now->addHours(2)->toIso8601String(),
        );

        self::assertSame(TransferEligibilityOutcome::EligibleNow, $this->eligibility($scenario)['assessment']->outcome);
    }

    public function test_eligibility_query_count_does_not_grow_with_participant_count(): void
    {
        $scenario = $this->outgoingScenario(7371, 7372);
        $plan = $scenario['plan']->load('window');
        $participants = collect([$scenario['participant']]);

        $factory = app(ScenarioFactory::class);
        for ($index = 2; $index <= 60; $index++) {
            $player = $factory->unclaimedPlayer(7371, 'TRANSFER-BUDGET-'.$index);
            $participants->push(TransferParticipant::query()->create([
                'alliance_id' => $scenario['alliance']->allianceId,
                'transfer_plan_id' => $plan->id,
                'direction' => TransferDirection::Outgoing,
                'readiness_state' => TransferReadinessState::NotStarted,
                'player_id' => $player->playerId,
                'observed_name' => 'Budget Governor '.$index,
                'game_player_id' => $player->gamePlayerId,
                'source_kingdom_id' => $scenario['participant']->source_kingdom_id,
                'destination_kingdom_id' => $scenario['participant']->destination_kingdom_id,
            ]));
        }

        DB::enableQueryLog();
        DB::flushQueryLog();
        app(TransferEligibilityQuery::class)->forPlan(
            $scenario['alliance']->allianceId,
            $plan,
            collect([$scenario['participant']]),
        );
        $smallCount = count(DB::getQueryLog());

        DB::flushQueryLog();
        $result = app(TransferEligibilityQuery::class)->forPlan(
            $scenario['alliance']->allianceId,
            $plan,
            $participants,
        );
        $largeCount = count(DB::getQueryLog());
        DB::disableQueryLog();

        self::assertCount(60, $result);
        self::assertLessThanOrEqual(
            $smallCount + 1,
            $largeCount,
            'Transfer eligibility must batch plan facts instead of adding per-Governor queries.',
        );
    }

    /**
     * @return array{actor:PlayerReference,alliance:AllianceReference,roster:RosterEntryReference}
     */
    private function ownerScenario(int $kingdomNumber): array
    {
        $factory = app(ScenarioFactory::class);
        $account = $factory->account();
        $actor = $factory->player($account->userId, $kingdomNumber, 'TRANSFER-COMPLETE-'.$kingdomNumber);
        $alliance = $factory->alliance($actor);
        $roster = $factory->roster($actor, $alliance, $actor);

        return compact('actor', 'alliance', 'roster');
    }

    /**
     * @return array{
     *     actor:PlayerReference,
     *     alliance:AllianceReference,
     *     roster:RosterEntryReference,
     *     plan:TransferPlan,
     *     participant:TransferParticipant,
     *     windowId:string,
     *     homeNumber:int,
     *     targetNumber:int
     * }
     */
    private function outgoingScenario(int $homeNumber, int $targetNumber): array
    {
        $scenario = $this->ownerScenario($homeNumber);
        app(ScenarioFactory::class)->kingdom($targetNumber);
        $windowId = app(SaveTransferWindow::class)->handle(
            $scenario['alliance']->allianceId,
            $scenario['actor']->playerId,
            $this->windowData('Current transfer'),
        );

        app(CreateTransferPlan::class)->handle(
            $scenario['alliance']->allianceId,
            $scenario['actor']->playerId,
            ['label' => 'Current transfer plan', 'transfer_window_id' => $windowId],
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
            ->firstOrFail();

        return [
            ...$scenario,
            'plan' => $plan,
            'participant' => $participant,
            'windowId' => $windowId,
            'homeNumber' => $homeNumber,
            'targetNumber' => $targetNumber,
        ];
    }

    /**
     * @param array{
     *     actor:PlayerReference,
     *     alliance:AllianceReference,
     *     plan:TransferPlan,
     *     participant:TransferParticipant,
     *     windowId:string,
     *     homeNumber:int,
     *     targetNumber:int
     * } $scenario
     */
    private function recordEligibleFacts(array $scenario, int $power, int $cap): void
    {
        app(SaveTransferGroup::class)->handle(
            $scenario['alliance']->allianceId,
            $scenario['actor']->playerId,
            $scenario['windowId'],
            [
                'official_label' => 'Group 4',
                'kingdom_numbers' => [$scenario['homeNumber'], $scenario['targetNumber']],
                'source_type' => TransferSourceType::InGame,
                'source_reference' => 'KingShot official Transfer Group screen',
                'observed_at' => $this->now->subMinutes(30)->toIso8601String(),
            ],
        );
        app(RecordTransferKingdomCondition::class)->handle(
            $scenario['alliance']->allianceId,
            $scenario['actor']->playerId,
            $scenario['windowId'],
            $scenario['targetNumber'],
            $cap,
            TransferKingdomClassification::Ordinary,
            TransferSourceType::InGame,
            'KingShot target Kingdom transfer screen',
            $this->now->subMinutes(15)->toIso8601String(),
        );

        $record = app(RecordTransferObservation::class);
        foreach ([
            [TransferObservationKind::GovernorPower, $power, 'KingShot Governor transfer screen', null],
            [TransferObservationKind::TransferPassesAvailable, 9, 'KingShot Transfer Pass inventory', null],
            [TransferObservationKind::TransferPassesRequired, 9, 'KingShot target transfer requirements', null],
            [TransferObservationKind::InGameRulesVerified, true, 'KingShot transfer eligibility screen', null],
        ] as [$kind, $value, $reference, $details]) {
            $record->handle(
                $scenario['alliance']->allianceId,
                $scenario['actor']->playerId,
                (string) $scenario['plan']->id,
                (string) $scenario['participant']->id,
                $kind,
                $value,
                TransferSourceType::InGame,
                $reference,
                $this->now->subMinutes(10)->toIso8601String(),
                $this->now->addHours(2)->toIso8601String(),
                $details,
            );
        }
    }

    /**
     * @param array{alliance:AllianceReference,plan:TransferPlan,participant:TransferParticipant} $scenario
     * @return array{assessment:mixed,transferScore:mixed,observations:mixed,officialGroup:?string,targetCondition:mixed}
     */
    private function eligibility(array $scenario): array
    {
        return app(TransferEligibilityQuery::class)->forPlan(
            $scenario['alliance']->allianceId,
            $scenario['plan'],
            collect([$scenario['participant']]),
        )[(string) $scenario['participant']->id];
    }

    /**
     * @return array{
     *     label:string,
     *     pre_transfer_starts_at:string,
     *     invitational_starts_at:string,
     *     transfer_opens_at:string,
     *     ends_at:string,
     *     source_type:TransferSourceType,
     *     source_reference:string,
     *     observed_at:string
     * }
     */
    private function windowData(string $label): array
    {
        return [
            'label' => $label,
            'pre_transfer_starts_at' => $this->now->subDays(3)->toIso8601String(),
            'invitational_starts_at' => $this->now->subDays(2)->toIso8601String(),
            'transfer_opens_at' => $this->now->subDay()->toIso8601String(),
            'ends_at' => $this->now->addDay()->toIso8601String(),
            'source_type' => TransferSourceType::OfficialPublication,
            'source_reference' => 'Century Games Kingdom Transfer publication',
            'observed_at' => $this->now->subDays(4)->toIso8601String(),
        ];
    }
}
