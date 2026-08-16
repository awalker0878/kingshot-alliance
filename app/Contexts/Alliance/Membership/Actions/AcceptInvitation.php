<?php

declare(strict_types=1);

namespace App\Contexts\Alliance\Membership\Actions;

use App\Contexts\Accounts\Models\User;
use App\Contexts\Alliance\Core\Enums\AllianceStatus;
use App\Contexts\Alliance\Core\Models\Alliance;
use App\Contexts\Alliance\Membership\Enums\AllianceRank;
use App\Contexts\Alliance\Membership\Enums\InvitationStatus;
use App\Contexts\Alliance\Membership\Enums\MembershipStatus;
use App\Contexts\Alliance\Membership\Enums\RosterState;
use App\Contexts\Alliance\Membership\Models\AllianceMembership;
use App\Contexts\Alliance\Membership\Models\AllianceRosterEntry;
use App\Contexts\Alliance\Membership\Models\Invitation;
use App\Contexts\Alliance\Membership\Services\InvitationTokenService;
use App\Contexts\GameWorld\Actions\ClaimPlayerAccount;
use App\Contexts\GameWorld\Models\Player;
use App\Shared\Infrastructure\AuditTrail\Services\AuditRecorder;
use App\Shared\Infrastructure\Messaging\Outbox\Models\OutboxMessage;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use LogicException;

final readonly class AcceptInvitation
{
    public function __construct(
        private InvitationTokenService $tokens,
        private ClaimPlayerAccount $claimPlayerAccount,
        private AuditRecorder $audit,
    ) {}

    public function handle(User $user, string $token): AllianceMembership
    {
        $tokenHash = $this->tokens->hash($token);

        // Resolve the immutable routing keys without taking locks so the transaction
        // can acquire them in the repository-standard lifecycle-first order.
        $candidate = Invitation::query()
            ->select(['id', 'alliance_id'])
            ->where('token_hash', $tokenHash)
            ->where('status', InvitationStatus::Pending->value)
            ->where('expires_at', '>', now())
            ->firstOrFail();

        return DB::transaction(function () use ($user, $tokenHash, $candidate): AllianceMembership {
            $alliance = Alliance::query()
                ->whereKey($candidate->alliance_id)
                ->sharedLock()
                ->firstOrFail();

            if ($alliance->status !== AllianceStatus::Active) {
                throw ValidationException::withMessages([
                    'invitation' => 'This Alliance is not currently active.',
                ]);
            }

            $invitation = Invitation::query()
                ->whereKey($candidate->id)
                ->where('alliance_id', $alliance->id)
                ->where('token_hash', $tokenHash)
                ->where('status', InvitationStatus::Pending->value)
                ->where('expires_at', '>', now())
                ->lockForUpdate()
                ->firstOrFail();

            $currentUser = User::query()
                ->whereKey($user->id)
                ->sharedLock()
                ->firstOrFail();

            if (! hash_equals(Str::lower((string) $invitation->email), Str::lower((string) $currentUser->email))) {
                throw new AuthorizationException;
            }

            $player = $invitation->player()->first();
            if (! $player instanceof Player) {
                throw new LogicException('An invitation must reference a Player.');
            }

            $lockedPlayer = Player::query()
                ->whereKey($player->id)
                ->lockForUpdate()
                ->firstOrFail();

            if ($lockedPlayer->user_id !== null && (int) $lockedPlayer->user_id !== (int) $currentUser->id) {
                throw ValidationException::withMessages([
                    'invitation' => 'This Player belongs to another account.',
                ]);
            }

            if ((string) $lockedPlayer->current_kingdom_id !== (string) $alliance->kingdom_id) {
                throw ValidationException::withMessages([
                    'invitation' => 'This Player is no longer in the Alliance Kingdom.',
                ]);
            }

            $roster = AllianceRosterEntry::query()
                ->where('alliance_id', $alliance->id)
                ->where('player_id', $lockedPlayer->id)
                ->where('state', RosterState::Active->value)
                ->sharedLock()
                ->first();

            if (! $roster instanceof AllianceRosterEntry) {
                throw ValidationException::withMessages([
                    'invitation' => 'This Player is no longer active on the Alliance roster.',
                ]);
            }

            $otherActiveMembership = AllianceMembership::query()
                ->where('player_id', $lockedPlayer->id)
                ->where('status', MembershipStatus::Active->value)
                ->where('alliance_id', '!=', $alliance->id)
                ->lockForUpdate()
                ->first();

            if ($otherActiveMembership instanceof AllianceMembership) {
                throw ValidationException::withMessages([
                    'invitation' => 'This Player is already active in another Alliance.',
                ]);
            }

            $membership = AllianceMembership::query()
                ->where('alliance_id', $alliance->id)
                ->where('player_id', $lockedPlayer->id)
                ->lockForUpdate()
                ->first();

            if ($membership === null) {
                $membership = AllianceMembership::query()->create([
                    'alliance_id' => $alliance->id,
                    'player_id' => $lockedPlayer->id,
                    'status' => MembershipStatus::Active,
                    'rank' => AllianceRank::R1,
                    'joined_at' => now(),
                ]);
            } elseif ($membership->status !== MembershipStatus::Active) {
                $membership->forceFill([
                    'status' => MembershipStatus::Active,
                    'rank' => AllianceRank::R1,
                    'joined_at' => $membership->joined_at ?? now(),
                    'left_at' => null,
                ])->save();
            }

            $lockedPlayer = $this->claimPlayerAccount->handle($lockedPlayer, $currentUser);

            $invitation->forceFill([
                'status' => InvitationStatus::Accepted,
                'accepted_at' => now(),
            ])->save();

            $this->audit->record(
                event: 'invitation.accepted',
                actor: $lockedPlayer,
                subject: $invitation,
                alliance: $alliance,
                metadata: ['membership_id' => $membership->id],
            );

            OutboxMessage::query()->create([
                'alliance_id' => $alliance->id,
                'partition_key' => 'alliance:'.$alliance->id,
                'event_type' => 'invitation.accepted',
                'aggregate_type' => Invitation::class,
                'aggregate_id' => $invitation->id,
                'idempotency_key' => 'invitation.accepted:'.$invitation->id,
                'payload' => [
                    'invitation_id' => $invitation->id,
                    'alliance_id' => $alliance->id,
                    'membership_id' => $membership->id,
                    'player_id' => $lockedPlayer->id,
                ],
                'occurred_at' => now(),
                'available_at' => now(),
                'attempts' => 0,
            ]);

            return $membership->refresh()->load(['alliance', 'player']);
        });
    }
}
