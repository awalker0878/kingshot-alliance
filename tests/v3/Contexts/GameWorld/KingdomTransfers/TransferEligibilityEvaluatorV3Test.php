<?php

declare(strict_types=1);

namespace Tests\v3\Contexts\GameWorld\KingdomTransfers;

use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferEligibilityOutcome;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferInvitationStatus;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferKingdomClassification;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferRequirementState;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferSourceType;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferWindowPhase;
use App\Contexts\GameWorld\KingdomTransfers\Services\TransferEligibilityEvaluator;
use App\Contexts\GameWorld\KingdomTransfers\ValueObjects\TransferEligibilityInput;
use App\Contexts\GameWorld\KingdomTransfers\ValueObjects\TransferObservedValue;
use Carbon\CarbonImmutable;
use Tests\v3\TestCase;

final class TransferEligibilityEvaluatorV3Test extends TestCase
{
    private CarbonImmutable $now;

    protected function setUp(): void
    {
        parent::setUp();
        $this->now = CarbonImmutable::parse('2026-08-23T12:00:00Z');
    }

    public function test_phase_i_and_closed_windows_never_report_eligible(): void
    {
        $evaluator = app(TransferEligibilityEvaluator::class);

        self::assertSame(
            TransferEligibilityOutcome::NotOpenYet,
            $evaluator->evaluate($this->eligibleInput(TransferWindowPhase::PreTransfer), $this->now)->outcome,
        );
        self::assertSame(
            TransferEligibilityOutcome::WindowClosed,
            $evaluator->evaluate($this->eligibleInput(TransferWindowPhase::Closed), $this->now)->outcome,
        );
    }

    public function test_phase_iii_under_cap_governor_is_eligible_without_invitation_when_all_evidence_is_current(): void
    {
        $assessment = app(TransferEligibilityEvaluator::class)->evaluate(
            $this->eligibleInput(TransferWindowPhase::TransferOpens),
            $this->now,
        );

        self::assertSame(TransferEligibilityOutcome::EligibleNow, $assessment->outcome);
        self::assertNull($assessment->primaryAction);
        self::assertContains(
            TransferRequirementState::NotApplicable,
            array_map(static fn ($requirement): TransferRequirementState => $requirement->state, $assessment->requirements),
        );
    }

    public function test_phase_ii_requires_the_correct_invitation_type(): void
    {
        $evaluator = app(TransferEligibilityEvaluator::class);
        $missingInvite = $this->eligibleInput(TransferWindowPhase::InvitationalTransfer, invitation: $this->current(TransferInvitationStatus::None->value));
        $ordinaryInvite = $this->eligibleInput(TransferWindowPhase::InvitationalTransfer, invitation: $this->current(TransferInvitationStatus::OrdinaryReceived->value));

        self::assertSame(TransferEligibilityOutcome::EligibleWithAction, $evaluator->evaluate($missingInvite, $this->now)->outcome);
        self::assertSame(TransferEligibilityOutcome::EligibleNow, $evaluator->evaluate($ordinaryInvite, $this->now)->outcome);
    }

    public function test_over_cap_governor_requires_special_invite_and_leading_target_is_a_hard_blocker(): void
    {
        $evaluator = app(TransferEligibilityEvaluator::class);
        $ordinary = $this->eligibleInput(
            TransferWindowPhase::TransferOpens,
            power: $this->current(130_000_000),
            invitation: $this->current(TransferInvitationStatus::SpecialApproved->value),
        );
        $leading = $this->eligibleInput(
            TransferWindowPhase::TransferOpens,
            classification: TransferKingdomClassification::Leading,
            power: $this->current(130_000_000),
            invitation: $this->current(TransferInvitationStatus::SpecialApproved->value),
        );

        self::assertSame(TransferEligibilityOutcome::EligibleNow, $evaluator->evaluate($ordinary, $this->now)->outcome);
        self::assertSame(TransferEligibilityOutcome::Blocked, $evaluator->evaluate($leading, $this->now)->outcome);
    }

