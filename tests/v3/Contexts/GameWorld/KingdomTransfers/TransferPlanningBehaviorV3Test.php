<?php

declare(strict_types=1);

namespace Tests\v3\Contexts\GameWorld\KingdomTransfers;

use App\Contexts\Alliance\Lifecycle\ValueObjects\AllianceReference;
use App\Contexts\Alliance\Membership\ValueObjects\RosterEntryReference;
use App\Contexts\GameWorld\KingdomTransfers\Actions\CreateTransferBlocker;
use App\Contexts\GameWorld\KingdomTransfers\Actions\CreateTransferPlan;
use App\Contexts\GameWorld\KingdomTransfers\Actions\RecordTransferKingdomCondition;
use App\Contexts\GameWorld\KingdomTransfers\Actions\RecordTransferObservation;
use App\Contexts\GameWorld\KingdomTransfers\Actions\SaveTransferGroup;
use App\Contexts\GameWorld\KingdomTransfers\Actions\SaveTransferParticipant;
use App\Contexts\GameWorld\KingdomTransfers\Actions\SaveTransferWindow;
use App\Contexts\GameWorld\KingdomTransfers\Actions\TransitionTransferReadiness;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferDirection;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferEligibilityOutcome;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferKingdomClassification;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferObservationKind;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferReadinessState;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferRequirementState;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferSourceType;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferWindowPhase;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferBlocker;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferGroup;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferObservation;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferParticipant;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferPlan;
use App\Contexts\GameWorld\KingdomTransfers\Models\TransferWindow;
use App\Contexts\GameWorld\KingdomTransfers\Queries\TransferEligibilityQuery;
use App\Contexts\GameWorld\Players\ValueObjects\PlayerReference;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Tests\v3\Support\ScenarioFactory;
use Tests\v3\TestCase;

