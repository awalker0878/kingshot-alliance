<?php

declare(strict_types=1);

namespace App\Domain\Memberships\Services;

use App\Domain\Alliances\Models\Alliance;
use App\Domain\Authorization\Enums\PermissionKey;
use App\Domain\Authorization\Services\AllianceAuthorization;
use App\Domain\Kingdoms\Models\Player;
use App\Domain\Memberships\Enums\AllianceRank;
use App\Domain\Memberships\Models\AllianceMembership;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Validation\ValidationException;

final readonly class MembershipAdministrationGuard
{
    public function __construct(private AllianceAuthorization $authorization) {}

    public function assertCanManage(Player $actor, Alliance $alliance, AllianceMembership $target): void
    {
        if ((string) $target->alliance_id !== (string) $alliance->id) {
            throw new AuthorizationException;
        }

        if (! $this->authorization->allows($actor, $alliance, PermissionKey::MembershipManage)) {
            throw new AuthorizationException;
        }

        $actorMembership = $this->authorization->activeMembership($actor, $alliance);

        if (! $actorMembership instanceof AllianceMembership) {
            throw new AuthorizationException;
        }

        if ((string) $target->player_id === (string) $actor->id) {
            throw ValidationException::withMessages([
                'membership' => 'Use the leave-alliance action to change the active Player membership.',
            ]);
        }

        if ($target->rank === AllianceRank::R5 || $actorMembership->rank->level() <= $target->rank->level()) {
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
