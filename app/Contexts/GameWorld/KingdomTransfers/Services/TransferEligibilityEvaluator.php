<?php

declare(strict_types=1);

namespace App\Contexts\GameWorld\KingdomTransfers\Services;

use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferEligibilityOutcome;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferInvitationStatus;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferKingdomClassification;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferRequirementKey;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferRequirementState;
use App\Contexts\GameWorld\KingdomTransfers\Enums\TransferWindowPhase;
use App\Contexts\GameWorld\KingdomTransfers\ValueObjects\TransferEligibilityAssessment;
use App\Contexts\GameWorld\KingdomTransfers\ValueObjects\TransferEligibilityInput;
use App\Contexts\GameWorld\KingdomTransfers\ValueObjects\TransferObservedValue;
use App\Contexts\GameWorld\KingdomTransfers\ValueObjects\TransferRequirement;
use Carbon\CarbonImmutable;

final readonly class TransferEligibilityEvaluator
{
    public function evaluate(TransferEligibilityInput $input, CarbonImmutable $now): TransferEligibilityAssessment
    {
        if ($input->phase === TransferWindowPhase::Closed) {
            return new TransferEligibilityAssessment(TransferEligibilityOutcome::WindowClosed, [$this->phase($input->phase)], null, $now);
        }
        if (in_array($input->phase, [TransferWindowPhase::NotStarted, TransferWindowPhase::PreTransfer], true)) {
            return new TransferEligibilityAssessment(TransferEligibilityOutcome::NotOpenYet, [$this->phase($input->phase)], 'Wait for Invitational Transfer or Transfer Opens.', $now);
        }

        $requirements = [
            $this->phase($input->phase),
            $this->group($input),
            $this->heroGeneration($input),
            $this->truegoldLevel($input),
            $this->characterAge($input),
            $this->cooldown($input),
            $this->targetCharacterLimit($input),
            $this->powerCap($input),
            $this->invitation($input),
            $this->targetCapacity($input),
            $this->invitationCapacity($input),
            $this->transferOpenCapacity($input),
            $this->passes($input),
            $this->resourceProtection($input),
            $this->inGameRules($input),
        ];

        $states = array_map(static fn (TransferRequirement $row): TransferRequirementState => $row->state, $requirements);
        $primary = collect($requirements)->first(static fn (TransferRequirement $row): bool => ! in_array($row->state, [TransferRequirementState::Met, TransferRequirementState::NotApplicable], true));

        if (in_array(TransferRequirementState::Conflicting, $states, true) || in_array(TransferRequirementState::Stale, $states, true) || in_array(TransferRequirementState::Unknown, $states, true)) {
            $outcome = TransferEligibilityOutcome::NeedsVerification;
        } elseif ($this->hasHardBlocker($requirements)) {
            $outcome = TransferEligibilityOutcome::Blocked;
        } elseif (in_array(TransferRequirementState::Unmet, $states, true)) {
            $outcome = TransferEligibilityOutcome::EligibleWithAction;
        } else {
            $outcome = TransferEligibilityOutcome::EligibleNow;
        }

        return new TransferEligibilityAssessment($outcome, $requirements, $primary?->nextAction, $now);
    }

    private function phase(TransferWindowPhase $phase): TransferRequirement
    {
        $state = in_array($phase, [TransferWindowPhase::InvitationalTransfer, TransferWindowPhase::TransferOpens], true) ? TransferRequirementState::Met : TransferRequirementState::Unmet;

        return new TransferRequirement(TransferRequirementKey::WindowPhase, $state, 'Official Transfer Window phase.', $phase->value);
    }

    private function group(TransferEligibilityInput $input): TransferRequirement
    {
        if ($input->groupState !== TransferRequirementState::Met) {
            return new TransferRequirement(TransferRequirementKey::TransferGroup, $input->groupState, 'Source and target Kingdom official Transfer Group membership is not trustworthy.', null, null, 'Verify the official Transfer Group membership for both Kingdoms.');
        }
        $same = $input->sourceGroupLabel !== null && $input->sourceGroupLabel === $input->targetGroupLabel;

        return new TransferRequirement(TransferRequirementKey::TransferGroup, $same ? TransferRequirementState::Met : TransferRequirementState::Unmet, 'Kingdoms may transfer only within the same official Transfer Group for this window.', $input->sourceGroupLabel, $input->targetGroupLabel, $same ? null : 'Choose a target Kingdom in the same official Transfer Group.');
    }

    private function heroGeneration(TransferEligibilityInput $input): TransferRequirement
    {
        return $this->matchObserved(
            TransferRequirementKey::HeroGeneration,
            $input->governorHeroGeneration,
            $input->targetHeroGeneration,
            'The Governor source Kingdom and target Kingdom must have the same hero generation.',
            'Choose a target Kingdom with the same hero generation.',
        );
    }

    private function truegoldLevel(TransferEligibilityInput $input): TransferRequirement
    {
        return $this->matchObserved(
            TransferRequirementKey::TruegoldLevel,
            $input->governorTruegoldLevel,
            $input->targetTruegoldLevel,
            'The Governor source Kingdom and target Kingdom must have the same Truegold level.',
            'Choose a target Kingdom with the same Truegold level.',
        );
    }

    private function characterAge(TransferEligibilityInput $input): TransferRequirement
    {
        if ($input->targetCharacterAgeThresholdDays->state !== TransferRequirementState::Met) {
            return $this->fromObserved(TransferRequirementKey::CharacterAge, $input->targetCharacterAgeThresholdDays, 'Verify the target Kingdom character-age threshold.');
        }
        if ($input->characterAgeOverTargetDays->state !== TransferRequirementState::Met) {
            return $this->fromObserved(TransferRequirementKey::CharacterAge, $input->characterAgeOverTargetDays, 'Verify how many days this character is older than the target Kingdom.');
        }
        $actual = (int) $input->characterAgeOverTargetDays->value;
        $required = (int) $input->targetCharacterAgeThresholdDays->value;
        $met = $actual <= $required;

        return new TransferRequirement(
            TransferRequirementKey::CharacterAge,
            $met ? TransferRequirementState::Met : TransferRequirementState::Unmet,
            'A character cannot be older than the target Kingdom by more than the target-specific official threshold.',
            $actual,
            $required,
            $met ? null : 'Choose an eligible target Kingdom; this character exceeds its age threshold.',
            $input->characterAgeOverTargetDays->sourceType,
            $input->characterAgeOverTargetDays->sourceReference,
            $input->characterAgeOverTargetDays->observedAt,
            $input->characterAgeOverTargetDays->validUntil,
        );
    }

    private function cooldown(TransferEligibilityInput $input): TransferRequirement
    {
        if ($input->transferCooldownRemainingDays->state !== TransferRequirementState::Met) {
            return $this->fromObserved(TransferRequirementKey::TransferCooldown, $input->transferCooldownRemainingDays, 'Verify the current transfer cooldown.');
        }
        $remaining = (int) $input->transferCooldownRemainingDays->value;
        $met = $remaining === 0;

        return new TransferRequirement(
            TransferRequirementKey::TransferCooldown,
            $met ? TransferRequirementState::Met : TransferRequirementState::Unmet,
            'KingShot applies a 25-day cooldown after a completed transfer.',
            $remaining,
            0,
            $met ? null : 'Wait '.$remaining.' more day(s) for the transfer cooldown to expire.',
            $input->transferCooldownRemainingDays->sourceType,
            $input->transferCooldownRemainingDays->sourceReference,
            $input->transferCooldownRemainingDays->observedAt,
            $input->transferCooldownRemainingDays->validUntil,
        );
    }

    private function targetCharacterLimit(TransferEligibilityInput $input): TransferRequirement
    {
        if ($input->targetExistingCharacterCount->state !== TransferRequirementState::Met) {
            return $this->fromObserved(TransferRequirementKey::TargetCharacterLimit, $input->targetExistingCharacterCount, 'Verify the number of existing characters in the target Kingdom.');
        }
        $count = (int) $input->targetExistingCharacterCount->value;
        $met = $count < TransferOfficialRulebook::MAX_CHARACTERS_PER_KINGDOM;

        return new TransferRequirement(
            TransferRequirementKey::TargetCharacterLimit,
            $met ? TransferRequirementState::Met : TransferRequirementState::Unmet,
            'An account may have at most four characters in a Kingdom.',
            $count,
            TransferOfficialRulebook::MAX_CHARACTERS_PER_KINGDOM - 1,
            $met ? null : 'The target Kingdom already has the maximum number of characters for this account.',
            $input->targetExistingCharacterCount->sourceType,
            $input->targetExistingCharacterCount->sourceReference,
            $input->targetExistingCharacterCount->observedAt,
            $input->targetExistingCharacterCount->validUntil,
        );
    }

    private function powerCap(TransferEligibilityInput $input): TransferRequirement
    {
        if ($input->targetPowerCap->state !== TransferRequirementState::Met) {
            return $this->fromObserved(TransferRequirementKey::PowerCap, $input->targetPowerCap, 'Verify the target Kingdom Power Cap.');
        }
        if ($input->governorPower->state !== TransferRequirementState::Met) {
            return $this->fromObserved(TransferRequirementKey::PowerCap, $input->governorPower, 'Refresh this Governor’s Power observation.');
        }

        return new TransferRequirement(TransferRequirementKey::PowerCap, TransferRequirementState::Met, 'Governor Power and target Power Cap are current.', $input->governorPower->value, $input->targetPowerCap->value, null, $input->governorPower->sourceType, $input->governorPower->sourceReference, $input->governorPower->observedAt, $input->governorPower->validUntil);
    }

    private function invitation(TransferEligibilityInput $input): TransferRequirement
    {
        if ($input->targetPowerCap->state !== TransferRequirementState::Met || $input->governorPower->state !== TransferRequirementState::Met) {
            return new TransferRequirement(TransferRequirementKey::Invitation, TransferRequirementState::Unknown, 'Invitation type depends on current Governor Power and target Power Cap.', null, null, 'Verify Power and Power Cap first.');
        }
        $power = (int) $input->governorPower->value;
        $cap = (int) $input->targetPowerCap->value;
        $overCap = $power > $cap;
        if ($overCap && $input->targetClassification === TransferKingdomClassification::Leading) {
            return new TransferRequirement(TransferRequirementKey::Invitation, TransferRequirementState::Unmet, 'Leading Kingdoms cannot issue Special Invites to an over-cap Governor.', $power, $cap, 'Reduce Power to the cap or choose a target that can issue a Special Invite.');
        }
        if ($input->phase === TransferWindowPhase::TransferOpens && ! $overCap) {
            return new TransferRequirement(TransferRequirementKey::Invitation, TransferRequirementState::NotApplicable, 'Transfer Opens does not require an invitation for a Governor at or below the target Power Cap.');
        }
        if ($input->invitationStatus->state !== TransferRequirementState::Met) {
            return $this->fromObserved(TransferRequirementKey::Invitation, $input->invitationStatus, $overCap ? 'Verify an approved Special Invite.' : 'Verify an Ordinary Invite.');
        }
        $status = (string) $input->invitationStatus->value;
        $required = $overCap ? TransferInvitationStatus::SpecialApproved->value : TransferInvitationStatus::OrdinaryReceived->value;
        $met = $status === $required;

        return new TransferRequirement(TransferRequirementKey::Invitation, $met ? TransferRequirementState::Met : TransferRequirementState::Unmet, $overCap ? 'An over-cap Governor requires an approved Special Invite.' : 'Invitational Transfer requires an Ordinary Invite for a Governor at or below the cap.', $status, $required, $met ? null : ($overCap ? 'Obtain approval for a Special Invite.' : 'Obtain an Ordinary Invite.'), $input->invitationStatus->sourceType, $input->invitationStatus->sourceReference, $input->invitationStatus->observedAt, $input->invitationStatus->validUntil);
    }

    private function targetCapacity(TransferEligibilityInput $input): TransferRequirement
    {
        return $this->positiveCapacity(
            TransferRequirementKey::TargetCapacity,
            $input->targetCapacityRemaining,
            'Target Kingdom total transfer capacity remaining after observed use and Alliance reservations.',
            'Choose a target Kingdom with an available transfer slot or release an internal reservation.',
        );
    }

    private function invitationCapacity(TransferEligibilityInput $input): TransferRequirement
    {
        if ($input->targetPowerCap->state !== TransferRequirementState::Met || $input->governorPower->state !== TransferRequirementState::Met) {
            return new TransferRequirement(TransferRequirementKey::InvitationCapacity, TransferRequirementState::Unknown, 'Invitation capacity depends on the current Power Cap comparison.', null, null, 'Verify Power and Power Cap first.');
        }
        $overCap = (int) $input->governorPower->value > (int) $input->targetPowerCap->value;
        if ($overCap) {
            if ($input->invitationStatus->state === TransferRequirementState::Met && $input->invitationStatus->value === TransferInvitationStatus::SpecialApproved->value) {
                return new TransferRequirement(TransferRequirementKey::InvitationCapacity, TransferRequirementState::NotApplicable, 'The Governor already has an approved Special Invite; inventory was consumed before this eligibility check.');
            }

            return $this->positiveCapacity(
                TransferRequirementKey::InvitationCapacity,
                $input->specialInvitesAvailable,
                'Special Invite inventory currently available to the target Kingdom.',
                'Wait for Special Invite inventory to replenish or choose another eligible target.',
            );
        }
        if ($input->phase !== TransferWindowPhase::InvitationalTransfer) {
            return new TransferRequirement(TransferRequirementKey::InvitationCapacity, TransferRequirementState::NotApplicable, 'Ordinary Invite capacity applies during Invitational Transfer.');
        }

        return $this->positiveCapacity(
            TransferRequirementKey::InvitationCapacity,
            $input->invitationCapacityRemaining,
            'Ordinary Invite capacity remaining after observed use and Alliance reservations.',
            'Choose a target with Ordinary Invite capacity or release an internal reservation.',
        );
    }

    private function transferOpenCapacity(TransferEligibilityInput $input): TransferRequirement
    {
        if ($input->phase !== TransferWindowPhase::TransferOpens) {
            return new TransferRequirement(TransferRequirementKey::TransferOpenCapacity, TransferRequirementState::NotApplicable, 'Transfer Opens capacity applies only during Transfer Opens.');
        }
        if ($input->targetPowerCap->state === TransferRequirementState::Met && $input->governorPower->state === TransferRequirementState::Met && (int) $input->governorPower->value > (int) $input->targetPowerCap->value) {
            return new TransferRequirement(TransferRequirementKey::TransferOpenCapacity, TransferRequirementState::NotApplicable, 'An over-cap Governor follows the Special Invite path instead of the ordinary Transfer Opens pool.');
        }

        return $this->positiveCapacity(
            TransferRequirementKey::TransferOpenCapacity,
            $input->transferOpenCapacityRemaining,
            'Transfer Opens capacity remaining after observed use and Alliance reservations.',
            'Choose a target with Transfer Opens capacity or release an internal reservation.',
        );
    }

    private function passes(TransferEligibilityInput $input): TransferRequirement
    {
        if ($input->passesRequired->state !== TransferRequirementState::Met) {
            return $this->fromObserved(TransferRequirementKey::TransferPasses, $input->passesRequired, 'Observe the required Transfer Pass count in-game; the exact public formula is not published.');
        }
        if ($input->passesAvailable->state !== TransferRequirementState::Met) {
            return $this->fromObserved(TransferRequirementKey::TransferPasses, $input->passesAvailable, 'Refresh available Transfer Passes.');
        }
        $available = (int) $input->passesAvailable->value;
        $required = (int) $input->passesRequired->value;
        if ($required < TransferOfficialRulebook::MIN_REQUIRED_TRANSFER_PASSES || $required > TransferOfficialRulebook::MAX_REQUIRED_TRANSFER_PASSES) {
            return new TransferRequirement(TransferRequirementKey::TransferPasses, TransferRequirementState::Conflicting, 'The observed required Transfer Pass count is outside the current official 1–50 range.', $required, TransferOfficialRulebook::MAX_REQUIRED_TRANSFER_PASSES, 'Re-check the in-game Transfer Pass requirement.');
        }
        $met = $available >= $required;

        return new TransferRequirement(TransferRequirementKey::TransferPasses, $met ? TransferRequirementState::Met : TransferRequirementState::Unmet, 'Transfer Passes are compared with the current in-game required count.', $available, $required, $met ? null : 'Acquire '.($required - $available).' more Transfer Pass(es).', $input->passesAvailable->sourceType, $input->passesAvailable->sourceReference, $input->passesAvailable->observedAt, $input->passesAvailable->validUntil);
    }

    private function resourceProtection(TransferEligibilityInput $input): TransferRequirement
    {
        if ($input->resourceProtectionVerified->state !== TransferRequirementState::Met) {
            return $this->fromObserved(
                TransferRequirementKey::ResourceProtection,
                $input->resourceProtectionVerified,
                'Verify that resources above Storehouse Protection have been reduced or protected before transfer.',
            );
        }
        $protected = $input->resourceProtectionVerified->value === true;

        return new TransferRequirement(
            TransferRequirementKey::ResourceProtection,
            $protected ? TransferRequirementState::Met : TransferRequirementState::Unmet,
            'Resources above the Storehouse Protection limit are lost during Kingdom Transfer.',
            $protected,
            true,
            $protected ? null : 'Reduce or protect excess resources before transferring to avoid resource loss.',
            $input->resourceProtectionVerified->sourceType,
            $input->resourceProtectionVerified->sourceReference,
            $input->resourceProtectionVerified->observedAt,
            $input->resourceProtectionVerified->validUntil,
        );
    }

    private function inGameRules(TransferEligibilityInput $input): TransferRequirement
    {
        if ($input->inGameRulesVerified->state !== TransferRequirementState::Met) {
            return $this->fromObserved(TransferRequirementKey::InGameRules, $input->inGameRulesVerified, 'Verify the remaining in-game transfer requirements for this Governor and target.');
        }
        $met = $input->inGameRulesVerified->value === true;

        return new TransferRequirement(TransferRequirementKey::InGameRules, $met ? TransferRequirementState::Met : TransferRequirementState::Unmet, $met ? 'Current in-game rules show no additional unpublished blocker.' : ($input->inGameRulesVerified->details ?? 'The game reports an additional transfer blocker.'), $met, true, $met ? null : 'Resolve the in-game blocker and record a fresh verification.', $input->inGameRulesVerified->sourceType, $input->inGameRulesVerified->sourceReference, $input->inGameRulesVerified->observedAt, $input->inGameRulesVerified->validUntil);
    }

    private function matchObserved(TransferRequirementKey $key, TransferObservedValue $actual, TransferObservedValue $required, string $explanation, string $action): TransferRequirement
    {
        if ($required->state !== TransferRequirementState::Met) {
            return $this->fromObserved($key, $required, $action);
        }
        if ($actual->state !== TransferRequirementState::Met) {
            return $this->fromObserved($key, $actual, $action);
        }
        $met = $actual->value === $required->value;

        return new TransferRequirement($key, $met ? TransferRequirementState::Met : TransferRequirementState::Unmet, $explanation, $actual->value, $required->value, $met ? null : $action, $actual->sourceType, $actual->sourceReference, $actual->observedAt, $actual->validUntil);
    }

    private function positiveCapacity(TransferRequirementKey $key, TransferObservedValue $value, string $explanation, string $action): TransferRequirement
    {
        if ($value->state !== TransferRequirementState::Met) {
            return $this->fromObserved($key, $value, $action);
        }
        $remaining = (int) $value->value;
        $met = $remaining > 0;

        return new TransferRequirement($key, $met ? TransferRequirementState::Met : TransferRequirementState::Unmet, $explanation, $remaining, 1, $met ? null : $action, $value->sourceType, $value->sourceReference, $value->observedAt, $value->validUntil);
    }

    private function fromObserved(TransferRequirementKey $key, TransferObservedValue $value, string $action): TransferRequirement
    {
        return new TransferRequirement($key, $value->state, $value->details ?? 'The required observation is not current and authoritative.', $value->value, null, $action, $value->sourceType, $value->sourceReference, $value->observedAt, $value->validUntil);
    }

    /** @param list<TransferRequirement> $requirements */
    private function hasHardBlocker(array $requirements): bool
    {
        foreach ($requirements as $row) {
            if ($row->state !== TransferRequirementState::Unmet) {
                continue;
            }
            if (in_array($row->key, [
                TransferRequirementKey::TransferGroup,
                TransferRequirementKey::HeroGeneration,
                TransferRequirementKey::TruegoldLevel,
                TransferRequirementKey::CharacterAge,
                TransferRequirementKey::TargetCharacterLimit,
            ], true)) {
                return true;
            }
            if ($row->key === TransferRequirementKey::Invitation && str_contains($row->explanation, 'Leading Kingdoms')) {
                return true;
            }
            if ($row->key === TransferRequirementKey::InGameRules && $row->actual === false) {
                return true;
            }
        }

        return false;
    }
}