final class TransferPlanningBehaviorV3Test extends TestCase
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

    public function test_window_phase_boundaries_are_exact_and_utc_safe(): void
    {
        $window = new TransferWindow([
            'pre_transfer_starts_at' => '2026-08-20T00:00:00Z',
            'invitational_starts_at' => '2026-08-23T00:00:00Z',
            'transfer_opens_at' => '2026-08-25T00:00:00Z',
            'ends_at' => '2026-08-27T00:00:00Z',
        ]);

        self::assertSame(
            TransferWindowPhase::NotStarted,
            $window->phaseAt(CarbonImmutable::parse('2026-08-19T23:59:59Z')),
        );
        self::assertSame(
            TransferWindowPhase::PreTransfer,
            $window->phaseAt(CarbonImmutable::parse('2026-08-20T00:00:00Z')),
        );
        self::assertSame(
            TransferWindowPhase::InvitationalTransfer,
            $window->phaseAt(CarbonImmutable::parse('2026-08-23T00:00:00Z')),
        );
        self::assertSame(
            TransferWindowPhase::TransferOpens,
            $window->phaseAt(CarbonImmutable::parse('2026-08-25T00:00:00Z')),
        );
        self::assertSame(
            TransferWindowPhase::Closed,
            $window->phaseAt(CarbonImmutable::parse('2026-08-27T00:00:00Z')),
        );
    }

    public function test_official_groups_are_window_scoped_idempotent_and_revisioned(): void
    {
        $scenario = $this->ownerScenario(7301);
        $target = 7302;
        $other = 7303;
        app(ScenarioFactory::class)->kingdom($target);
        app(ScenarioFactory::class)->kingdom($other);

        $firstWindow = $this->saveWindow($scenario['alliance'], $scenario['actor'], 'August transfer', 0);
        $secondWindow = $this->saveWindow($scenario['alliance'], $scenario['actor'], 'September transfer', 10);

        $first = app(SaveTransferGroup::class)->handle(
            $scenario['alliance']->allianceId,
            $scenario['actor']->playerId,
            $firstWindow,
            $this->groupData('Group 4', [7301, $target]),
        );
        $repeat = app(SaveTransferGroup::class)->handle(
            $scenario['alliance']->allianceId,
            $scenario['actor']->playerId,
            $firstWindow,
            $this->groupData('Group 4', [7301, $target]),
        );
        $otherWindow = app(SaveTransferGroup::class)->handle(
            $scenario['alliance']->allianceId,
            $scenario['actor']->playerId,
            $secondWindow,
            $this->groupData('Group 9', [7301, $target]),
        );

        self::assertSame($first, $repeat);
        self::assertNotSame($first, $otherWindow);
        self::assertSame(2, TransferGroup::query()->count());

        $revised = app(SaveTransferGroup::class)->handle(
            $scenario['alliance']->allianceId,
            $scenario['actor']->playerId,
            $firstWindow,
            $this->groupData('Group 4', [7301, $target, $other]),
        );

        self::assertNotSame($first, $revised);
        self::assertSame(3, TransferGroup::query()->count());
        self::assertSame(1, TransferGroup::query()->whereKey($first)->whereNotNull('superseded_at')->count());
        self::assertSame(2, TransferGroup::query()->findOrFail($revised)->revision);
        self::assertSame(
            'Group 9',
            TransferGroup::query()->findOrFail($otherWindow)->official_label,
        );
    }

    public function test_observations_are_idempotent_append_only_and_require_provenance(): void
    {
        $scenario = $this->outgoingScenario(7311, 7312);
        $record = app(RecordTransferObservation::class);

        $first = $record->handle(
            $scenario['alliance']->allianceId,
            $scenario['actor']->playerId,
            (string) $scenario['plan']->id,
            (string) $scenario['participant']->id,
            TransferObservationKind::TransferScore,
            875,
            TransferSourceType::InGame,
            'KingShot Transfer Score screen',
            $this->now->subMinutes(20)->toIso8601String(),
            $this->now->subMinute()->toIso8601String(),
        );
        $repeat = $record->handle(
            $scenario['alliance']->allianceId,
            $scenario['actor']->playerId,
            (string) $scenario['plan']->id,
            (string) $scenario['participant']->id,
            TransferObservationKind::TransferScore,
            875,
            TransferSourceType::InGame,
            'KingShot Transfer Score screen',
            $this->now->subMinutes(20)->toIso8601String(),
            $this->now->subMinute()->toIso8601String(),
        );

        self::assertSame($first, $repeat);
        self::assertSame(1, TransferObservation::query()->count());

        $second = $record->handle(
            $scenario['alliance']->allianceId,
            $scenario['actor']->playerId,
            (string) $scenario['plan']->id,
            (string) $scenario['participant']->id,
            TransferObservationKind::TransferScore,
            910,
            TransferSourceType::InGame,
            'KingShot Transfer Score screen',
            $this->now->toIso8601String(),
            $this->now->addHour()->toIso8601String(),
        );

        self::assertNotSame($first, $second);
        self::assertSame(2, TransferObservation::query()->count());

        $eligibility = app(TransferEligibilityQuery::class)->forPlan(
            $scenario['alliance']->allianceId,
            $scenario['plan'],
            collect([$scenario['participant']]),
        );
        self::assertSame(
            TransferRequirementState::Met,
            $eligibility[(string) $scenario['participant']->id]['transferScore']->state,
        );
        self::assertSame(910, $eligibility[(string) $scenario['participant']->id]['transferScore']->value);

        $this->expectException(ValidationException::class);
        $record->handle(
            $scenario['alliance']->allianceId,
            $scenario['actor']->playerId,
            (string) $scenario['plan']->id,
            (string) $scenario['participant']->id,
            TransferObservationKind::GovernorPower,
            100_000_000,
            TransferSourceType::InGame,
            '   ',
            $this->now->toIso8601String(),
            $this->now->addHour()->toIso8601String(),
        );
    }

    public function test_equal_power_cap_is_eligible_and_readiness_blockers_remain_independent(): void
    {
        $scenario = $this->outgoingScenario(7321, 7322);
        $this->recordEligibleFacts($scenario, 125_000_000, 125_000_000);

        $before = app(TransferEligibilityQuery::class)->forPlan(
            $scenario['alliance']->allianceId,
            $scenario['plan'],
            collect([$scenario['participant']]),
        );
        self::assertSame(
            TransferEligibilityOutcome::EligibleNow,
            $before[(string) $scenario['participant']->id]['assessment']->outcome,
        );

        app(CreateTransferBlocker::class)->handle(
            $scenario['alliance']->allianceId,
            $scenario['actor']->playerId,
            (string) $scenario['plan']->id,
            (string) $scenario['participant']->id,
            'Coordinate departure timing',
            'Alliance planning work remains even though game eligibility is satisfied.',
        );
        app(TransitionTransferReadiness::class)->handle(
            $scenario['alliance']->allianceId,
            $scenario['actor']->playerId,
            (string) $scenario['plan']->id,
            (string) $scenario['participant']->id,
            TransferReadinessState::Blocked,
        );

        $participant = TransferParticipant::query()->findOrFail($scenario['participant']->id);
        $after = app(TransferEligibilityQuery::class)->forPlan(
            $scenario['alliance']->allianceId,
            $scenario['plan'],
            collect([$participant]),
        );

        self::assertSame(TransferReadinessState::Blocked, $participant->readiness_state);
        self::assertSame(1, TransferBlocker::query()->where('transfer_participant_id', $participant->id)->count());
        self::assertSame(
            TransferEligibilityOutcome::EligibleNow,
            $after[(string) $participant->id]['assessment']->outcome,
        );
    }

    public function test_stale_power_forces_verification_while_stale_score_is_reported_separately(): void
    {
        $scenario = $this->outgoingScenario(7331, 7332);
        $this->recordEligibleFacts($scenario, 118_000_000, 125_000_000, stalePower: true);
        app(RecordTransferObservation::class)->handle(
            $scenario['alliance']->allianceId,
            $scenario['actor']->playerId,
            (string) $scenario['plan']->id,
            (string) $scenario['participant']->id,
            TransferObservationKind::TransferScore,
            900,
            TransferSourceType::InGame,
            'KingShot Transfer Score screen',
            $this->now->subHours(2)->toIso8601String(),
            $this->now->subHour()->toIso8601String(),
        );

        $result = app(TransferEligibilityQuery::class)->forPlan(
            $scenario['alliance']->allianceId,
            $scenario['plan'],
            collect([$scenario['participant']]),
        );
        $row = $result[(string) $scenario['participant']->id];

        self::assertSame(TransferEligibilityOutcome::NeedsVerification, $row['assessment']->outcome);
        self::assertSame(TransferRequirementState::Stale, $row['transferScore']->state);
    }

    public function test_cross_alliance_actor_cannot_write_transfer_facts(): void
    {
        $owner = $this->ownerScenario(7341);
        $foreign = $this->ownerScenario(7342);

        $this->expectException(AuthorizationException::class);
        app(SaveTransferWindow::class)->handle(
            $owner['alliance']->allianceId,
            $foreign['actor']->playerId,
            $this->windowData('Unauthorized window', 0),
        );
    }

    /**
     * @return array{actor:PlayerReference,alliance:AllianceReference,roster:RosterEntryReference}
     */
    private function ownerScenario(int $kingdomNumber): array
    {
        $scenarios = app(ScenarioFactory::class);
        $account = $scenarios->account();
        $actor = $scenarios->player($account->userId, $kingdomNumber, 'TRANSFER-'.$kingdomNumber);
        $alliance = $scenarios->alliance($actor);
        $roster = $scenarios->roster($actor, $alliance, $actor);

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
        $windowId = $this->saveWindow(
            $scenario['alliance'],
            $scenario['actor'],
            'Current transfer',
            0,
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
    private function recordEligibleFacts(
        array $scenario,
        int $power,
        int $cap,
        bool $stalePower = false,
    ): void {
        app(SaveTransferGroup::class)->handle(
            $scenario['alliance']->allianceId,
            $scenario['actor']->playerId,
            $scenario['windowId'],
            $this->groupData(
                'Group 4',
                [$scenario['homeNumber'], $scenario['targetNumber']],
            ),
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
        $record->handle(
            $scenario['alliance']->allianceId,
            $scenario['actor']->playerId,
            (string) $scenario['plan']->id,
            (string) $scenario['participant']->id,
            TransferObservationKind::GovernorPower,
            $power,
            TransferSourceType::InGame,
            'KingShot Governor transfer screen',
            $this->now->subMinutes(10)->toIso8601String(),
            ($stalePower ? $this->now->subMinute() : $this->now->addHours(2))->toIso8601String(),
        );
        $record->handle(
            $scenario['alliance']->allianceId,
            $scenario['actor']->playerId,
            (string) $scenario['plan']->id,
            (string) $scenario['participant']->id,
            TransferObservationKind::TransferPassesAvailable,
            9,
            TransferSourceType::InGame,
            'KingShot Transfer Pass inventory',
            $this->now->subMinutes(10)->toIso8601String(),
            $this->now->addHours(2)->toIso8601String(),
        );
        $record->handle(
            $scenario['alliance']->allianceId,
            $scenario['actor']->playerId,
            (string) $scenario['plan']->id,
            (string) $scenario['participant']->id,
            TransferObservationKind::TransferPassesRequired,
            9,
            TransferSourceType::InGame,
            'KingShot target transfer requirements',
            $this->now->subMinutes(10)->toIso8601String(),
            $this->now->addHours(2)->toIso8601String(),
        );
        $record->handle(
            $scenario['alliance']->allianceId,
            $scenario['actor']->playerId,
            (string) $scenario['plan']->id,
            (string) $scenario['participant']->id,
            TransferObservationKind::InGameRulesVerified,
            true,
            TransferSourceType::InGame,
            'KingShot transfer eligibility screen',
            $this->now->subMinutes(10)->toIso8601String(),
            $this->now->addHours(2)->toIso8601String(),
        );
    }

    private function saveWindow(
        AllianceReference $alliance,
        PlayerReference $actor,
        string $label,
        int $offsetDays,
    ): string {
        return app(SaveTransferWindow::class)->handle(
            $alliance->allianceId,
            $actor->playerId,
            $this->windowData($label, $offsetDays),
        );
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
    private function windowData(string $label, int $offsetDays): array
    {
        $anchor = $this->now->addDays($offsetDays);

        return [
            'label' => $label,
            'pre_transfer_starts_at' => $anchor->subDays(3)->toIso8601String(),
            'invitational_starts_at' => $anchor->subDays(2)->toIso8601String(),
            'transfer_opens_at' => $anchor->subDay()->toIso8601String(),
            'ends_at' => $anchor->addDay()->toIso8601String(),
            'source_type' => TransferSourceType::OfficialPublication,
            'source_reference' => 'Century Games Kingdom Transfer publication',
            'observed_at' => $anchor->subDays(4)->toIso8601String(),
        ];
    }

    /** @return array{official_label:string,kingdom_numbers:list<int>,source_type:TransferSourceType,source_reference:string,observed_at:string} */
    private function groupData(string $label, array $kingdomNumbers): array
    {
        return [
            'official_label' => $label,
            'kingdom_numbers' => array_values($kingdomNumbers),
            'source_type' => TransferSourceType::InGame,
            'source_reference' => 'KingShot official Transfer Group screen',
            'observed_at' => $this->now->subMinutes(30)->toIso8601String(),
        ];
    }
}