    public function test_group_mismatch_is_a_hard_blocker(): void
    {
        $input = $this->eligibleInput(TransferWindowPhase::TransferOpens);
        $input = new TransferEligibilityInput(
            $input->phase,
            TransferRequirementState::Met,
            'Group 3',
            'Group 4',
            $input->targetPowerCap,
            $input->targetClassification,
            $input->governorPower,
            $input->invitationStatus,
            $input->passesAvailable,
            $input->passesRequired,
            $input->inGameRulesVerified,
        );

        self::assertSame(
            TransferEligibilityOutcome::Blocked,
            app(TransferEligibilityEvaluator::class)->evaluate($input, $this->now)->outcome,
        );
    }

    public function test_missing_stale_or_conflicting_evidence_yields_needs_verification(): void
    {
        $evaluator = app(TransferEligibilityEvaluator::class);

        foreach ([TransferRequirementState::Unknown, TransferRequirementState::Stale, TransferRequirementState::Conflicting] as $state) {
            $input = $this->eligibleInput(
                TransferWindowPhase::TransferOpens,
                passesAvailable: new TransferObservedValue($state, 9, TransferSourceType::InGame, 'transfer screen', $this->now->subHour(), $this->now->subMinute()),
            );

            self::assertSame(TransferEligibilityOutcome::NeedsVerification, $evaluator->evaluate($input, $this->now)->outcome);
        }
    }

    public function test_insufficient_passes_returns_an_actionable_shortfall(): void
    {
        $input = $this->eligibleInput(
            TransferWindowPhase::TransferOpens,
            passesAvailable: $this->current(7),
            passesRequired: $this->current(9),
        );
        $assessment = app(TransferEligibilityEvaluator::class)->evaluate($input, $this->now);

        self::assertSame(TransferEligibilityOutcome::EligibleWithAction, $assessment->outcome);
        self::assertSame('Acquire 2 more Transfer Pass(es).', $assessment->primaryAction);
    }

    public function test_false_in_game_verification_is_a_hard_blocker_and_missing_verification_never_silently_passes(): void
    {
        $evaluator = app(TransferEligibilityEvaluator::class);
        $blocked = $this->eligibleInput(
            TransferWindowPhase::TransferOpens,
            inGameRules: $this->current(false, 'Governor is still in an Alliance.'),
        );
        $unknown = $this->eligibleInput(
            TransferWindowPhase::TransferOpens,
            inGameRules: TransferObservedValue::unknown(),
        );

        self::assertSame(TransferEligibilityOutcome::Blocked, $evaluator->evaluate($blocked, $this->now)->outcome);
        self::assertSame(TransferEligibilityOutcome::NeedsVerification, $evaluator->evaluate($unknown, $this->now)->outcome);
    }

    private function eligibleInput(
        TransferWindowPhase $phase,
        TransferKingdomClassification $classification = TransferKingdomClassification::Ordinary,
        ?TransferObservedValue $power = null,
        ?TransferObservedValue $invitation = null,
        ?TransferObservedValue $passesAvailable = null,
        ?TransferObservedValue $passesRequired = null,
        ?TransferObservedValue $inGameRules = null,
    ): TransferEligibilityInput {
        return new TransferEligibilityInput(
            $phase,
            TransferRequirementState::Met,
            'Group 4',
            'Group 4',
            $this->current(125_000_000),
            $classification,
            $power ?? $this->current(118_400_000),
            $invitation ?? $this->current(TransferInvitationStatus::None->value),
            $passesAvailable ?? $this->current(9),
            $passesRequired ?? $this->current(9),
            $inGameRules ?? $this->current(true),
        );
    }

    private function current(int|string|bool $value, ?string $details = null): TransferObservedValue
    {
        return new TransferObservedValue(
            TransferRequirementState::Met,
            $value,
            TransferSourceType::InGame,
            'KingShot transfer screen',
            $this->now->subMinutes(10),
            $this->now->addHours(2),
            $details,
        );
    }
}
