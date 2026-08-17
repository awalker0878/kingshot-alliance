<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Membership\Services;

use App\Contexts\Alliance\Access\ValueObjects\AllianceMutationContext;
use App\Contexts\Alliance\Membership\Enums\AllianceRank;
use App\Contexts\Alliance\Membership\Models\AllianceMembership;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

final readonly class MembershipAdministrationGuard
{
    /**
     * Apply Memberships-owned target/hierarchy rules after the caller has already
     * acquired authoritative Alliance mutation permission inside its transaction.
     */
    public function assertCanManage(AllianceMutationContext $context, AllianceMembership $target): void
    {
        if ((string) $target->alliance_id !== (string) $context->alliance->id) {
            throw new AuthorizationException;
        }

        if ((string) $target->player_id === (string) $context->actor->playerId) {
            throw ValidationException::withMessages([
                'membership' => 'Use the leave-alliance action to change the active Player membership.',
            ]);
        }

        if ($target->rank === AllianceRank::R5
            || $context->membership->rank->level() <= $target->rank->level()) {
            throw new AuthorizationException;
        }
    }

    public function assertCanDeactivate(AllianceMembership $target): void
    {
        if ($target->rank !== AllianceRank::R5) {
            return;
        }

        throw ValidationException::withMessages([
            'membership' => 'Transfer R5 Alliance leadership before changing this membership.',
        ]);
    }

    public function rank(AllianceMembership $membership): int
    {
        return $membership->rank->level();
    }
}
