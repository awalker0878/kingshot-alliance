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

final class TransferEligibilityEvaluator
{
    public function evaluate(TransferEligibilityInput $input, CarbonImmutable $now): TransferEligibilityAssessment
    {
        if ($input->phase === TransferWindowPhase::Closed) {
            return new TransferEligibilityAssessment(TransferEligibilityOutcome::WindowClosed, [$this->phase($input->phase)], null, $now);
        }
        if (in_array($input->phase, [TransferWindowPhase::NotStarted, TransferWindowPhase::PreTransfer], true)) {
            return new TransferEligibilityAssessment(TransferEligibilityOutcome::NotOpenYet, [$this->phase($input->phase)], 'Wait for Invitational Transfer or Transfer Opens.', $now);
        }

        $requirements = [$this->phase($input->phase), $this->group($input), $this->powerCap($input), $this->invitation($input), $this->passes($input), $this->inGameRules($input)];

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

    private function powerCap(TransferEligibilityInput $input): TransferRequirement
    {
        if ($input->targetPowerCap->state !== TransferRequirementState::Met) return $this->fromObserved(TransferRequirementKey::PowerCap, $input->targetPowerCap, 'Verify the target Kingdom Power Cap.');
        if ($input->governorPower->state !== TransferRequirementState::Met) return $this->fromObserved(TransferRequirementKey::PowerCap, $input->governorPower, 'Refresh this Governor’s Power observation.');
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
            return new TransferRequirement(TransferRequirementKey::Invitation, TransferRequirementState::NotApplicable, 'Phase III does not require an invitation for a Governor at or below the target Power Cap.');
        }
        if ($input->invitationStatus->state !== TransferRequirementState::Met) return $this->fromObserved(TransferRequirementKey::Invitation, $input->invitationStatus, $overCap ? 'Verify an approved Special Invite.' : 'Verify an Ordinary Invite.');
        $status = (string) $input->invitationStatus->value;
        $required = $overCap ? TransferInvitationStatus::SpecialApproved->value : TransferInvitationStatus::OrdinaryReceived->value;
        $met = $status === $required;
        return new TransferRequirement(TransferRequirementKey::Invitation, $met ? TransferRequirementState::Met : TransferRequirementState::Unmet, $overCap ? 'An over-cap Governor requires an approved Special Invite.' : 'Phase II requires an Ordinary Invite for a Governor at or below the cap.', $status, $required, $met ? null : ($overCap ? 'Obtain approval for a Special Invite.' : 'Obtain an Ordinary Invite.'), $input->invitationStatus->sourceType, $input->invitationStatus->sourceReference, $input->invitationStatus->observedAt, $input->invitationStatus->validUntil);
    }

    private function passes(TransferEligibilityInput $input): TransferRequirement
    {
        if ($input->passesRequired->state !== TransferRequirementState::Met) return $this->fromObserved(TransferRequirementKey::TransferPasses, $input->passesRequired, 'Observe the required Transfer Pass count in-game; the public formula is not published.');
        if ($input->passesAvailable->state !== TransferRequirementState::Met) return $this->fromObserved(TransferRequirementKey::TransferPasses, $input->passesAvailable, 'Refresh available Transfer Passes.');
        $available = (int) $input->passesAvailable->value;
        $required = (int) $input->passesRequired->value;
        $met = $available >= $required;
        return new TransferRequirement(TransferRequirementKey::TransferPasses, $met ? TransferRequirementState::Met : TransferRequirementState::Unmet, 'Transfer Passes are compared with the current in-game required count.', $available, $required, $met ? null : 'Acquire '.($required - $available).' more Transfer Pass(es).', $input->passesAvailable->sourceType, $input->passesAvailable->sourceReference, $input->passesAvailable->observedAt, $input->passesAvailable->validUntil);
    }

    private function inGameRules(TransferEligibilityInput $input): TransferRequirement
    {
        if ($input->inGameRulesVerified->state !== TransferRequirementState::Met) return $this->fromObserved(TransferRequirementKey::InGameRules, $input->inGameRulesVerified, 'Verify the remaining in-game transfer requirements for this Governor and target.');
        $met = $input->inGameRulesVerified->value === true;
        return new TransferRequirement(TransferRequirementKey::InGameRules, $met ? TransferRequirementState::Met : TransferRequirementState::Unmet, $met ? 'Current in-game rules show no additional unpublished blocker.' : ($input->inGameRulesVerified->details ?? 'The game reports an additional transfer blocker.'), $met, true, $met ? null : 'Resolve the in-game blocker and record a fresh verification.', $input->inGameRulesVerified->sourceType, $input->inGameRulesVerified->sourceReference, $input->inGameRulesVerified->observedAt, $input->inGameRulesVerified->validUntil);
    }

    private function fromObserved(TransferRequirementKey $key, TransferObservedValue $value, string $action): TransferRequirement
    {
        return new TransferRequirement($key, $value->state, $value->details ?? 'The required observation is not current and authoritative.', $value->value, null, $action, $value->sourceType, $value->sourceReference, $value->observedAt, $value->validUntil);
    }

    /** @param list<TransferRequirement> $requirements */
    private function hasHardBlocker(array $requirements): bool
    {
        foreach ($requirements as $row) {
            if ($row->state !== TransferRequirementState::Unmet) continue;
            if ($row->key === TransferRequirementKey::TransferGroup) return true;
            if ($row->key === TransferRequirementKey::Invitation && str_contains($row->explanation, 'Leading Kingdoms')) return true;
            if ($row->key === TransferRequirementKey::InGameRules && $row->actual === false) return true;
        }
        return false;
    }
}
