<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Membership\Services;

use App\Contexts\Accounts\Identity\Queries\AccountIdentityQuery;
use App\Contexts\Alliance\Lifecycle\Models\Alliance;
use App\Contexts\Alliance\Membership\Enums\MembershipStatus;
use App\Contexts\Alliance\Membership\Enums\RosterState;
use App\Contexts\Alliance\Membership\Models\AllianceMembership;
use App\Contexts\Alliance\Membership\Models\AllianceRosterEntry;
use App\Contexts\GameWorld\Players\Queries\PlayerReferenceQuery;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use LogicException;

/** Current eligibility shared by initial issuance and token renewal. */
final readonly class InvitationEligibility
{
    public function __construct(
        private PlayerReferenceQuery $players,
        private AccountIdentityQuery $accounts,
    ) {}

    public function assertEligible(Alliance $alliance, string $playerId, string $email, string $playerField = 'player_id', string $emailField = 'email'): void
    {
        if (DB::transactionLevel() < 1) {
            throw new LogicException('Invitation eligibility requires the current Alliance transaction.');
        }

        try {
            $target = $this->players->lockCurrentShared($playerId);
        } catch (ModelNotFoundException) {
            throw ValidationException::withMessages([$playerField => 'The invited Player is no longer a current game identity.']);
        }
        $roster = AllianceRosterEntry::query()
            ->where('alliance_id', $alliance->id)
            ->where('player_id', $playerId)
            ->where('state', RosterState::Active->value)
            ->sharedLock()
            ->first();
        if (! $roster instanceof AllianceRosterEntry || $target->kingdomId !== (string) $alliance->kingdom_id) {
            throw ValidationException::withMessages([$playerField => 'The invited Player must be active on this Alliance roster.']);
        }

        $activeMembership = AllianceMembership::query()
            ->where('player_id', $playerId)
            ->where('status', MembershipStatus::Active->value)
            ->first();
        if ($activeMembership instanceof AllianceMembership) {
            throw ValidationException::withMessages([
                $playerField => (string) $activeMembership->alliance_id === (string) $alliance->id
                    ? 'This Player is already an active Alliance member.'
                    : 'This Player is already active in another Alliance.',
            ]);
        }

        if ($target->userId !== null) {
            $owner = $this->accounts->require($target->userId);
            if ($owner->anonymized || ! hash_equals(Str::lower($owner->email), Str::lower(trim($email)))) {
                throw ValidationException::withMessages([$emailField => 'This Player is already owned by a different or unavailable account.']);
            }
        }
    }
}
