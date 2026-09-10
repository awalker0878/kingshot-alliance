<?php

declare(strict_types=1);

namespace Tests\Contexts\GameWorld\KingdomTransfers\Unit;

use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferEligibilityOutcome;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferInvitationStatus;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferKingdomClassification;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferRequirementKey;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferRequirementState;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferSourceType;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferWindowPhase;
use App\Contexts\GameWorld\KingdomTransfers\Services\TransferEligibilityEvaluator;
use App\Contexts\GameWorld\KingdomTransfers\ValueObjects\TransferEligibilityInput;
use App\Contexts\GameWorld\KingdomTransfers\ValueObjects\TransferObservedValue;
use Carbon\CarbonImmutable;
use PHPUnit\Framework\TestCase;

final class TransferEligibilityEvaluatorV3Test extends TestCase
{
    private CarbonImmutable $now;

    protected function setUp(): void
    {
        parent::setUp();
        $this->now = CarbonImmutable::parse('2026-09-07T12:00:00Z');
    }

    public function test_phase_i_and_closed_windows_never_report_eligible(): void
    {
        $evaluator = (new TransferEligibilityEvaluator);

        self::assertSame(TransferEligibilityOutcome::NotOpenYet, $evaluator->evaluate($this->eligibleInput(TransferWindowPhase::PreTransfer), $this->now)->outcome);
        self::assertSame(TransferEligibilityOutcome::WindowClosed, $evaluator->evaluate($this->eligibleInput(TransferWindowPhase::Closed), $this->now)->outcome);
    }

    public function test_phase_iii_under_cap_governor_is_eligible_without_invitation_when_all_rules_are_current(): void
    {
        $assessment = (new TransferEligibilityEvaluator)->evaluate($this->eligibleInput(TransferWindowPhase::TransferOpens), $this->now);

        self::assertSame(TransferEligibilityOutcome::EligibleNow, $assessment->outcome);
        self::assertNull($assessment->primaryAction);
        self::assertContains(TransferRequirementState::NotApplicable, array_map(static fn ($requirement): TransferRequirementState => $requirement->state, $assessment->requirements));
    }

    public function test_phase_ii_requires_the_correct_invitation_type(): void
    {
        $evaluator = (new TransferEligibilityEvaluator);
        $missingInvite = $this->eligibleInput(TransferWindowPhase::InvitationalTransfer, invitation: $this->current(TransferInvitationStatus::None->value));
        $ordinaryInvite = $this->eligibleInput(TransferWindowPhase::InvitationalTransfer, invitation: $this->current(TransferInvitationStatus::OrdinaryReceived->value));

        self::assertSame(TransferEligibilityOutcome::EligibleWithAction, $evaluator->evaluate($missingInvite, $this->now)->outcome);
        self::assertSame(TransferEligibilityOutcome::EligibleNow, $evaluator->evaluate($ordinaryInvite, $this->now)->outcome);
    }

    public function test_over_cap_governor_requires_special_invite_and_leading_target_is_a_hard_blocker(): void
    {
        $evaluator = (new TransferEligibilityEvaluator);
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

    public function test_group_generation_and_truegold_mismatches_are_hard_blockers(): void
    {
        $evaluator = (new TransferEligibilityEvaluator);

        self::assertSame(
            TransferEligibilityOutcome::Blocked,
            $evaluator->evaluate($this->eligibleInput(TransferWindowPhase::TransferOpens, targetGroupLabel: 'Group 5'), $this->now)->outcome,
        );
        self::assertSame(
            TransferEligibilityOutcome::Blocked,
            $evaluator->evaluate($this->eligibleInput(TransferWindowPhase::TransferOpens, governorHeroGeneration: $this->current(4)), $this->now)->outcome,
        );
        self::assertSame(
            TransferEligibilityOutcome::Blocked,
            $evaluator->evaluate($this->eligibleInput(TransferWindowPhase::TransferOpens, governorTruegoldLevel: $this->current(2)), $this->now)->outcome,
        );
    }

    public function test_character_age_threshold_is_inclusive_and_exceeding_it_blocks(): void
    {
        $evaluator = (new TransferEligibilityEvaluator);
        $atBoundary = $this->eligibleInput(TransferWindowPhase::TransferOpens, characterAgeOverTargetDays: $this->current(120));
        $overBoundary = $this->eligibleInput(TransferWindowPhase::TransferOpens, characterAgeOverTargetDays: $this->current(121));

        self::assertSame(TransferEligibilityOutcome::EligibleNow, $evaluator->evaluate($atBoundary, $this->now)->outcome);
        self::assertSame(TransferEligibilityOutcome::Blocked, $evaluator->evaluate($overBoundary, $this->now)->outcome);
    }

    public function test_transfer_cooldown_is_actionable_until_zero_days_remain(): void
    {
        $evaluator = (new TransferEligibilityEvaluator);
        $coolingDown = $this->eligibleInput(TransferWindowPhase::TransferOpens, cooldownRemaining: $this->current(1));
        $ready = $this->eligibleInput(TransferWindowPhase::TransferOpens, cooldownRemaining: $this->current(0));

        $assessment = $evaluator->evaluate($coolingDown, $this->now);
        self::assertSame(TransferEligibilityOutcome::EligibleWithAction, $assessment->outcome);
        self::assertSame('Wait 1 more day(s) for the transfer cooldown to expire.', $assessment->primaryAction);
        self::assertSame(TransferEligibilityOutcome::EligibleNow, $evaluator->evaluate($ready, $this->now)->outcome);
    }

    public function test_four_existing_characters_in_target_is_a_hard_blocker(): void
    {
        $evaluator = (new TransferEligibilityEvaluator);

        self::assertSame(
            TransferEligibilityOutcome::EligibleNow,
            $evaluator->evaluate($this->eligibleInput(TransferWindowPhase::TransferOpens, targetCharacterCount: $this->current(3)), $this->now)->outcome,
        );
        self::assertSame(
            TransferEligibilityOutcome::Blocked,
            $evaluator->evaluate($this->eligibleInput(TransferWindowPhase::TransferOpens, targetCharacterCount: $this->current(4)), $this->now)->outcome,
        );
    }

    public function test_exhausted_target_or_phase_capacity_never_reports_eligible_now(): void
    {
        $evaluator = (new TransferEligibilityEvaluator);
        $totalFull = $this->eligibleInput(TransferWindowPhase::TransferOpens, targetCapacity: $this->current(0));
        $openFull = $this->eligibleInput(TransferWindowPhase::TransferOpens, transferOpenCapacity: $this->current(0));

        self::assertSame(TransferEligibilityOutcome::EligibleWithAction, $evaluator->evaluate($totalFull, $this->now)->outcome);
        self::assertSame(TransferEligibilityOutcome::EligibleWithAction, $evaluator->evaluate($openFull, $this->now)->outcome);
    }

    public function test_resource_loss_preflight_is_actionable_but_not_a_hard_game_blocker(): void
    {
        $assessment = (new TransferEligibilityEvaluator)->evaluate(
            $this->eligibleInput(TransferWindowPhase::TransferOpens, resourceProtection: $this->current(false)),
            $this->now,
        );

        self::assertSame(TransferEligibilityOutcome::EligibleWithAction, $assessment->outcome);
        $resource = collect($assessment->requirements)->first(static fn ($row): bool => $row->key === TransferRequirementKey::ResourceProtection);
        self::assertSame(TransferRequirementState::Unmet, $resource?->state);
        self::assertSame('Reduce or protect excess resources before transferring to avoid resource loss.', $resource?->nextAction);
    }

    public function test_missing_stale_or_conflicting_evidence_yields_needs_verification(): void
    {
        $evaluator = (new TransferEligibilityEvaluator);

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
        $assessment = (new TransferEligibilityEvaluator)->evaluate($input, $this->now);

        self::assertSame(TransferEligibilityOutcome::EligibleWithAction, $assessment->outcome);
        self::assertSame('Acquire 2 more Transfer Pass(es).', $assessment->primaryAction);
    }

    public function test_required_pass_count_outside_official_range_is_conflicting(): void
    {
        $assessment = (new TransferEligibilityEvaluator)->evaluate(
            $this->eligibleInput(TransferWindowPhase::TransferOpens, passesRequired: $this->current(51), passesAvailable: $this->current(51)),
            $this->now,
        );

        self::assertSame(TransferEligibilityOutcome::NeedsVerification, $assessment->outcome);
        $passes = collect($assessment->requirements)->first(static fn ($row): bool => $row->key === TransferRequirementKey::TransferPasses);
        self::assertSame(TransferRequirementState::Conflicting, $passes?->state);
    }

    public function test_false_in_game_verification_is_a_hard_blocker_and_missing_verification_never_silently_passes(): void
    {
        $evaluator = (new TransferEligibilityEvaluator);
        $blocked = $this->eligibleInput(TransferWindowPhase::TransferOpens, inGameRules: $this->current(false, 'Governor is still in an Alliance.'));
        $unknown = $this->eligibleInput(TransferWindowPhase::TransferOpens, inGameRules: TransferObservedValue::unknown());

        self::assertSame(TransferEligibilityOutcome::Blocked, $evaluator->evaluate($blocked, $this->now)->outcome);
        self::assertSame(TransferEligibilityOutcome::NeedsVerification, $evaluator->evaluate($unknown, $this->now)->outcome);
    }

    private function eligibleInput(
        TransferWindowPhase $phase,
        TransferKingdomClassification $classification = TransferKingdomClassification::Ordinary,
        string $sourceGroupLabel = 'Group 4',
        string $targetGroupLabel = 'Group 4',
        ?TransferObservedValue $power = null,
        ?TransferObservedValue $governorHeroGeneration = null,
        ?TransferObservedValue $governorTruegoldLevel = null,
        ?TransferObservedValue $characterAgeOverTargetDays = null,
        ?TransferObservedValue $cooldownRemaining = null,
        ?TransferObservedValue $targetCharacterCount = null,
        ?TransferObservedValue $targetCapacity = null,
        ?TransferObservedValue $invitationCapacity = null,
        ?TransferObservedValue $transferOpenCapacity = null,
        ?TransferObservedValue $specialInvites = null,
        ?TransferObservedValue $invitation = null,
        ?TransferObservedValue $passesAvailable = null,
        ?TransferObservedValue $passesRequired = null,
        ?TransferObservedValue $resourceProtection = null,
        ?TransferObservedValue $inGameRules = null,
    ): TransferEligibilityInput {
        return new TransferEligibilityInput(
            phase: $phase,
            groupState: TransferRequirementState::Met,
            sourceGroupLabel: $sourceGroupLabel,
            targetGroupLabel: $targetGroupLabel,
            targetPowerCap: $this->current(125_000_000),
            targetClassification: $classification,
            targetHeroGeneration: $this->current(5),
            targetTruegoldLevel: $this->current(3),
            targetCharacterAgeThresholdDays: $this->current(120),
            targetCapacityRemaining: $targetCapacity ?? $this->current(20),
            invitationCapacityRemaining: $invitationCapacity ?? $this->current(10),
            transferOpenCapacityRemaining: $transferOpenCapacity ?? $this->current(10),
            specialInvitesAvailable: $specialInvites ?? $this->current(3),
            governorPower: $power ?? $this->current(118_400_000),
            governorHeroGeneration: $governorHeroGeneration ?? $this->current(5),
            governorTruegoldLevel: $governorTruegoldLevel ?? $this->current(3),
            characterAgeOverTargetDays: $characterAgeOverTargetDays ?? $this->current(60),
            transferCooldownRemainingDays: $cooldownRemaining ?? $this->current(0),
            targetExistingCharacterCount: $targetCharacterCount ?? $this->current(0),
            invitationStatus: $invitation ?? $this->current(TransferInvitationStatus::None->value),
            passesAvailable: $passesAvailable ?? $this->current(9),
            passesRequired: $passesRequired ?? $this->current(9),
            resourceProtectionVerified: $resourceProtection ?? $this->current(true),
            inGameRulesVerified: $inGameRules ?? $this->current(true),
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
